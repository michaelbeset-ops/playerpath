<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Payments\DemoGateway;
use App\Support\Payments\NotConnectedGateway;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * De demo-betaalflow: de hele keten zonder dat er een cent beweegt.
 *
 * Wat hier vast moet liggen: de demo staat alleen aan als je hem aanzet, hij
 * bestaat nooit naast een echte Mollie-sleutel, het scherm zegt dat het nep
 * is, en de uitkomst gaat door dezelfde deur als een echte webhook.
 */
class DemoPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $ouder;

    protected Payment $betaling;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(RoleSeeder::class);

        config(['services.payments.demo' => true, 'services.mollie.key' => null]);
        $this->app->forgetInstance(PaymentGateway::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        $speler = Player::factory()->for($this->school)->create();
        $speler->guardians()->attach($this->ouder->id);

        $this->betaling = Payment::factory()->for($this->school)->create(['player_id' => $speler->id]);
    }

    public function test_de_demo_staat_alleen_aan_als_je_hem_aanzet_en_nooit_naast_mollie(): void
    {
        $this->assertInstanceOf(DemoGateway::class, app(PaymentGateway::class));

        config(['services.payments.demo' => false]);
        $this->app->forgetInstance(PaymentGateway::class);
        $this->assertInstanceOf(NotConnectedGateway::class, app(PaymentGateway::class));

        // Zonder demo bestaat het demoscherm niet, ook niet met een geldige
        // handtekening.
        $url = URL::temporarySignedRoute('demo-pay.show', now()->addHour(), ['payment' => $this->betaling->id]);
        $this->get($url)->assertNotFound();
    }

    public function test_een_ouder_doorloopt_de_demo_en_de_rekening_staat_op_betaald(): void
    {
        $antwoord = $this->actingAs($this->ouder)->post("/billing/payments/{$this->betaling->id}/betalen");

        $antwoord->assertRedirect();
        $demoUrl = $antwoord->headers->get('Location');
        $this->assertStringContainsString('/betalen/demo/'.$this->betaling->id, $demoUrl);

        $this->betaling->refresh();
        $this->assertStringStartsWith('demo_', $this->betaling->external_reference);

        // Het scherm zegt dat het nep is en weet waar het heen moet.
        $this->actingAs($this->ouder)
            ->get($demoUrl)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/DemoPay')
                ->where('payment.paid', false)
                ->where('mandate', false)
                ->has('completeUrl')
            );

        $completeUrl = $this->inertiaProps($this->actingAs($this->ouder)->get($demoUrl))['completeUrl'];

        $this->actingAs($this->ouder)
            ->post($completeUrl, ['result' => 'paid', 'bank' => 'ING'])
            ->assertRedirect(route('billing.return', $this->betaling));

        $this->assertSame(PaymentStatus::Paid, $this->betaling->fresh()->status);
        $this->assertNotNull($this->betaling->fresh()->paid_at);

        // Terug in de app: de bevestiging, en de stand komt van de gateway.
        $this->actingAs($this->ouder)
            ->get(route('billing.return', $this->betaling))
            ->assertRedirect('/billing')
            ->assertSessionHas('status', 'Bedankt, we hebben je betaling ontvangen.');
    }

    public function test_annuleren_in_de_demo_laat_de_rekening_openstaan(): void
    {
        $demoUrl = $this->actingAs($this->ouder)
            ->post("/billing/payments/{$this->betaling->id}/betalen")
            ->headers->get('Location');

        $completeUrl = $this->inertiaProps($this->actingAs($this->ouder)->get($demoUrl))['completeUrl'];

        $this->actingAs($this->ouder)->post($completeUrl, ['result' => 'cancelled']);

        $this->assertSame(PaymentStatus::Cancelled, $this->betaling->fresh()->status);
        $this->assertNull($this->betaling->fresh()->paid_at);
    }

    public function test_de_link_naar_het_demoscherm_is_ondertekend(): void
    {
        $this->actingAs($this->ouder)
            ->get('/betalen/demo/'.$this->betaling->id)
            ->assertForbidden();
    }

    public function test_de_productiecontrole_weigert_de_demo(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->artisan('playerpath:check')->assertFailed();
    }
}
