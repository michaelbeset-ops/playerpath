<?php

namespace Tests\Feature\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Enrollment;
use App\Models\PaymentOption;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\InschrijvingGeannuleerd;
use App\Notifications\VerlengUitnodiging;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Enrollment\RefundPolicy;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Verlengen, opzeggen en annuleren (onderdeel 5): een blok verlengt niet
 * vanzelf, een abonnement loopt door tot de opzegtermijn om is, en annuleren
 * vóór de start volgt het restitutiebeleid.
 */
class LifecycleTest extends TestCase
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
            'starts_on' => '2026-10-05',
            'ends_on' => '2026-11-09',
        ]);

        app(Tenancy::class)->forget();
    }

    /** Een bevestigde inschrijving van een nieuwe ouder, betaald bij de school. */
    protected function bevestigdeInschrijving(?int $optieId = null): Enrollment
    {
        Notification::fake();

        $this->post('/inschrijven/keepersschool-rob', [
            'children' => [[
                'first_name' => 'Sem', 'last_name' => 'de Vries', 'date_of_birth' => '2016-04-12', 'position' => 'keeper',
                'product_id' => $this->blok->id,
                'payment_option_id' => $optieId ?? PaymentOption::withoutSchoolScope()->where('product_id', $this->blok->id)->where('is_default', true)->value('id'),
            ]],
            'guardian_name' => 'Marieke de Vries', 'guardian_email' => 'marieke@voorbeeld.nl', 'password' => 'wachtwoord123',
            'consents' => ['avg'], 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        app(Tenancy::class)->set($this->school);
        $inschrijving = Enrollment::firstOrFail();

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$inschrijving->id.'/approve');

        foreach ($inschrijving->refresh()->order?->payments ?? [] as $rekening) {
            $this->actingAs($this->eigenaar)->patch('/payments/'.$rekening->id, ['status' => 'paid', 'method' => 'cash']);
        }

        return $inschrijving->refresh();
    }

    public function test_het_restitutiebeleid_rekent_in_centen(): void
    {
        $beleid = RefundPolicy::for(EnrollmentSettings::for($this->school));
        $start = CarbonImmutable::parse('2026-10-05');

        // Standaard: kosteloos tot 14 dagen vooraf, daarna 50% ingehouden.
        $this->assertSame(12000, $beleid->refundCents(12000, $start, CarbonImmutable::parse('2026-09-21')));
        $this->assertSame(6000, $beleid->refundCents(12000, $start, CarbonImmutable::parse('2026-09-22')));
        $this->assertSame(0, $beleid->refundCents(0, $start));
        // Zonder startdatum telt het als ná de start.
        $this->assertSame(6000, $beleid->refundCents(12000, null));
    }

    public function test_een_ouder_annuleert_voor_de_start_en_krijgt_terug_wat_het_beleid_zegt(): void
    {
        $inschrijving = $this->bevestigdeInschrijving();
        $this->assertSame(EnrollmentStatus::Confirmed, $inschrijving->status);
        $ouder = $inschrijving->guardian;

        Notification::fake();

        // Ruim vóór de start: kosteloos.
        $this->actingAs($ouder)
            ->post('/billing/inschrijvingen/'.$inschrijving->id.'/annuleren', ['reason' => 'Verhuisd'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $inschrijving->refresh();

        $this->assertSame(EnrollmentStatus::Cancelled, $inschrijving->status);
        $this->assertSame(12000, $inschrijving->refund_cents);
        $this->assertSame('Verhuisd', $inschrijving->cancellation_reason);

        // Uit het aanbod en uit de groep; de betaling blijft als betaald staan.
        $this->assertDatabaseHas('participations', ['player_id' => $inschrijving->player_id, 'status' => 'cancelled']);
        $this->assertSame(0, Player::firstOrFail()->groups()->count());
        $this->assertDatabaseHas('payments', ['order_id' => $inschrijving->order_id, 'status' => PaymentStatus::Paid->value]);

        Notification::assertSentTo($this->eigenaar, InschrijvingGeannuleerd::class, fn (InschrijvingGeannuleerd $m) => $m->forSchool);
        Notification::assertSentTo($ouder, InschrijvingGeannuleerd::class, fn (InschrijvingGeannuleerd $m) => ! $m->forSchool);

        // Annuleren is definitief.
        $this->actingAs($ouder)->post('/billing/inschrijvingen/'.$inschrijving->id.'/annuleren')->assertSessionHasErrors('enrollment');
    }

    public function test_vlak_voor_de_start_houdt_de_school_haar_percentage_in(): void
    {
        $inschrijving = $this->bevestigdeInschrijving();

        $this->travelTo(CarbonImmutable::parse('2026-09-28'));
        Notification::fake();

        $this->actingAs($inschrijving->guardian)->post('/billing/inschrijvingen/'.$inschrijving->id.'/annuleren');

        $this->assertSame(6000, $inschrijving->refresh()->refund_cents);
    }

    public function test_een_andere_ouder_kan_niet_annuleren(): void
    {
        $inschrijving = $this->bevestigdeInschrijving();

        $andere = User::factory()->for($this->school)->create();
        $andere->assignRole(Role::Ouder->value);

        $this->actingAs($andere)->post('/billing/inschrijvingen/'.$inschrijving->id.'/annuleren')->assertForbidden();
        $this->assertSame(EnrollmentStatus::Confirmed, $inschrijving->refresh()->status);
    }

    public function test_de_levensloop_activeert_verlengt_en_beeindigt(): void
    {
        $inschrijving = $this->bevestigdeInschrijving();
        Notification::fake();

        // Vóór de start gebeurt er niets.
        $this->artisan('enrollments:lifecycle');
        $this->assertSame(EnrollmentStatus::Confirmed, $inschrijving->refresh()->status);

        // Op de startdag: actief.
        $this->travelTo(CarbonImmutable::parse('2026-10-05'));
        $this->artisan('enrollments:lifecycle');
        $this->assertSame(EnrollmentStatus::Active, $inschrijving->refresh()->status);
        Notification::assertNothingSent();

        // Twee weken voor het einde: de verleng-uitnodiging, één keer.
        $this->travelTo(CarbonImmutable::parse('2026-10-27'));
        $this->artisan('enrollments:lifecycle');
        $this->artisan('enrollments:lifecycle');
        Notification::assertSentTimes(VerlengUitnodiging::class, 1);
        $this->assertNotNull($inschrijving->refresh()->renewal_invited_at);

        // Na het einde: beëindigd.
        $this->travelTo(CarbonImmutable::parse('2026-11-10'));
        $this->artisan('enrollments:lifecycle');
        $this->assertSame(EnrollmentStatus::Ended, $inschrijving->refresh()->status);
    }

    public function test_met_automatisch_verlengen_gaat_er_geen_uitnodiging(): void
    {
        $inschrijving = $this->bevestigdeInschrijving();
        EnrollmentSettings::save($this->school, ['auto_renew_block' => true]);

        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-27'));
        $this->artisan('enrollments:lifecycle');

        Notification::assertNotSentTo($inschrijving->guardian, VerlengUitnodiging::class);
    }

    public function test_opzeggen_houdt_de_opzegtermijn_aan_en_beeindigt_daarna(): void
    {
        app(Tenancy::class)->set($this->school);
        $this->blok->update(['starts_on' => null, 'ends_on' => null, 'type' => 'doorlopend', 'stops_at_end' => false]);
        $this->blok->syncPaymentOptions([['type' => 'abonnement', 'amount_cents' => 3000, 'interval' => 'monthly']]);
        $optie = $this->blok->paymentOptions()->firstOrFail();
        app(Tenancy::class)->forget();

        $inschrijving = $this->bevestigdeInschrijving($optie->id);
        $abonnement = Subscription::firstOrFail();
        $this->assertSame(SubscriptionStatus::Active, $abonnement->status);

        // Opzegtermijn: standaard één maand.
        $this->actingAs($inschrijving->guardian)
            ->post('/billing/abonnementen/'.$abonnement->id.'/opzeggen')
            ->assertSessionHas('status');

        $abonnement->refresh();
        $this->assertSame(SubscriptionStatus::CancellationPlanned, $abonnement->status);
        $this->assertSame('2026-10-01', $abonnement->ends_on->toDateString());
        $this->assertSame(EnrollmentStatus::CancellationPlanned, $inschrijving->refresh()->status);

        // Tot die dag brengt het abonnement rekeningen voort; daarna is het klaar.
        $this->travelTo(CarbonImmutable::parse('2026-10-02'));
        $this->artisan('enrollments:lifecycle');

        $this->assertSame(SubscriptionStatus::Ended, $abonnement->refresh()->status);
        $this->assertSame(EnrollmentStatus::Ended, $inschrijving->refresh()->status);

        // Nog eens opzeggen kan niet.
        $this->actingAs($inschrijving->guardian)->post('/billing/abonnementen/'.$abonnement->id.'/opzeggen')->assertSessionHasErrors('subscription');
    }

    public function test_het_ouderscherm_toont_de_inschrijvingen_met_annuleerknop(): void
    {
        $inschrijving = $this->bevestigdeInschrijving();

        $this->actingAs($inschrijving->guardian)
            ->get('/billing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('enrollments', 1)
                ->where('enrollments.0.status', 'confirmed')
                ->where('enrollments.0.can_cancel', true)
                ->where('enrollments.0.refund', '€ 120,00')
            );
    }
}
