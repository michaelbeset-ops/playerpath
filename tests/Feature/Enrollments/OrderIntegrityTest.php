<?php

namespace Tests\Feature\Enrollments;

use App\Actions\Enrollments\CancelEnrollment;
use App\Actions\Enrollments\InviteFromWaitlist;
use App\Enums\DiscountKind;
use App\Enums\EnrollmentStatus;
use App\Enums\ParticipationStatus;
use App\Enums\Role;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Participation;
use App\Models\Payment;
use App\Models\PaymentOption;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\User;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Enrollment\OrderBuilder;
use App\Support\Enrollment\OrderWriter;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Wat er met een order gebeurt als het niet het rechte pad is: vol aanbod,
 * meerdere kinderen, kortingen, btw-tarieven, termijnen en annuleren.
 * Elk geval hier was ooit een manier om geld of plekken kwijt te raken.
 */
class OrderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Product $blok;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00'));

        $this->school = School::factory()->create(['slug' => 'keepersschool-rob']);
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);

        $this->blok = Product::factory()->for($this->school)->blok(capaciteit: 10)->create([
            'name' => 'Keepersblok',
            'amount_cents' => 12000,
            'starts_on' => '2026-12-01',
            'ends_on' => '2027-01-15',
        ]);

        app(Tenancy::class)->forget();
    }

    protected function standaardOptie(): int
    {
        return PaymentOption::withoutSchoolScope()->where('product_id', $this->blok->id)->where('is_default', true)->value('id');
    }

    /** @return array<string, mixed> */
    protected function kind(string $naam, ?int $optie = null, array $extra = []): array
    {
        return [
            'first_name' => $naam, 'last_name' => 'de Vries', 'date_of_birth' => '2016-04-12', 'position' => 'keeper',
            'product_id' => $this->blok->id, 'payment_option_id' => $optie ?? $this->standaardOptie(), ...$extra,
        ];
    }

    /** @param  list<array<string, mixed>>  $kinderen */
    protected function meldAan(array $kinderen, string $email = 'marieke@voorbeeld.nl', array $extra = [])
    {
        return $this->post('/inschrijven/keepersschool-rob', [
            'children' => $kinderen,
            'guardian_name' => 'Ouder '.$email, 'guardian_email' => $email, 'password' => 'wachtwoord123',
            'consents' => ['avg'], 'payment_method' => 'cash',
            ...$extra,
        ]);
    }

    protected function betaal(Payment $rekening): void
    {
        $this->actingAs($this->eigenaar)->patch('/payments/'.$rekening->id, ['status' => 'paid', 'method' => 'cash'])->assertSessionHasNoErrors();
    }

    protected function termijnOptie(): int
    {
        app(Tenancy::class)->set($this->school);
        $this->blok->syncPaymentOptions([
            ['type' => 'eenmalig', 'amount_cents' => 12000],
            ['type' => 'termijnen', 'amount_cents' => 4000, 'installments' => 3],
        ]);
        $id = $this->blok->paymentOptions()->where('type', 'termijnen')->value('id');
        app(Tenancy::class)->forget();

        return $id;
    }

    // --- 1. Overboeken ---

    public function test_wie_op_goedkeuring_wacht_houdt_een_plek_vast(): void
    {
        Notification::fake();

        app(Tenancy::class)->set($this->school);
        $this->blok->update(['capacity' => 1]);
        app(Tenancy::class)->forget();

        $this->meldAan([$this->kind('Sem')])->assertSessionHasNoErrors();
        $this->meldAan([$this->kind('Noud')], 'noud@voorbeeld.nl')->assertSessionHas('enrollment_submitted.status', 'waitlist');

        app(Tenancy::class)->set($this->school);
        $this->assertSame(EnrollmentStatus::AwaitingApproval, Enrollment::where('first_name', 'Sem')->firstOrFail()->status);
        $this->assertSame(EnrollmentStatus::Waitlist, Enrollment::where('first_name', 'Noud')->firstOrFail()->status);
        $this->assertTrue($this->blok->fresh()->isFull());
        $this->assertSame(1, Product::withSpotsTaken()->findOrFail($this->blok->id)->spotsTaken());
    }

    public function test_twee_kinderen_in_een_aanmelding_nemen_elk_een_plek(): void
    {
        Notification::fake();

        app(Tenancy::class)->set($this->school);
        $this->blok->update(['capacity' => 1]);
        app(Tenancy::class)->forget();

        $this->meldAan([$this->kind('Sem'), $this->kind('Liam')])->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $this->assertSame(EnrollmentStatus::AwaitingApproval, Enrollment::where('first_name', 'Sem')->firstOrFail()->status);
        $this->assertSame(EnrollmentStatus::Waitlist, Enrollment::where('first_name', 'Liam')->firstOrFail()->status);
    }

    public function test_bij_het_bevestigen_wordt_opnieuw_geteld_en_gaat_de_tweede_naar_de_wachtlijst(): void
    {
        Notification::fake();

        $this->meldAan([$this->kind('Sem')])->assertSessionHasNoErrors();
        $this->meldAan([$this->kind('Noud')], 'noud@voorbeeld.nl')->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $sem = Enrollment::where('first_name', 'Sem')->firstOrFail();
        $noud = Enrollment::where('first_name', 'Noud')->firstOrFail();
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$sem->id.'/approve')->assertSessionHasNoErrors();
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$noud->id.'/approve')->assertSessionHasNoErrors();

        // De school verkleint de groep terwijl beide nog moeten betalen.
        $this->blok->update(['capacity' => 1]);

        $this->betaal($sem->refresh()->order->payments()->firstOrFail());
        $this->betaal($noud->refresh()->order->payments()->firstOrFail());

        $this->assertSame(EnrollmentStatus::Confirmed, $sem->refresh()->status);
        $this->assertSame(EnrollmentStatus::Waitlist, $noud->refresh()->status);
        $this->assertTrue($noud->waitlist);
        $this->assertSame(1, Participation::confirmed()->where('product_id', $this->blok->id)->count());
        $this->assertDatabaseHas('participations', ['player_id' => $noud->player_id, 'status' => ParticipationStatus::Waitlist->value]);
    }

    // --- 2 en 8. Annuleren binnen een gedeelde order ---

    public function test_annuleren_haalt_het_kind_en_zijn_korting_van_een_openstaande_order(): void
    {
        Notification::fake();
        EnrollmentSettings::save($this->school, ['discounts' => ['volume' => ['enabled' => true, 'percent' => 10, 'from_count' => 2]]]);

        $this->meldAan([$this->kind('Sem'), $this->kind('Liam')])->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $order = Order::firstOrFail();
        $sem = Enrollment::where('first_name', 'Sem')->firstOrFail();
        $liam = Enrollment::where('first_name', 'Liam')->firstOrFail();
        $this->assertSame(21600, $order->total_cents);

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$sem->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame(21600, (int) $order->payments()->outstanding()->sum('amount_cents'));

        app(CancelEnrollment::class)->handle($liam->refresh(), $this->eigenaar);

        $order->refresh();
        $this->assertSame(EnrollmentStatus::Cancelled, $liam->refresh()->status);
        $this->assertSame(0, $liam->refund_cents);
        $this->assertSame(10800, $order->total_cents, 'Sems regel min zijn helft van de korting.');
        $this->assertSame(-1200, (int) $order->lines()->where('type', 'discount')->sum('amount_cents'));
        $this->assertSame(10800, (int) $order->payments()->outstanding()->sum('amount_cents'));
        $this->assertSame(1, $order->payments()->where('status', 'cancelled')->count());

        // 6. De vervallen rekening telt niet mee: betaald is betaald.
        $this->betaal($order->payments()->outstanding()->firstOrFail());
        $this->assertSame('paid', $order->refresh()->status->value);
        $this->assertSame(EnrollmentStatus::Confirmed, $sem->refresh()->status);
    }

    public function test_samen_komt_er_nooit_meer_terug_dan_er_betaald_is(): void
    {
        Notification::fake();
        EnrollmentSettings::save($this->school, ['discounts' => ['volume' => ['enabled' => true, 'percent' => 10, 'from_count' => 2]]]);
        $termijnen = $this->termijnOptie();

        $this->meldAan([$this->kind('Sem', $termijnen), $this->kind('Liam', $termijnen)])->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $order = Order::firstOrFail();
        $sem = Enrollment::where('first_name', 'Sem')->firstOrFail();
        $liam = Enrollment::where('first_name', 'Liam')->firstOrFail();

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$sem->id.'/approve')->assertSessionHasNoErrors();
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$liam->id.'/approve')->assertSessionHasNoErrors();
        $rekeningen = $order->payments()->orderBy('installment_number')->get();
        $this->assertSame([7200, 7200, 7200], $rekeningen->pluck('amount_cents')->all());

        // Eén termijn betaald; beide kinderen doen mee.
        $this->betaal($rekeningen[0]);
        $this->assertSame(EnrollmentStatus::Confirmed, $liam->refresh()->status);

        // Sem annuleert ruim voor de start: zijn netto deel is 10800, maar er
        // is maar 7200 binnen.
        app(CancelEnrollment::class)->handle($sem->refresh(), $this->eigenaar);
        $this->assertSame(7200, $sem->refresh()->refund_cents);

        // Wat er voor Liam nog open staat: zijn deel, want het betaalde gaat terug.
        $open = $order->refresh()->payments()->outstanding()->orderBy('installment_number')->get();
        $this->assertSame(10800, $order->total_cents);
        $this->assertSame([5400, 5400], $open->pluck('amount_cents')->all());
        $this->assertSame([2, 3], $open->pluck('installment_number')->all());

        // Het ouderscherm rekent hetzelfde: voor Liam is er niets meer terug te geven.
        $this->actingAs($liam->guardian)
            ->get('/billing')
            ->assertInertia(fn ($page) => $page
                ->where('enrollments', fn ($rijen) => collect($rijen)->firstWhere('child', 'Liam')['refund_cents'] === 0
                    && collect($rijen)->firstWhere('child', 'Sem')['refund_cents'] === 7200));

        app(CancelEnrollment::class)->handle($liam->refresh(), $this->eigenaar);
        $this->assertSame(0, $liam->refresh()->refund_cents);
        $this->assertLessThanOrEqual(7200, (int) Enrollment::sum('refund_cents'));
    }

    // --- 3. Kortingscodes ---

    public function test_een_kortingscode_telt_mee_en_stopt_bij_het_maximum(): void
    {
        Notification::fake();
        EnrollmentSettings::save($this->school, ['discounts' => ['code' => ['enabled' => true]]]);

        app(Tenancy::class)->set($this->school);
        $code = Discount::factory()->for($this->school)->create(['kind' => DiscountKind::Code, 'code' => 'ZOMER', 'percent' => 10, 'max_uses' => 1, 'uses' => 0]);
        app(Tenancy::class)->forget();

        $this->meldAan([$this->kind('Sem')], extra: ['code' => 'zomer'])->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $this->assertSame(1, $code->refresh()->uses);
        $this->assertSame(10800, Order::firstOrFail()->total_cents);
        app(Tenancy::class)->forget();

        // Op: de volgende krijgt de korting niet meer.
        $this->meldAan([$this->kind('Noud')], 'noud@voorbeeld.nl', ['code' => 'ZOMER'])->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $this->assertSame(1, $code->refresh()->uses);
        $this->assertSame(12000, Order::latest('id')->firstOrFail()->total_cents);
    }

    public function test_een_code_die_tussendoor_op_raakt_wordt_geweigerd(): void
    {
        app(Tenancy::class)->set($this->school);
        $code = Discount::factory()->for($this->school)->create(['max_uses' => 1, 'uses' => 1]);
        $ouder = User::factory()->for($this->school)->create();
        $speler = Player::factory()->for($this->school)->create();
        $inschrijving = Enrollment::factory()->for($this->school)->create(['product_id' => $this->blok->id, 'player_id' => $speler->id]);

        // De berekening zag de code nog als bruikbaar; bij het wegschrijven is hij op.
        $this->app->instance(OrderBuilder::class, new class($code->id) extends OrderBuilder
        {
            public function __construct(protected int $kortingId) {}

            public function build(EnrollmentSettings $settings, array $regels, ?User $ouder = null, ?string $code = null): array
            {
                return ['code' => ['code' => 'X', 'valid' => true, 'message' => null], 'lines' => [
                    ['type' => 'offering', 'description' => 'Blok', 'amount_cents' => 12000, 'vat_rate' => 21, 'product_id' => null, 'player_id' => null, 'discount_id' => null],
                    ['type' => 'discount', 'description' => 'Code', 'amount_cents' => -1200, 'vat_rate' => 0, 'product_id' => null, 'player_id' => null, 'discount_id' => $this->kortingId],
                ]];
            }
        });

        try {
            app(OrderWriter::class)->write(EnrollmentSettings::for($this->school), [[
                'product' => $this->blok, 'option' => PaymentOption::findOrFail($this->standaardOptie()),
                'child_name' => 'Sem', 'player_id' => $speler->id, 'enrollment' => $inschrijving,
            ]], $ouder, 'X');
            $this->fail('De code had geweigerd moeten worden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }

        $this->assertSame(1, $code->refresh()->uses);
        $this->assertSame(0, Order::count());
    }

    // --- 4 en 5. Btw en termijnen op de rekening ---

    public function test_verschillende_btw_tarieven_krijgen_elk_een_eigen_rekening(): void
    {
        Notification::fake();
        EnrollmentSettings::save($this->school, ['registration_fee' => ['enabled' => true, 'amount_cents' => 2500]]);

        app(Tenancy::class)->set($this->school);
        $this->blok->update(['vat_rate' => 9]);
        app(Tenancy::class)->forget();

        $this->meldAan([$this->kind('Sem')])->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $sem = Enrollment::firstOrFail();
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$sem->id.'/approve')->assertSessionHasNoErrors();

        $rekeningen = $sem->refresh()->order->payments()->orderBy('vat_rate')->get();
        $this->assertSame([9, 21], $rekeningen->pluck('vat_rate')->all());
        $this->assertSame([12000, 2500], $rekeningen->pluck('amount_cents')->all());
        $this->assertStringContainsString('(9% btw)', $rekeningen[0]->description);
    }

    public function test_een_enkel_tarief_komt_op_de_rekening_en_de_korting_blijft_binnen_het_totaal(): void
    {
        Notification::fake();
        EnrollmentSettings::save($this->school, ['discounts' => ['volume' => ['enabled' => true, 'percent' => 10, 'from_count' => 2]]]);

        app(Tenancy::class)->set($this->school);
        $this->blok->update(['vat_rate' => 9]);
        app(Tenancy::class)->forget();

        $this->meldAan([$this->kind('Sem'), $this->kind('Liam')])->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $sem = Enrollment::where('first_name', 'Sem')->firstOrFail();
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$sem->id.'/approve');

        $rekeningen = $sem->refresh()->order->payments;
        $this->assertCount(1, $rekeningen);
        $this->assertSame(9, $rekeningen[0]->vat_rate);
        $this->assertSame(21600, $rekeningen[0]->amount_cents);
        $this->assertStringNotContainsString('btw', $rekeningen[0]->description);
    }

    public function test_termijnen_alleen_als_elk_kind_dezelfde_termijnen_koos(): void
    {
        Notification::fake();
        $termijnen = $this->termijnOptie();

        $this->meldAan([$this->kind('Sem', $termijnen), $this->kind('Liam')])->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $sem = Enrollment::where('first_name', 'Sem')->firstOrFail();
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$sem->id.'/approve');

        $rekeningen = $sem->refresh()->order->payments;
        $this->assertCount(1, $rekeningen);
        $this->assertSame(24000, $rekeningen[0]->amount_cents);
        $this->assertNull($rekeningen[0]->installment_number);
    }

    // --- 10. Vol zonder wachtlijst ---

    public function test_vol_zonder_wachtlijst_wordt_geweigerd(): void
    {
        Notification::fake();
        EnrollmentSettings::save($this->school, ['capacity' => ['waitlist' => false]]);

        app(Tenancy::class)->set($this->school);
        $this->blok->update(['capacity' => 1]);
        Participation::create(['product_id' => $this->blok->id, 'player_id' => Player::factory()->for($this->school)->create()->id, 'status' => ParticipationStatus::Confirmed]);
        app(Tenancy::class)->forget();

        $this->meldAan([$this->kind('Sem')])
            ->assertSessionHasErrors(['children.0.product_id' => 'Dit aanbod zit vol. Kies een ander aanbod.']);

        $this->assertSame(0, Enrollment::withoutSchoolScope()->count());
    }

    public function test_zonder_wachtlijst_moet_er_voor_elk_kind_plek_zijn(): void
    {
        Notification::fake();
        EnrollmentSettings::save($this->school, ['capacity' => ['waitlist' => false]]);

        app(Tenancy::class)->set($this->school);
        $this->blok->update(['capacity' => 1]);
        app(Tenancy::class)->forget();

        $this->meldAan([$this->kind('Sem'), $this->kind('Liam')])->assertSessionHasErrors('children.1.product_id');
        $this->assertSame(0, Enrollment::withoutSchoolScope()->count());
    }

    // --- 11. Opnieuw uitnodigen loopt door de statusmachine ---

    public function test_een_verlopen_plek_kan_opnieuw_worden_uitgenodigd(): void
    {
        Notification::fake();

        app(Tenancy::class)->set($this->school);
        $this->blok->update(['capacity' => 1]);
        $bezet = Participation::create(['product_id' => $this->blok->id, 'player_id' => Player::factory()->for($this->school)->create()->id, 'status' => ParticipationStatus::Confirmed]);
        app(Tenancy::class)->forget();

        $this->meldAan([$this->kind('Sem')])->assertSessionHas('enrollment_submitted.status', 'waitlist');

        app(Tenancy::class)->set($this->school);
        $sem = Enrollment::firstOrFail();
        $bezet->update(['status' => ParticipationStatus::Cancelled]);

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$sem->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame(EnrollmentStatus::AwaitingPayment, $sem->refresh()->status);

        $this->travelTo(CarbonImmutable::parse('2026-09-10 10:00'));
        app(InviteFromWaitlist::class)->expire();

        $this->assertSame(EnrollmentStatus::Expired, $sem->refresh()->status);
        $this->assertSame(['cancelled'], $sem->order->payments()->pluck('status')->map->value->all());

        app(InviteFromWaitlist::class)->handle($sem, $this->eigenaar);

        $this->assertSame(EnrollmentStatus::AwaitingPayment, $sem->refresh()->status);
        $this->assertSame(12000, (int) $sem->order->payments()->outstanding()->sum('amount_cents'), 'Er is weer iets te betalen.');
    }
}
