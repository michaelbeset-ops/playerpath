<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\PaymentLink;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Support\FakeGateway;
use Tests\TestCase;

/**
 * Afrekenen via een ondertekende link uit een e-mail, zonder in te loggen.
 *
 * Wat hier getest wordt is vooral wat er níét kan: zonder handtekening, met een
 * gesleutelde handtekening, of na de vervaldatum hoort de pagina dicht te zijn.
 */
class PublicPayTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected Player $speler;

    protected Payment $betaling;

    protected FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create(['name' => 'Keepersschool Rob']);

        app(Tenancy::class)->set($this->school);

        $this->speler = Player::factory()->for($this->school)->create(['first_name' => 'Sem', 'last_name' => 'de Vries']);

        $this->betaling = Payment::create([
            'player_id' => $this->speler->id,
            'amount_cents' => 1500,
            'status' => PaymentStatus::Open,
            'description' => 'Losse training',
            'due_on' => now()->addWeek()->toDateString(),
        ]);

        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);

        // Geen ingelogde gebruiker: precies de situatie van een net
        // ingeschreven ouder die nog geen wachtwoord heeft gekozen.
        app(Tenancy::class)->forget();
    }

    protected function link(): string
    {
        return app(PaymentLink::class)->for($this->betaling);
    }

    public function test_de_betaalpagina_toont_alleen_wat_nodig_is_om_te_betalen(): void
    {
        $this->get($this->link())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/PublicPay')
                ->where('payment.amount', '€ 15,00')
                ->where('payment.description', 'Losse training')
                // Voornaam mag: die stond ook in de mail waar deze link in zat.
                ->where('payment.player', 'Sem')
                ->where('school.name', 'Keepersschool Rob')
                // Geen app-props op een openbare pagina.
                ->where('auth.user', null)
                ->where('nav', [])
            );

        // De achternaam hoort hier niet te staan.
        $this->get($this->link())->assertDontSee('de Vries');
    }

    public function test_zonder_geldige_handtekening_kom_je_er_niet_in(): void
    {
        $this->get('/betalen/'.$this->betaling->id)->assertForbidden();
        $this->get($this->link().'x')->assertForbidden();

        // En het id in een geldige link vervangen door een ander werkt evenmin.
        app(Tenancy::class)->set($this->school);

        $andere = Payment::create([
            'player_id' => $this->speler->id,
            'amount_cents' => 9900,
            'status' => PaymentStatus::Open,
            'description' => 'Iets duurders',
            'due_on' => now()->addWeek()->toDateString(),
        ]);

        app(Tenancy::class)->forget();

        $this->get(str_replace('/betalen/'.$this->betaling->id, '/betalen/'.$andere->id, $this->link()))
            ->assertForbidden();
    }

    public function test_een_verlopen_link_doet_niets_meer(): void
    {
        $link = $this->link();

        $this->travel(PaymentLink::DAGEN_GELDIG + 1)->days();

        $this->get($link)->assertForbidden();
    }

    public function test_betalen_stuurt_je_naar_de_provider_en_onthoudt_dat(): void
    {
        $this->get($this->link());

        $vervolg = $this->post(
            URL::temporarySignedRoute('public-pay.pay', now()->addHour(), ['payment' => $this->betaling->id])
        );

        // Inertia::location() is buiten een Inertia-verzoek een gewone redirect
        // naar de betaalpagina van de provider.
        $vervolg->assertRedirect();
        $this->assertStringContainsString('checkout', (string) $vervolg->headers->get('Location'));
        $this->assertCount(1, $this->gateway->started);

        $this->betaling->refresh();
        $this->assertNotNull($this->betaling->external_reference);
        $this->assertNotNull($this->betaling->checkout_url);
    }

    public function test_een_betaalde_rekening_toont_dat_en_start_niets(): void
    {
        $this->betaling->forceFill(['status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

        $this->get($this->link())
            ->assertInertia(fn ($page) => $page->where('payment.paid', true));

        $this->post(
            URL::temporarySignedRoute('public-pay.pay', now()->addHour(), ['payment' => $this->betaling->id])
        );

        $this->assertSame([], $this->gateway->started);
    }
}
