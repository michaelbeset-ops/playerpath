<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class CashPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $ouder;

    protected Player $speler;

    protected FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        $this->speler = Player::factory()->for($this->school)->create();
        $this->speler->guardians()->attach($this->ouder->id);
    }

    public function test_contant_is_een_geldige_betaalmethode(): void
    {
        $this->assertSame('Contant', PaymentMethod::Cash->label());
        $this->assertArrayHasKey('cash', PaymentMethod::options());

        // Contant komt buiten het systeem om binnen en wordt nooit geïncasseerd.
        $this->assertFalse(PaymentMethod::Cash->isAutomatic());
        $this->assertTrue(PaymentMethod::Cash->isOffline());
        $this->assertTrue(PaymentMethod::DirectDebit->isAutomatic());
        $this->assertFalse(PaymentMethod::Ideal->isOffline());
    }

    public function test_de_eigenaar_zet_een_betaling_op_betaald_met_contant(): void
    {
        $betaling = Payment::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'method' => null,
        ]);

        $this->actingAs($this->eigenaar)
            ->patch("/payments/{$betaling->id}", ['status' => 'paid', 'method' => 'cash'])
            ->assertRedirect();

        $betaling->refresh();
        $this->assertSame(PaymentStatus::Paid, $betaling->status);
        $this->assertSame(PaymentMethod::Cash, $betaling->method);
        $this->assertNotNull($betaling->paid_at);
    }

    public function test_zonder_methode_blijft_de_bestaande_staan(): void
    {
        $betaling = Payment::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'method' => PaymentMethod::Cash,
        ]);

        $this->actingAs($this->eigenaar)->patch("/payments/{$betaling->id}", ['status' => 'paid']);

        $this->assertSame(PaymentMethod::Cash, $betaling->refresh()->method);
    }

    public function test_een_onbekende_methode_wordt_geweigerd(): void
    {
        $betaling = Payment::factory()->for($this->school)->create(['player_id' => $this->speler->id]);

        $this->actingAs($this->eigenaar)
            ->patch("/payments/{$betaling->id}", ['status' => 'paid', 'method' => 'bitcoin'])
            ->assertSessionHasErrors('method');
    }

    public function test_een_contante_rekening_krijgt_geen_betaalknop(): void
    {
        Payment::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'method' => PaymentMethod::Cash,
        ]);

        $this->actingAs($this->ouder)
            ->get('/billing')
            ->assertInertia(fn ($page) => $page
                ->where('payments.0.payable', false)
                ->where('payments.0.offline', true)
            );
    }

    public function test_een_ideal_rekening_krijgt_die_knop_wel(): void
    {
        Payment::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'method' => PaymentMethod::Ideal,
        ]);

        $this->actingAs($this->ouder)
            ->get('/billing')
            ->assertInertia(fn ($page) => $page
                ->where('payments.0.payable', true)
                ->where('payments.0.offline', false)
            );
    }

    public function test_online_betalen_van_een_contante_rekening_wordt_geweigerd(): void
    {
        $betaling = Payment::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'method' => PaymentMethod::Cash,
        ]);

        // Niet alleen de knop verbergen: anders betaalt een gezin dat de URL
        // kent alsnog twee keer.
        $this->actingAs($this->ouder)
            ->post("/billing/payments/{$betaling->id}/betalen")
            ->assertRedirect();

        $this->assertCount(0, $this->gateway->started);
    }

    public function test_een_contant_abonnement_wordt_nooit_geincasseerd(): void
    {
        $abonnement = Subscription::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'product_id' => null,
            'payment_method' => PaymentMethod::Cash,
            'interval' => BillingInterval::Monthly,
            'status' => SubscriptionStatus::Active,
            'starts_on' => now()->subMonth()->toDateString(),
        ]);

        Payment::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'subscription_id' => $abonnement->id,
            'method' => PaymentMethod::Cash,
            'due_on' => now()->subWeek()->toDateString(),
        ]);

        $this->speler->forceFill(['payment_customer_reference' => 'cst_test_1'])->save();
        $this->gateway->mandate = true;

        $this->artisan('payments:collect')->assertSuccessful();

        $this->assertCount(0, $this->gateway->charged);
    }

    public function test_een_contant_abonnement_brengt_wel_gewoon_rekeningen_voort(): void
    {
        $abonnement = Subscription::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'product_id' => null,
            'payment_method' => PaymentMethod::Cash,
            'interval' => BillingInterval::Monthly,
            'status' => SubscriptionStatus::Active,
            'starts_on' => now()->subDays(3)->toDateString(),
        ]);

        $this->artisan('payments:generate')->assertSuccessful();

        $betaling = Payment::where('subscription_id', $abonnement->id)->firstOrFail();

        // De administratie loopt gewoon door; alleen het innen gebeurt bij de school.
        $this->assertSame(PaymentMethod::Cash, $betaling->method);
        $this->assertSame(PaymentStatus::Open, $betaling->status);
    }

    public function test_het_betaaloverzicht_biedt_contant_als_keuze(): void
    {
        Payment::factory()->for($this->school)->create(['player_id' => $this->speler->id]);

        $this->actingAs($this->eigenaar)
            ->get('/payments')
            ->assertInertia(fn ($page) => $page->where('methods.cash', 'Contant'));
    }
}
