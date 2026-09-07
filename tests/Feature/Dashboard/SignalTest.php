<?php

namespace Tests\Feature\Dashboard;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Report;
use App\Models\School;
use App\Models\User;
use App\Support\Dashboard\DevelopmentOverview;
use App\Support\Dashboard\Signal;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kleur op het dashboard moet iets betekenen.
 *
 * De drempels staan op één plek (Signal); dit legt vast wát die plek zegt, en
 * dat het dashboard die uitkomst ook echt meestuurt in plaats van er per
 * component een eigen grens op na te houden.
 */
class SignalTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);
    }

    public function test_stijgen_is_niet_overal_goed(): void
    {
        $this->assertSame(Signal::GOOD, Signal::trend(4));
        $this->assertSame(Signal::BAD, Signal::trend(-33));
        $this->assertSame(Signal::NEUTRAL, Signal::trend(0));
        $this->assertSame(Signal::NEUTRAL, Signal::trend(null));

        // Bij openstaande rekeningen is omhoog juist slecht; groen zou daar het
        // tegendeel zeggen van wat er staat.
        $this->assertSame(Signal::BAD, Signal::trend(4, higherIsBetter: false));
        $this->assertSame(Signal::GOOD, Signal::trend(-4, higherIsBetter: false));
    }

    public function test_een_percentage_kleurt_op_de_norm(): void
    {
        $this->assertSame(Signal::GOOD, Signal::ratio(75));
        $this->assertSame(Signal::GOOD, Signal::ratio(100));
        $this->assertSame(Signal::WARN, Signal::ratio(74));
        $this->assertSame(Signal::WARN, Signal::ratio(50));
        $this->assertSame(Signal::BAD, Signal::ratio(49));

        // Geen cijfers is grijs en niet oranje: een school die net begint hoort
        // geen alarm te krijgen over iets wat ze nog niet gedaan kán hebben.
        $this->assertSame(Signal::NEUTRAL, Signal::ratio(null));
    }

    public function test_het_dashboard_stuurt_de_kleur_mee(): void
    {
        Player::factory()->count(2)->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Twee nieuwe spelers deze maand: dat is goed nieuws.
                ->where('widgets.kpi_players.tone', Signal::GOOD)
                // Spelers zonder één rapport: daar valt het product stil.
                ->where('widgets.development.coverage.tone', Signal::BAD)
            );
    }

    public function test_zonder_spelers_valt_er_niets_te_kleuren(): void
    {
        // Een school die net begint hoort geen alarm te krijgen over iets wat
        // ze nog niet gedaan kán hebben.
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('widgets.development.coverage.percentage', null)
                ->where('widgets.development.coverage.tone', Signal::NEUTRAL)
            );
    }

    public function test_de_dekkingsbalk_kleurt_op_dezelfde_drempel(): void
    {
        // Drie van de vier spelers een recent rapport: 75 procent, dus goed.
        $spelers = Player::factory()->count(4)->for($this->school)->create();

        foreach ($spelers->take(3) as $speler) {
            Report::factory()->for($this->school)->create([
                'player_id' => $speler->id,
                'reported_on' => now()->subDays(3),
            ]);
        }

        $beeld = app(DevelopmentOverview::class)->for();

        $this->assertSame(75, $beeld['coverage']['percentage']);
        $this->assertSame(Signal::GOOD, $beeld['coverage']['tone']);
    }

    public function test_langst_geen_rapport_laat_wie_net_beoordeeld_is_weg(): void
    {
        $vers = Player::factory()->for($this->school)->create(['first_name' => 'Vers']);
        $stil = Player::factory()->for($this->school)->create(['first_name' => 'Stil']);
        $nooit = Player::factory()->for($this->school)->create(['first_name' => 'Nooit']);

        Report::factory()->for($this->school)->create(['player_id' => $vers->id, 'reported_on' => now()]);
        Report::factory()->for($this->school)->create(['player_id' => $stil->id, 'reported_on' => now()->subDays(20)]);

        $beeld = app(DevelopmentOverview::class)->for();
        $namen = collect($beeld['stalest'])->pluck('name');

        // Nooit beoordeeld staat bovenaan: dat is precies het geval waar een
        // ouder op afhaakt.
        $this->assertSame($nooit->full_name, $namen->first());
        $this->assertTrue($namen->contains($stil->full_name));

        // "0 dagen" in een lijst met de kop "langst geen rapport" is ruis.
        $this->assertFalse($namen->contains($vers->full_name));
    }

    public function test_omzet_staat_maar_op_een_plek(): void
    {
        Payment::factory()->for($this->school)->create([
            'player_id' => Player::factory()->for($this->school)->create()->id,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'amount_cents' => 5000,
        ]);

        // Standaard staat de omzettegel bovenaan; dan hoort hij niet ook in het
        // financiële vak te staan.
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('widgets.kpi_revenue.value', '€ 50,00')
                ->where('widgets.finance.revenue', null)
            );

        // Haalt de eigenaar die tegel weg, dan verschijnt de omzet in het
        // financiële vak — anders ziet hij hem nergens meer.
        $this->actingAs($this->eigenaar)->patch('/dashboard/indeling', [
            'widgets' => [
                ['key' => 'finance', 'x' => 0, 'y' => 0, 'w' => 6],
            ],
        ]);

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('widgets.kpi_revenue', null)
                ->where('widgets.finance.revenue.thisMonth', '€ 50,00')
            );
    }
}
