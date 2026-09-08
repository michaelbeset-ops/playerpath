<?php

namespace Tests\Feature\Billing;

use App\Actions\Payments\SyncPayment;
use App\Enums\MandateStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Mandate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Notifications\BetalingMislukt;
use App\Notifications\IncassoAankondiging;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Payments\PaymentGateway;
use App\Support\Payments\RemotePayment;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\Support\FakeGateway;
use Tests\TestCase;

/**
 * Voorbereiding op betalingen (onderdeel 7): het mandaat per ouder uit de
 * eerste iDEAL-betaling, de vooraankondiging vóór elke incasso, het
 * herhaalschema na een mislukte betaling, storneringskosten, en het venster
 * waarin een ouder een incasso kan terugdraaien.
 */
class DirectDebitPrepTest extends TestCase
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
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00'));

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

    /** Een rekening op een order van deze ouder, per incasso. */
    protected function orderRekening(array $velden = []): Payment
    {
        $order = Order::factory()->for($this->school)->create(['user_id' => $this->ouder->id, 'status' => OrderStatus::Open, 'total_cents' => 4000]);

        return Payment::factory()->for($this->school)->create(array_merge([
            'player_id' => $this->speler->id,
            'order_id' => $order->id,
            'amount_cents' => 4000,
            'method' => PaymentMethod::DirectDebit,
            'status' => PaymentStatus::Open,
            'due_on' => now()->toDateString(),
        ], $velden));
    }

    public function test_de_eerste_ideal_betaling_levert_een_mandaat_per_ouder_op(): void
    {
        $rekening = $this->orderRekening();

        // De ouder betaalt zelf: de checkout gaat met een klantkenmerk van de
        // ouder, niet van het kind.
        $this->actingAs($this->ouder)->post('/billing/payments/'.$rekening->id.'/betalen');

        $this->assertSame('cst_user_'.$this->ouder->id, $this->gateway->started[0]['customer']);
        $this->assertDatabaseHas('mandates', ['user_id' => $this->ouder->id, 'status' => MandateStatus::Pending->value]);
        $this->assertNull($this->speler->refresh()->payment_customer_reference);

        // De provider bevestigt met een mandaatkenmerk: vanaf nu geldig.
        $this->gateway->markPaid('tr_test_'.$rekening->id, PaymentMethod::Ideal, mandate: 'mdt_abc', customer: 'cst_user_'.$this->ouder->id);
        app(SyncPayment::class)->handle($rekening->refresh(), $this->gateway->fetch('tr_test_'.$rekening->id));

        $mandaat = Mandate::firstOrFail();
        $this->assertSame(MandateStatus::Valid, $mandaat->status);
        $this->assertSame('mdt_abc', $mandaat->mandate_reference);
        $this->assertFalse(Schema::hasColumn('mandates', 'iban'));
    }

    public function test_een_incasso_wordt_veertien_dagen_vooraf_aangekondigd_en_niet_eerder_afgeschreven(): void
    {
        Notification::fake();
        Mandate::factory()->for($this->school)->create(['user_id' => $this->ouder->id, 'customer_reference' => 'cst_user_1', 'mandate_reference' => 'mdt_1']);
        $this->gateway->mandate = true;

        $rekening = $this->orderRekening();

        // Zonder aankondiging wordt er niets afgeschreven.
        $this->artisan('payments:collect');
        $this->assertCount(0, $this->gateway->charged);

        $this->artisan('payments:prenotify')->assertSuccessful();
        $this->artisan('payments:prenotify');

        Notification::assertSentToTimes($this->ouder, IncassoAankondiging::class, 1);
        $this->assertNotNull($rekening->refresh()->prenotified_at);

        // Dertien dagen later nog niet.
        $this->travelTo(CarbonImmutable::parse('2026-09-14 10:00'));
        $this->artisan('payments:collect');
        $this->assertCount(0, $this->gateway->charged);

        // Veertien dagen later wel, op het mandaat van de ouder.
        $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00'));
        $this->artisan('payments:collect');
        $this->assertCount(1, $this->gateway->charged);
        $this->assertSame('cst_user_1', $this->gateway->charged[0]['customer']);
    }

    public function test_een_mislukte_betaling_krijgt_herinneringen_volgens_het_schema(): void
    {
        Notification::fake();
        EnrollmentSettings::save($this->school, ['dunning' => ['days' => [3, 7, 14]]]);

        $rekening = $this->orderRekening(['status' => PaymentStatus::Failed, 'due_on' => now()->subDays(10)->toDateString()]);

        // Dag 0: nog niets.
        $this->artisan('payments:remind');
        Notification::assertNotSentTo($this->ouder, BetalingMislukt::class);

        // Dag 3: de eerste, met een nieuwe betaallink.
        $this->travelTo(CarbonImmutable::parse('2026-09-04 10:00'));
        $this->artisan('payments:remind');
        $this->artisan('payments:remind');
        Notification::assertSentToTimes($this->ouder, BetalingMislukt::class, 1);
        $this->assertSame(1, $rekening->refresh()->reminder_count);

        Notification::assertSentTo($this->ouder, BetalingMislukt::class, function (BetalingMislukt $m) {
            $mail = $m->toMail($this->ouder);
            $this->assertStringContainsString('/betalen/', (string) $mail->actionUrl);
            $this->assertSame(1, $m->poging);
            $this->assertSame(3, $m->totaal);

            return true;
        });

        // Dag 7 en 14: de tweede en de laatste. Daarna niets meer.
        $this->travelTo(CarbonImmutable::parse('2026-09-08 10:00'));
        $this->artisan('payments:remind');
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00'));
        $this->artisan('payments:remind');
        $this->artisan('payments:remind');

        Notification::assertSentToTimes($this->ouder, BetalingMislukt::class, 3);
        $this->assertSame(3, $rekening->refresh()->reminder_count);
    }

    public function test_een_stornering_brengt_de_ingestelde_kosten_in_rekening_een_keer(): void
    {
        EnrollmentSettings::save($this->school, ['chargeback_fee' => ['enabled' => true, 'amount_cents' => 750]]);

        $rekening = $this->orderRekening(['status' => PaymentStatus::Paid, 'paid_at' => now(), 'external_reference' => 'tr_x']);

        $gestorneerd = new RemotePayment('tr_x', PaymentStatus::ChargedBack, method: PaymentMethod::DirectDebit);
        app(SyncPayment::class)->handle($rekening, $gestorneerd);
        app(SyncPayment::class)->handle($rekening->refresh(), $gestorneerd);

        $this->assertSame(PaymentStatus::ChargedBack, $rekening->refresh()->status);
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', ['parent_id' => $rekening->id, 'amount_cents' => 750, 'status' => 'open', 'description' => 'Storneringskosten']);
    }

    public function test_het_storneringsvenster_is_acht_weken_na_betaling(): void
    {
        $rekening = $this->orderRekening(['status' => PaymentStatus::Paid, 'paid_at' => CarbonImmutable::parse('2026-09-01'), 'method' => PaymentMethod::DirectDebit]);

        $this->assertTrue($rekening->isWithinChargebackWindow());
        $this->assertSame('2026-10-27', $rekening->chargebackWindowClosesAt()->toDateString());

        $this->travelTo(CarbonImmutable::parse('2026-10-28'));
        $this->assertFalse($rekening->isWithinChargebackWindow());

        // Alleen incasso kan gestorneerd worden; iDEAL niet.
        $rekening->update(['method' => PaymentMethod::Ideal]);
        $this->travelTo(CarbonImmutable::parse('2026-09-02'));
        $this->assertFalse($rekening->refresh()->isWithinChargebackWindow());
    }

    public function test_de_wizard_bewaart_het_herhaalschema(): void
    {
        $this->actingAs($this->eigenaar)->patch('/instellingen/inschrijven/stap/4', [
            'default_payment_type' => 'upfront', 'installments' => 3, 'installment_interval' => 'month',
            'auto_renew_block' => false, 'notice_months' => 1, 'approval' => 'manual',
            'chargeback_fee_enabled' => true, 'chargeback_fee_amount' => '7,50',
            'dunning_days' => '7, 3, 3, 14',
        ])->assertSessionHasNoErrors();

        $this->assertSame([3, 7, 14], EnrollmentSettings::for($this->school->refresh())->get('dunning')['days']);
    }
}
