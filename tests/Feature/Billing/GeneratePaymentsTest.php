<?php

namespace Tests\Feature\Billing;

use App\Actions\Payments\GeneratePayments;
use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\Subscription;
use App\Support\Money\SplitAmount;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeneratePaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->speler = Player::factory()->for($this->school)->create();
    }

    protected function abonnement(array $velden = []): Subscription
    {
        return Subscription::factory()->for($this->school)->create(array_merge([
            'player_id' => $this->speler->id,
            'product_id' => null,
            'amount_cents' => 2750,
            'interval' => BillingInterval::Monthly,
            'status' => SubscriptionStatus::Active,
            'starts_on' => '2026-01-10',
            'ends_on' => null,
            'installments' => null,
        ], $velden));
    }

    public function test_een_maandabonnement_levert_een_rekening_voor_deze_maand(): void
    {
        $abonnement = $this->abonnement();

        $nieuw = app(GeneratePayments::class)->handle($abonnement, CarbonImmutable::parse('2026-03-15'));

        $this->assertCount(1, $nieuw);
        $this->assertSame(2750, $nieuw[0]->amount_cents);
        // De termijn loopt van de 10e tot de 10e, niet per kalendermaand.
        $this->assertSame('2026-03-10', $nieuw[0]->period_start->toDateString());
        $this->assertSame('2026-03-10', $nieuw[0]->due_on->toDateString());
    }

    public function test_twee_keer_draaien_maakt_geen_tweede_rekening(): void
    {
        $abonnement = $this->abonnement();
        $actie = app(GeneratePayments::class);

        $actie->handle($abonnement, CarbonImmutable::parse('2026-03-15'));
        $actie->handle($abonnement, CarbonImmutable::parse('2026-03-20'));
        $actie->handle($abonnement, CarbonImmutable::parse('2026-04-09'));

        $this->assertSame(1, Payment::where('subscription_id', $abonnement->id)->count());
    }

    public function test_een_nieuwe_maand_levert_wel_een_nieuwe_rekening(): void
    {
        $abonnement = $this->abonnement();
        $actie = app(GeneratePayments::class);

        $actie->handle($abonnement, CarbonImmutable::parse('2026-03-15'));
        $actie->handle($abonnement, CarbonImmutable::parse('2026-04-10'));

        $this->assertSame(2, Payment::where('subscription_id', $abonnement->id)->count());
    }

    public function test_voor_de_startdatum_gebeurt_er_niets(): void
    {
        $abonnement = $this->abonnement(['starts_on' => '2026-06-01']);

        $this->assertSame([], app(GeneratePayments::class)->handle($abonnement, CarbonImmutable::parse('2026-03-15')));
    }

    public function test_na_de_einddatum_gebeurt_er_niets(): void
    {
        $abonnement = $this->abonnement(['ends_on' => '2026-02-28']);

        $this->assertSame([], app(GeneratePayments::class)->handle($abonnement, CarbonImmutable::parse('2026-03-15')));
    }

    public function test_een_kwartaalabonnement_rekent_per_drie_maanden(): void
    {
        $abonnement = $this->abonnement(['interval' => BillingInterval::Quarterly]);
        $actie = app(GeneratePayments::class);

        $actie->handle($abonnement, CarbonImmutable::parse('2026-02-01'));
        $actie->handle($abonnement, CarbonImmutable::parse('2026-03-01'));

        // Februari en maart vallen in dezelfde termijn (10 jan - 9 apr).
        $this->assertSame(1, Payment::where('subscription_id', $abonnement->id)->count());
        $this->assertSame('2026-01-10', Payment::first()->period_start->toDateString());

        $actie->handle($abonnement, CarbonImmutable::parse('2026-04-15'));
        $this->assertSame(2, Payment::where('subscription_id', $abonnement->id)->count());
    }

    public function test_een_jaarbedrag_in_termijnen_telt_precies_op(): void
    {
        // 100 euro in 3 termijnen is niet 3 x 33,33 — er zou een cent missen.
        $abonnement = $this->abonnement([
            'interval' => BillingInterval::Yearly,
            'amount_cents' => 10000,
            'installments' => 3,
        ]);

        $nieuw = app(GeneratePayments::class)->handle($abonnement, CarbonImmutable::parse('2026-01-10'));

        $this->assertCount(3, $nieuw);
        $this->assertSame(10000, collect($nieuw)->sum('amount_cents'));
        $this->assertSame([3334, 3333, 3333], collect($nieuw)->pluck('amount_cents')->all());

        // Termijnen vervallen na elkaar, niet allemaal op dezelfde dag.
        $this->assertSame(
            ['2026-01-10', '2026-02-10', '2026-03-10'],
            collect($nieuw)->map(fn ($b) => $b->due_on->toDateString())->all(),
        );

        // Een jaartermijn die op 10 januari begint loopt tot 9 januari erna;
        // de omschrijving zegt dat eerlijk in plaats van alleen "2026".
        $this->assertSame('Contributie jan. 2026 - jan. 2027 (termijn 1 van 3)', $nieuw[0]->description);
    }

    public function test_een_ontbrekende_termijn_wordt_alsnog_aangevuld(): void
    {
        $abonnement = $this->abonnement(['interval' => BillingInterval::Yearly, 'amount_cents' => 9000, 'installments' => 3]);
        $actie = app(GeneratePayments::class);

        $nieuw = $actie->handle($abonnement, CarbonImmutable::parse('2026-01-10'));
        $nieuw[1]->delete();

        $aangevuld = $actie->handle($abonnement, CarbonImmutable::parse('2026-01-20'));

        $this->assertCount(1, $aangevuld);
        $this->assertSame(2, $aangevuld[0]->installment_number);
        $this->assertSame(3, Payment::where('subscription_id', $abonnement->id)->count());
    }

    public function test_een_gestopt_abonnement_krijgt_geen_rekening(): void
    {
        $abonnement = $this->abonnement(['status' => SubscriptionStatus::Cancelled]);

        $this->artisan('payments:generate')->assertSuccessful();

        $this->assertSame(0, Payment::where('subscription_id', $abonnement->id)->count());
    }

    public function test_het_commando_bedient_alle_scholen(): void
    {
        $this->abonnement();

        $andere = School::factory()->create();
        app(Tenancy::class)->forSchool($andere, function () use ($andere) {
            $speler = Player::factory()->for($andere)->create();
            Subscription::factory()->for($andere)->create([
                'player_id' => $speler->id,
                'product_id' => null,
                'status' => SubscriptionStatus::Active,
                'interval' => BillingInterval::Monthly,
                'starts_on' => now()->subMonths(2)->toDateString(),
            ]);
        });

        $this->artisan('payments:generate')->assertSuccessful();

        $this->assertSame(1, Payment::withoutSchoolScope()->where('school_id', $this->school->id)->count());
        $this->assertSame(1, Payment::withoutSchoolScope()->where('school_id', $andere->id)->count());
    }

    public function test_een_proefdraai_maakt_niets_aan(): void
    {
        $this->abonnement();

        $this->artisan('payments:generate', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Payment::count());
    }

    /** @return array<string, array{int, int, list<int>}> */
    public static function splitsingen(): array
    {
        return [
            'gaat gelijk op' => [9000, 3, [3000, 3000, 3000]],
            'een cent over' => [10000, 3, [3334, 3333, 3333]],
            'twee centen over' => [10001, 3, [3334, 3334, 3333]],
            'tien termijnen' => [27499, 10, [2750, 2750, 2750, 2750, 2750, 2750, 2750, 2750, 2750, 2749]],
            'een deel' => [2750, 1, [2750]],
        ];
    }

    /**
     * @param  list<int>  $verwacht
     */
    #[DataProvider('splitsingen')]
    public function test_een_bedrag_splitsen_verliest_geen_cent(int $cents, int $delen, array $verwacht): void
    {
        $delenLijst = SplitAmount::into($cents, $delen);

        $this->assertSame($verwacht, $delenLijst);
        $this->assertSame($cents, array_sum($delenLijst));
    }
}
