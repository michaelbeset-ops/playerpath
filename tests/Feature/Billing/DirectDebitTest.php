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

class DirectDebitTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $ouder;

    protected Player $speler;

    protected Subscription $abonnement;

    protected FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        $this->speler = Player::factory()->for($this->school)->create();
        $this->speler->guardians()->attach($this->ouder->id);

        $this->abonnement = Subscription::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'product_id' => null,
            'payment_method' => PaymentMethod::DirectDebit,
            'interval' => BillingInterval::Monthly,
            'status' => SubscriptionStatus::Active,
            'starts_on' => now()->subMonth()->toDateString(),
        ]);
    }

    protected function rekening(array $velden = []): Payment
    {
        return Payment::factory()->for($this->school)->create(array_merge([
            'player_id' => $this->speler->id,
            'subscription_id' => $this->abonnement->id,
            'method' => PaymentMethod::DirectDebit,
            'due_on' => now()->subDay()->toDateString(),
            // Aangekondigd, ruim veertien dagen geleden: anders incasseert de ronde niets.
            'prenotified_at' => now()->subDays(15),
        ], $velden));
    }

    public function test_de_eerste_betaling_legt_een_mandaat_vast(): void
    {
        $betaling = $this->rekening();

        $this->actingAs($this->ouder)->post("/billing/payments/{$betaling->id}/betalen");

        // De speler heeft nu een klant bij de provider, en die is meegestuurd.
        $this->assertNotNull($this->speler->refresh()->payment_customer_reference);
        $this->assertSame('cst_test_'.$this->speler->id, $this->gateway->started[0]['customer']);
    }

    public function test_zonder_incasso_wordt_er_geen_klant_aangemaakt(): void
    {
        $this->abonnement->update(['payment_method' => PaymentMethod::Ideal]);
        $betaling = $this->rekening();

        $this->actingAs($this->ouder)->post("/billing/payments/{$betaling->id}/betalen");

        $this->assertNull($this->speler->refresh()->payment_customer_reference);
        $this->assertNull($this->gateway->started[0]['customer']);
    }

    public function test_een_vervallen_rekening_wordt_afgeschreven_op_het_mandaat(): void
    {
        $betaling = $this->rekening();
        $this->speler->forceFill(['payment_customer_reference' => 'cst_test_1'])->save();
        $this->gateway->mandate = true;

        $this->artisan('payments:collect')->assertSuccessful();

        $this->assertCount(1, $this->gateway->charged);
        $this->assertSame('cst_test_1', $this->gateway->charged[0]['customer']);
        $this->assertSame('tr_incasso_'.$betaling->id, $betaling->refresh()->external_reference);
    }

    public function test_zonder_geldig_mandaat_wordt_er_niets_afgeschreven(): void
    {
        $this->rekening();
        $this->speler->forceFill(['payment_customer_reference' => 'cst_test_1'])->save();
        $this->gateway->mandate = false;

        $this->artisan('payments:collect')->assertSuccessful();

        $this->assertCount(0, $this->gateway->charged);
    }

    public function test_zonder_klant_gebeurt_er_niets(): void
    {
        $this->rekening();
        $this->gateway->mandate = true;

        $this->artisan('payments:collect')->assertSuccessful();

        $this->assertCount(0, $this->gateway->charged);
    }

    public function test_een_lopende_poging_wordt_niet_nog_eens_aangeboden(): void
    {
        $this->rekening(['external_reference' => 'tr_loopt_al']);
        $this->speler->forceFill(['payment_customer_reference' => 'cst_test_1'])->save();
        $this->gateway->mandate = true;

        $this->artisan('payments:collect');

        $this->assertCount(0, $this->gateway->charged);
    }

    public function test_wie_niet_op_incasso_staat_wordt_met_rust_gelaten(): void
    {
        $this->abonnement->update(['payment_method' => PaymentMethod::Ideal]);
        $this->rekening();
        $this->speler->forceFill(['payment_customer_reference' => 'cst_test_1'])->save();
        $this->gateway->mandate = true;

        $this->artisan('payments:collect');

        $this->assertCount(0, $this->gateway->charged);
    }

    public function test_een_rekening_die_nog_niet_vervallen_is_blijft_liggen(): void
    {
        $this->rekening(['due_on' => now()->addWeek()->toDateString()]);
        $this->speler->forceFill(['payment_customer_reference' => 'cst_test_1'])->save();
        $this->gateway->mandate = true;

        $this->artisan('payments:collect');

        $this->assertCount(0, $this->gateway->charged);
    }

    public function test_een_betaalde_rekening_wordt_niet_alsnog_geincasseerd(): void
    {
        $this->rekening(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
        $this->speler->forceFill(['payment_customer_reference' => 'cst_test_1'])->save();
        $this->gateway->mandate = true;

        $this->artisan('payments:collect');

        $this->assertCount(0, $this->gateway->charged);
    }

    public function test_een_proefdraai_incasseert_niets(): void
    {
        $this->rekening();
        $this->speler->forceFill(['payment_customer_reference' => 'cst_test_1'])->save();
        $this->gateway->mandate = true;

        $this->artisan('payments:collect', ['--dry-run' => true])->assertSuccessful();

        $this->assertCount(0, $this->gateway->charged);
    }

    public function test_zonder_provider_incasseert_de_ronde_niets(): void
    {
        $this->gateway->connected = false;
        $this->rekening();
        $this->speler->forceFill(['payment_customer_reference' => 'cst_test_1'])->save();

        $this->artisan('payments:collect')->assertSuccessful();

        $this->assertCount(0, $this->gateway->charged);
    }

    public function test_een_fout_bij_een_gezin_stopt_de_ronde_niet(): void
    {
        $this->rekening();
        $this->speler->forceFill(['payment_customer_reference' => 'cst_test_1'])->save();
        $this->gateway->mandate = true;
        $this->gateway->failWith = new \RuntimeException('Mollie ligt eruit');

        // De ronde loopt netjes af in plaats van halverwege te ontploffen.
        $this->artisan('payments:collect')->assertSuccessful();
    }
}
