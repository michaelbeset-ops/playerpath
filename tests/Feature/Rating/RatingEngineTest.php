<?php

namespace Tests\Feature\Rating;

use App\Enums\AttendanceStatus;
use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\PlayerCardSeason;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Models\XpEvent;
use App\Support\Rating\AgeCategory;
use App\Support\Rating\RatingEngine;
use App\Support\Rating\RatingSettings;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * De rekenkern: rating, XP en level.
 *
 * Drie dingen die deze test bewaakt: XP daalt nooit door prestaties, elke XP
 * is uitlegbaar (een boeking met een reden), en een leeftijdsovergang bewaart
 * de oude kaart in plaats van hem te laten verdwijnen.
 */
class RatingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected Player $speler;

    protected RatingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        $this->speler = Player::factory()->for($this->school)->keeper()->create(['date_of_birth' => '2015-03-10']);

        $this->engine = app(RatingEngine::class);

        $this->travelTo('2026-09-12 12:00:00');
    }

    protected function rapporteer(float $cijfer): void
    {
        $scores = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => $cijfer])
            ->all();

        $this->actingAs($this->trainer)
            ->post("/players/{$this->speler->id}/reports", ['scores' => $scores])
            ->assertSessionHasNoErrors();
    }

    protected function training(): Training
    {
        $groep = Group::factory()->for($this->school)->create();
        $groep->players()->attach($this->speler->id);

        return Training::factory()->for($this->school)->for($groep)->create();
    }

    // ---------------------------------------------------------------
    // Leeftijdscategorie
    // ---------------------------------------------------------------

    public function test_de_categorie_volgt_het_geboortejaar(): void
    {
        $op = Carbon::parse('2026-09-12');

        // Seizoen 2026/27: geboren 2015 wordt 11 → O12; geboren 2014 wordt 12 → O14.
        $this->assertSame('O12', AgeCategory::forBirthDate(Carbon::parse('2015-03-10'), $op));
        $this->assertSame('O14', AgeCategory::forBirthDate(Carbon::parse('2014-11-30'), $op));
        $this->assertSame('O8', AgeCategory::forBirthDate(Carbon::parse('2019-06-01'), $op));
        $this->assertSame('O18+', AgeCategory::forBirthDate(Carbon::parse('2008-01-01'), $op));
    }

    public function test_het_seizoen_loopt_van_augustus_tot_juli(): void
    {
        $this->assertSame('2026/27', AgeCategory::seasonLabel(Carbon::parse('2026-09-12')));
        // In maart hoort het nog bij het seizoen dat in augustus ervoor begon.
        $this->assertSame('2026/27', AgeCategory::seasonLabel(Carbon::parse('2027-03-01')));
        $this->assertSame('2027/28', AgeCategory::seasonLabel(Carbon::parse('2027-08-01')));
    }

    public function test_de_eerste_vaststelling_is_geen_overgang(): void
    {
        $this->assertFalse($this->engine->syncCategory($this->speler));
        $this->assertSame('O12', $this->speler->fresh()->age_category);
        $this->assertSame(0, PlayerCardSeason::count());
    }

    public function test_een_overgang_bewaart_de_oude_kaart_als_seizoenskaart(): void
    {
        $this->engine->syncCategory($this->speler);
        $this->rapporteer(8);
        $this->assertSame('O12', $this->speler->fresh()->age_category);

        // Een seizoen verder: geboren 2015 wordt in 2027 twaalf → O14.
        $this->travelTo('2027-09-01 03:00:00');

        $this->assertTrue($this->engine->syncCategory($this->speler->fresh()));

        $speler = $this->speler->fresh();
        $this->assertSame('O14', $speler->age_category);
        $this->assertTrue($this->engine->recentlyMovedUp($speler));

        $kaart = PlayerCardSeason::firstOrFail();
        $this->assertSame('2026/27', $kaart->season);
        $this->assertSame('O12', $kaart->age_category);
        $this->assertSame(80, $kaart->overall_rating);
        // De rating zelf blijft staan: de demping vangt de hogere lat op.
        $this->assertSame(80, $speler->overall_rating);
    }

    public function test_het_commando_stelt_categorieen_vast_en_is_idempotent(): void
    {
        $this->artisan('players:categories')->assertSuccessful();
        $this->assertSame('O12', $this->speler->fresh()->age_category);

        $this->artisan('players:categories')->assertSuccessful();
        $this->assertSame(0, PlayerCardSeason::count());
    }

    // ---------------------------------------------------------------
    // XP
    // ---------------------------------------------------------------

    public function test_aanwezig_zijn_levert_xp_op(): void
    {
        $training = $this->training();

        $this->actingAs($this->trainer)
            ->patch("/trainings/{$training->id}/attendance/{$this->speler->id}", ['status' => AttendanceStatus::Present->value]);

        $this->assertSame(10, $this->speler->fresh()->xp);
        $this->assertSame('attendance', XpEvent::firstOrFail()->source);
    }

    public function test_twee_keer_afvinken_telt_een_keer(): void
    {
        $training = $this->training();
        $url = "/trainings/{$training->id}/attendance/{$this->speler->id}";

        $this->actingAs($this->trainer)->patch($url, ['status' => AttendanceStatus::Present->value]);
        $this->actingAs($this->trainer)->patch($url, ['status' => AttendanceStatus::Present->value]);

        $this->assertSame(10, $this->speler->fresh()->xp);
        $this->assertSame(1, XpEvent::count());
    }

    public function test_terugdraaien_neemt_de_xp_weer_mee(): void
    {
        $training = $this->training();
        $url = "/trainings/{$training->id}/attendance/{$this->speler->id}";

        $this->actingAs($this->trainer)->patch($url, ['status' => AttendanceStatus::Present->value]);
        $this->actingAs($this->trainer)->patch($url, ['status' => AttendanceStatus::Absent->value]);

        // Een correctie van een fout, niet een straf.
        $this->assertSame(0, $this->speler->fresh()->xp);
        $this->assertSame(0, XpEvent::count());
    }

    public function test_afwezig_levert_niets_op(): void
    {
        $training = $this->training();

        $this->actingAs($this->trainer)
            ->patch("/trainings/{$training->id}/attendance/{$this->speler->id}", ['status' => AttendanceStatus::Absent->value]);

        $this->assertSame(0, $this->speler->fresh()->xp);
    }

    public function test_een_rapport_levert_basis_xp_op(): void
    {
        $this->rapporteer(7);

        $this->assertSame(5, $this->speler->fresh()->xp);
    }

    public function test_groei_levert_extra_xp_op(): void
    {
        $this->rapporteer(6);   // kaart: 60
        $this->rapporteer(9);   // kaart: gemiddelde van 60 en 90 = 75 → +15 punten

        $speler = $this->speler->fresh();

        // Twee rapporten (2 × 5) plus 15 punten groei × 2 = 30, plafond 30.
        $this->assertSame(40, $speler->xp);
        $this->assertSame(30, XpEvent::where('source', 'growth')->sum('points'));
    }

    public function test_achteruitgang_kost_geen_xp(): void
    {
        $this->rapporteer(9);
        $this->rapporteer(5);

        // Twee rapporten, geen groei, geen minpunten.
        $this->assertSame(10, $this->speler->fresh()->xp);
        $this->assertSame(0, XpEvent::where('points', '<', 0)->count());
    }

    public function test_de_groei_heeft_een_plafond(): void
    {
        $settings = RatingSettings::for($this->school);

        $xp = $this->engine->xpForReport(40, 90, $settings);

        // 50 punten × 2 = 100, maar het plafond is 30.
        $this->assertSame(30, $xp['growth']);
        $this->assertSame(35, $xp['total']);
    }

    // ---------------------------------------------------------------
    // Levels
    // ---------------------------------------------------------------

    public function test_levels_volgen_de_xp(): void
    {
        $settings = RatingSettings::for($this->school);

        $this->assertSame('brons', $this->engine->level(0, null, $settings)['key']);
        $this->assertSame('brons', $this->engine->level(250, 90, $settings)['key']);
        $this->assertSame('zilver', $this->engine->level(251, null, $settings)['key']);
        $this->assertSame('goud', $this->engine->level(501, null, $settings)['key']);
        $this->assertSame('elite', $this->engine->level(751, null, $settings)['key']);
    }

    public function test_het_volgende_level_zegt_hoeveel_er_nog_nodig_is(): void
    {
        $settings = RatingSettings::for($this->school);

        $volgende = $this->engine->nextLevel(221, $settings);
        $this->assertSame('zilver', $volgende['key']);
        $this->assertSame(30, $volgende['remaining']);

        // Op het hoogste level is er geen "nog 0 tot".
        $this->assertNull($this->engine->nextLevel(900, $settings));
    }

    public function test_een_school_kan_een_minimale_rating_per_level_eisen(): void
    {
        $this->school->update(['rating_settings' => [
            'levels' => [
                ['key' => 'brons', 'label' => 'Brons', 'xp' => 0, 'min_rating' => null],
                ['key' => 'zilver', 'label' => 'Zilver', 'xp' => 150, 'min_rating' => null],
                ['key' => 'goud', 'label' => 'Goud', 'xp' => 400, 'min_rating' => 70],
                ['key' => 'elite', 'label' => 'Elite', 'xp' => 900, 'min_rating' => 80],
            ],
        ]]);

        $settings = RatingSettings::for($this->school->fresh());

        // Genoeg XP voor goud, maar de rating is er niet: dan zilver, niet brons.
        $this->assertSame('zilver', $this->engine->level(500, 65, $settings)['key']);
        $this->assertSame('goud', $this->engine->level(500, 72, $settings)['key']);
    }

    public function test_instellingen_zijn_per_school_en_vallen_terug_op_de_standaard(): void
    {
        $this->school->update(['rating_settings' => ['xp_attendance' => 25]]);

        $settings = RatingSettings::for($this->school->fresh());

        $this->assertSame(25, $settings->xpForAttendance());
        // Wat niet is ingesteld houdt de standaard.
        $this->assertSame(5, $settings->xpForReport());
        $this->assertSame(3, $settings->reportsInAverage());
    }

    public function test_de_kaart_toont_level_en_voortgang_naar_het_volgende(): void
    {
        $this->rapporteer(7);

        $eigenaar = User::factory()->for($this->school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($eigenaar)
            ->get("/players/{$this->speler->id}/card")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('card.level.key', 'brons')
                ->where('card.level.xp', 5)
                ->where('card.level.next.key', 'zilver')
                ->where('card.level.next.remaining', 246)
                ->where('card.age_category.key', 'O12')
                ->where('card.season', '2026/27')
            );
    }
}
