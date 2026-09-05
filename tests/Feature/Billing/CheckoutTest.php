<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Notifications\BetalingOntvangen;
use App\Support\Payments\NotConnectedGateway;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $ouder;

    protected Player $speler;

    protected Payment $betaling;

    protected FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        $this->speler = Player::factory()->for($this->school)->create();
        $this->speler->guardians()->attach($this->ouder->id);

        $this->betaling = Payment::factory()->for($this->school)->create(['player_id' => $this->speler->id]);
    }

    public function test_een_ouder_start_een_betaling_en_wordt_doorgestuurd(): void
    {
        $verwacht = 'https://betaalprovider.test/checkout/'.$this->betaling->id;

        $this->actingAs($this->ouder)
            ->post("/billing/payments/{$this->betaling->id}/betalen")
            ->assertRedirect($verwacht);

        $this->betaling->refresh();
        $this->assertSame('tr_test_'.$this->betaling->id, $this->betaling->external_reference);
        $this->assertNotNull($this->betaling->checkout_url);

        // De provider moet zowel weten waar de mens landt als waar de waarheid heen mag.
        $this->assertSame(route('billing.return', $this->betaling), $this->gateway->started[0]['returnUrl']);
        $this->assertSame(route('webhooks.mollie'), $this->gateway->started[0]['webhookUrl']);
    }

    public function test_inertia_krijgt_een_externe_omleiding(): void
    {
        // Een gewone browserpost krijgt 302; een Inertia-verzoek moet 409 met
        // een locatie-header krijgen, anders blijft de gebruiker op de pagina.
        $this->actingAs($this->ouder)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
            ->post("/billing/payments/{$this->betaling->id}/betalen")
            ->assertStatus(409)
            ->assertHeader('x-inertia-location', 'https://betaalprovider.test/checkout/'.$this->betaling->id);
    }

    public function test_twee_keer_klikken_maakt_geen_tweede_betaling(): void
    {
        $this->actingAs($this->ouder)->post("/billing/payments/{$this->betaling->id}/betalen");
        $this->actingAs($this->ouder)->post("/billing/payments/{$this->betaling->id}/betalen");

        $this->assertCount(1, $this->gateway->started);
    }

    public function test_een_voldane_betaling_kan_niet_opnieuw(): void
    {
        $this->betaling->forceFill(['status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

        $this->actingAs($this->ouder)
            ->post("/billing/payments/{$this->betaling->id}/betalen")
            ->assertRedirect();

        $this->assertCount(0, $this->gateway->started);
    }

    public function test_zonder_provider_wordt_er_niets_gestart(): void
    {
        $this->app->instance(PaymentGateway::class, new NotConnectedGateway);

        $this->actingAs($this->ouder)
            ->post("/billing/payments/{$this->betaling->id}/betalen")
            ->assertRedirect();

        $this->assertNull($this->betaling->refresh()->external_reference);
    }

    public function test_een_ouder_kan_niet_betalen_voor_een_ander_kind(): void
    {
        $ander = Player::factory()->for($this->school)->create();
        $vreemde = Payment::factory()->for($this->school)->create(['player_id' => $ander->id]);

        $this->actingAs($this->ouder)->post("/billing/payments/{$vreemde->id}/betalen")->assertForbidden();
    }

    public function test_een_betaling_van_een_andere_school_bestaat_niet(): void
    {
        $andere = School::factory()->create();
        $vreemdeSpeler = Player::factory()->for($andere)->create();
        $vreemde = Payment::factory()->for($andere)->create(['player_id' => $vreemdeSpeler->id]);

        $this->actingAs($this->ouder)->post("/billing/payments/{$vreemde->id}/betalen")->assertNotFound();
    }

    public function test_de_webhook_zet_de_betaling_op_betaald_en_bevestigt_dat(): void
    {
        Notification::fake();

        $this->actingAs($this->ouder)->post("/billing/payments/{$this->betaling->id}/betalen");
        $referentie = $this->betaling->refresh()->external_reference;

        $this->gateway->markPaid($referentie, PaymentMethod::Ideal);

        $this->post('/webhooks/mollie', ['id' => $referentie])->assertOk();

        $this->betaling->refresh();
        $this->assertSame(PaymentStatus::Paid, $this->betaling->status);
        $this->assertNotNull($this->betaling->paid_at);
        $this->assertSame(PaymentMethod::Ideal, $this->betaling->method);

        Notification::assertSentTo($this->ouder, BetalingOntvangen::class);
    }

    public function test_dezelfde_webhook_twee_keer_bevestigt_maar_een_keer(): void
    {
        Notification::fake();

        $this->actingAs($this->ouder)->post("/billing/payments/{$this->betaling->id}/betalen");
        $referentie = $this->betaling->refresh()->external_reference;
        $this->gateway->markPaid($referentie);

        $this->post('/webhooks/mollie', ['id' => $referentie]);
        $this->post('/webhooks/mollie', ['id' => $referentie]);

        Notification::assertSentToTimes($this->ouder, BetalingOntvangen::class, 1);
    }

    public function test_een_stornering_komt_terug_in_de_administratie(): void
    {
        $this->actingAs($this->ouder)->post("/billing/payments/{$this->betaling->id}/betalen");
        $referentie = $this->betaling->refresh()->external_reference;

        $this->gateway->markPaid($referentie);
        $this->post('/webhooks/mollie', ['id' => $referentie]);

        $this->gateway->markStatus($referentie, PaymentStatus::ChargedBack);
        $this->post('/webhooks/mollie', ['id' => $referentie]);

        $this->betaling->refresh();
        $this->assertSame(PaymentStatus::ChargedBack, $this->betaling->status);
        // Geen betaaldatum meer: er is geen geld meer binnen.
        $this->assertNull($this->betaling->paid_at);
        $this->assertTrue($this->betaling->status->needsAttention());
    }

    public function test_een_onbekende_webhook_geeft_gewoon_tweehonderd(): void
    {
        $this->post('/webhooks/mollie', ['id' => 'tr_bestaat_niet'])->assertOk();
        $this->post('/webhooks/mollie', [])->assertOk();
    }

    public function test_de_webhook_heeft_geen_csrf_token_nodig(): void
    {
        // Zonder uitzondering zou dit een 419 geven; Mollie stuurt geen token mee.
        $this->withMiddleware()
            ->post('/webhooks/mollie', ['id' => 'tr_onbekend'])
            ->assertOk();
    }

    public function test_terugkomen_uit_de_browser_ververst_de_stand(): void
    {
        $this->actingAs($this->ouder)->post("/billing/payments/{$this->betaling->id}/betalen");
        $referentie = $this->betaling->refresh()->external_reference;
        $this->gateway->markPaid($referentie);

        $this->actingAs($this->ouder)
            ->get("/billing/payments/{$this->betaling->id}/terug")
            ->assertRedirect('/billing');

        $this->assertSame(PaymentStatus::Paid, $this->betaling->refresh()->status);
    }

    public function test_het_ouderscherm_biedt_alleen_te_betalen_regels_een_knop(): void
    {
        Payment::factory()->for($this->school)->paid()->create(['player_id' => $this->speler->id]);

        $this->actingAs($this->ouder)
            ->get('/billing')
            ->assertInertia(fn ($page) => $page
                ->where('payments', fn ($rijen) => collect($rijen)->where('payable', true)->count() === 1)
            );
    }

    public function test_zonder_provider_toont_het_ouderscherm_geen_knop(): void
    {
        $this->app->instance(PaymentGateway::class, new NotConnectedGateway);

        $this->actingAs($this->ouder)
            ->get('/billing')
            ->assertInertia(fn ($page) => $page
                ->where('payments', fn ($rijen) => collect($rijen)->where('payable', true)->count() === 0)
            );
    }
}
