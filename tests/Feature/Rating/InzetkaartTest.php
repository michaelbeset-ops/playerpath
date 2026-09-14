<?php

namespace Tests\Feature\Rating;

use App\Actions\Schools\ChangeCardMode;
use App\Enums\ParticipationStatus;
use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\CourseAssessment;
use App\Models\EffortRating;
use App\Models\Group;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\PlayerCard\PlayerProgress;
use App\Support\Progress\CourseProgress;
use App\Support\Rating\RatingEngine;
use App\Support\Rating\RatingSettings;
use App\Support\Tenancy\Tenancy;
use App\Support\Trainings\ReportPrompts;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * De inzetkaart (variant B): punten voor inzet, geen cijfers, en voortgang per
 * cursus in kleuren.
 *
 * Wat hier vastligt: de school kiest de kaart, de trainer geeft na de training
 * inzet en de kaart groeit, punten gaan nooit omlaag door een mindere dag, de
 * kaart bevat geen rating (ook niet onzichtbaar in de gegevens), en de rest van
 * het systeem - herinnering, voortgang, mijlpalen, exports - beweegt mee.
 */
class InzetkaartTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $trainer;

    protected User $ouder;

    protected Group $groep;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        $this->groep = Group::factory()->for($this->school)->create(['name' => 'Keepers O12']);

        $this->speler = Player::factory()->for($this->school)->keeper()->create(['first_name' => 'Sem']);
        $this->groep->players()->attach($this->speler->id);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);
        $this->speler->guardians()->attach($this->ouder->id, ['relationship' => 'moeder']);

        $this->travelTo('2026-09-20 12:00:00');

        app(ChangeCardMode::class)->handle($this->school, RatingSettings::INZET);
    }

    protected function training(string $dag): Training
    {
        return Training::factory()->for($this->school)->for($this->groep)->create([
            'starts_at' => now()->parse($dag.' 18:00:00'),
            'ends_at' => now()->parse($dag.' 19:00:00'),
        ]);
    }

    /** @param array<string, mixed> $data */
    protected function geefInzet(Training $training, array $data, ?User $door = null): TestResponse
    {
        return $this->actingAs($door ?? $this->trainer)->post("/trainings/{$training->id}/inzet/{$this->speler->id}", $data);
    }

    public function test_zonder_keuze_blijft_de_prestatiekaart_en_de_wizard_raadt_de_inzetkaart_aan(): void
    {
        $nieuw = School::factory()->create();
        $this->assertSame(RatingSettings::PRESTATIE, RatingSettings::for($nieuw)->cardMode());

        $eigenaar = User::factory()->for($nieuw)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);
        app(Tenancy::class)->set($nieuw);

        $this->actingAs($eigenaar)
            ->get('/instellingen/inschrijven/stap/8')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('enrollment-settings/Wizard')
                ->where('steps.7.key', 'kaart')
                ->where('cardMode', 'inzet')
            );

        $this->actingAs($eigenaar)->patch('/instellingen/inschrijven/stap/8', ['card_mode' => 'sterren'])->assertSessionHasErrors('card_mode');

        $this->actingAs($eigenaar)
            ->patch('/instellingen/inschrijven/stap/8', ['card_mode' => 'inzet'])
            ->assertRedirect('/instellingen/inschrijven/stap/9');

        $this->assertSame(RatingSettings::INZET, RatingSettings::for($nieuw->refresh())->cardMode());
    }

    public function test_de_trainer_geeft_inzet_en_de_kaart_groeit(): void
    {
        $training = $this->training('2026-09-19');

        $this->actingAs($this->trainer)
            ->get("/trainings/{$training->id}/inzet")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('trainings/EffortQuick')
                ->where('player.id', $this->speler->id)
                ->where('current.present', true)
                ->count('effortLevels', 3)
                ->count('attitudeLevels', 3)
                ->where('attendancePoints', 10)
            );

        $this->geefInzet($training, ['present' => true, 'effort' => 'n2', 'attitude' => 'n3', 'note' => 'Bleef positief.'])
            ->assertRedirect("/trainings/{$training->id}/inzet/klaar");

        // Aanwezig 10, hard gewerkt 10, voorbeeld voor de groep 15.
        $this->assertSame(35, $this->speler->refresh()->xp);
        $this->assertDatabaseHas('effort_ratings', [
            'training_id' => $training->id,
            'player_id' => $this->speler->id,
            'effort' => 'n2',
            'attitude' => 'n3',
            'effort_points' => 10,
            'attitude_points' => 15,
            'note' => 'Bleef positief.',
        ]);
        $this->assertDatabaseHas('attendances', ['training_id' => $training->id, 'player_id' => $this->speler->id, 'status' => 'present']);

        $this->actingAs($this->trainer)
            ->get("/trainings/{$training->id}/inzet/klaar")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('trainings/EffortSummary')
                ->where('results.0.points', 35)
                ->where('results.0.labels', ['Hard gewerkt', 'Voorbeeld voor de groep'])
                ->count('open', 0)
            );

        // Opnieuw opslaan vervangt de keuze en telt niet dubbel.
        $this->geefInzet($training, ['present' => true, 'effort' => 'n1']);
        $this->assertSame(15, $this->speler->refresh()->xp);
        $this->assertSame(1, EffortRating::count());

        // Toch afwezig: de punten van die training gaan mee terug.
        $this->geefInzet($training, ['present' => false]);
        $this->assertSame(0, $this->speler->refresh()->xp);
        $this->assertSame(0, EffortRating::count());
    }

    public function test_punten_gaan_nooit_omlaag_door_een_mindere_training(): void
    {
        $this->geefInzet($this->training('2026-09-17'), ['present' => true, 'effort' => 'n3', 'attitude' => 'n3']);
        $na = $this->speler->refresh()->xp;

        // Een mindere dag: niets aangetikt. Dan komt er alleen iets bij.
        $this->geefInzet($this->training('2026-09-18'), ['present' => true]);
        $this->assertSame($na + 10, $this->speler->refresh()->xp);

        // Een negatieve keuze bestaat niet.
        $this->geefInzet($this->training('2026-09-19'), ['present' => true, 'effort' => 'min'])->assertSessionHasErrors('effort');
    }

    public function test_de_kaart_toont_level_en_punten_maar_geen_cijfers(): void
    {
        // Eerst een rapport, zoals een school met de prestatiekaart dat deed.
        app(ChangeCardMode::class)->handle($this->school, RatingSettings::PRESTATIE);
        $scores = collect(ReportCategory::forPosition(PlayerPosition::Keeper))->mapWithKeys(fn (ReportCategory $c) => [$c->value => 8])->all();
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/reports", ['scores' => $scores])->assertRedirect();
        $this->assertSame(5, $this->speler->refresh()->xp);

        // Overstappen: iedereen gelijk, rapportpunten tellen niet mee.
        app(ChangeCardMode::class)->handle($this->school, RatingSettings::INZET);
        $this->assertSame(0, $this->speler->refresh()->xp);

        $this->geefInzet($this->training('2026-09-19'), ['present' => true, 'effort' => 'n3', 'attitude' => 'n2', 'note' => 'Top gedaan']);

        $this->actingAs($this->ouder)
            ->get("/players/{$this->speler->id}/card")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('card.card_mode', 'inzet')
                // Geen rating op de kaart, ook niet onzichtbaar in de gegevens.
                ->where('card.overall', null)
                ->where('card.grade', null)
                ->count('card.categories', 0)
                ->where('card.recent_reports', null)
                ->where('card.effort.points', 35)
                ->where('card.effort.trainings', 1)
                ->where('card.effort.standouts', 1)
                ->where('card.recent_trainings.0.effort', 'Uitblinker')
                ->where('card.recent_trainings.0.note', 'Top gedaan')
                ->where('card.level.key', 'brons')
            );

        // Terug naar de prestatiekaart: de rapportpunten tellen weer, de inzet niet.
        app(ChangeCardMode::class)->handle($this->school, RatingSettings::PRESTATIE);
        $this->assertSame(15, $this->speler->refresh()->xp);
    }

    public function test_level_en_mijlpalen_groeien_mee_met_inzet(): void
    {
        foreach (range(1, 7) as $dag) {
            $this->geefInzet($this->training(sprintf('2026-09-%02d', $dag)), ['present' => true, 'effort' => 'n3', 'attitude' => 'n3']);
        }

        $speler = $this->speler->refresh();

        // Zeven trainingen vol inzet: 7 x 40 = 280, en dat is zilver.
        $this->assertSame(280, $speler->xp);
        $this->assertSame('zilver', app(RatingEngine::class)->levelState($speler)['key']);

        $mijlpalen = collect(app(PlayerBadges::class)->for($speler, app(PlayerProgress::class)));
        $behaald = $mijlpalen->where('earned', true)->pluck('key')->all();

        foreach (['eerste_inzet', 'aanwezig_vijf', 'doorzetter', 'luisteraar', 'topinzet'] as $key) {
            $this->assertContains($key, $behaald);
        }

        // Geen mijlpalen over rapporten bij de inzetkaart.
        $this->assertNotContains('eerste_rapport', $mijlpalen->pluck('key')->all());

        // De tijdlijn noemt het uitblinken, niet een rapport.
        $this->actingAs($this->ouder)
            ->get("/players/{$this->speler->id}/progress")
            ->assertInertia(fn ($page) => $page->where('timeline', fn ($items) => collect($items)->contains('type', 'inzet') && ! collect($items)->contains('type', 'rapport')));
    }

    public function test_de_rapportflow_en_de_herinnering_wijzen_naar_inzet(): void
    {
        $training = Training::factory()->for($this->school)->for($this->groep)->create([
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($this->trainer)->get("/trainings/{$training->id}/rapporten")->assertRedirect("/trainings/{$training->id}/inzet");

        $herinnering = app(ReportPrompts::class)->for($this->trainer);
        $this->assertSame("/trainings/{$training->id}/inzet", $herinnering[0]['href']);
        $this->assertSame('inzet', $herinnering[0]['mode']);
        $this->assertSame(1, $herinnering[0]['open']);

        $this->geefInzet($training, ['present' => true, 'effort' => 'n3']);

        $this->assertSame([], app(ReportPrompts::class)->for($this->trainer->fresh()));
    }

    public function test_alleen_de_school_geeft_inzet(): void
    {
        $training = $this->training('2026-09-19');

        $this->actingAs($this->ouder)->get("/trainings/{$training->id}/inzet")->assertForbidden();
        $this->geefInzet($training, ['present' => true, 'effort' => 'n1'], $this->ouder)->assertForbidden();

        $andereSchool = School::factory()->create();
        $vreemde = User::factory()->for($andereSchool)->create();
        $vreemde->assignRole(Role::Trainer->value);

        $status = $this->geefInzet($training, ['present' => true, 'effort' => 'n1'], $vreemde)->status();
        $this->assertContains($status, [403, 404]);

        app(Tenancy::class)->set($this->school);
        $this->assertSame(0, EffortRating::count());
    }

    public function test_begin_en_eindniveau_per_cursus_zonder_vergelijken(): void
    {
        $product = Product::factory()->for($this->school)->blok()->create(['name' => 'Keepersblok najaar']);
        $product->participations()->create(['player_id' => $this->speler->id, 'status' => ParticipationStatus::Confirmed]);

        $this->actingAs($this->trainer)
            ->get("/aanbod/{$product->id}/voortgang")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('offerings/CourseProgress')
                ->where('moment', 'begin')
                ->where('player.id', $this->speler->id)
                ->count('levels', 4)
                ->count('categories', 6)
            );

        $categorieen = collect(ReportCategory::forPosition(PlayerPosition::Keeper))->map(fn (ReportCategory $c) => $c->value);
        $url = "/aanbod/{$product->id}/voortgang/{$this->speler->id}";

        // Leeg opslaan kan niet, een niveau buiten de schaal ook niet.
        $this->actingAs($this->trainer)->post($url, ['moment' => 'begin', 'levels' => []])->assertSessionHasErrors('levels');
        $this->actingAs($this->trainer)->post($url, ['moment' => 'begin', 'levels' => [$categorieen->first() => 9]])->assertSessionHasErrors('levels.'.$categorieen->first());

        $this->actingAs($this->trainer)->post($url, ['moment' => 'begin', 'levels' => $categorieen->mapWithKeys(fn ($c) => [$c => 1])->all()])->assertRedirect();
        $this->actingAs($this->trainer)
            ->post($url, ['moment' => 'eind', 'levels' => $categorieen->mapWithKeys(fn ($c) => [$c => 2])->all(), 'note' => 'Veel zekerder bij hoge ballen.'])
            ->assertRedirect();

        $this->assertSame(2, CourseAssessment::count());

        // Een ouder legt zelf niets vast.
        $this->actingAs($this->ouder)->post($url, ['moment' => 'eind', 'levels' => [$categorieen->first() => 3]])->assertForbidden();

        // De ouder ziet de eigen lijn: van "Op weg" naar "Goed", met het verslag.
        $this->actingAs($this->ouder)
            ->get("/players/{$this->speler->id}/progress")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('players/Progress')
                ->count('courses', 1)
                ->where('courses.0.product.name', 'Keepersblok najaar')
                ->where('courses.0.categories.0.begin.label', 'Op weg')
                ->where('courses.0.categories.0.eind.label', 'Goed')
                ->where('courses.0.eind_note', 'Veel zekerder bij hoge ballen.')
                ->where('nextStep', null)
            );

        // Een school die naar vijf niveaus gaat: het oude niveau wordt omgerekend.
        $vijf = [
            ['key' => 'n1', 'label' => 'Werkpunt', 'color' => 'rood'],
            ['key' => 'n2', 'label' => 'Op weg', 'color' => 'oranje'],
            ['key' => 'n3', 'label' => 'Goed', 'color' => 'geel'],
            ['key' => 'n4', 'label' => 'Sterk', 'color' => 'groen'],
            ['key' => 'n5', 'label' => 'Top', 'color' => 'blauw'],
        ];
        $this->assertSame(3, CourseProgress::level(2, 4, $vijf)['index']);
        $this->assertSame(0, CourseProgress::level(0, 4, $vijf)['index']);

        // Een aanbod zonder begin en eind (doorlopend) heeft geen cursusniveaus.
        $doorlopend = Product::factory()->for($this->school)->create();
        $this->actingAs($this->trainer)->get("/aanbod/{$doorlopend->id}/voortgang")->assertNotFound();
    }

    public function test_de_overzichten_bewegen_mee_met_de_kaart(): void
    {
        $this->geefInzet($this->training('2026-09-19'), ['present' => true, 'effort' => 'n2', 'note' => 'Goed bezig']);

        $this->actingAs($this->eigenaar)
            ->get('/exports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('exports', 6)
                ->where('exports.3.key', 'inzet')
                ->where('exports.4.key', 'cursusvoortgang')
            );

        // Rapportcijfers bestaan niet bij de inzetkaart.
        $this->actingAs($this->eigenaar)->get('/exports/reports')->assertNotFound();

        $inhoud = $this->actingAs($this->eigenaar)->get('/exports/inzet?format=csv')->assertOk()->streamedContent();

        $this->assertStringContainsString('Sem', $inhoud);
        // Hard gewerkt, geen houding, 10 + 10 punten.
        $this->assertStringContainsString('"Hard gewerkt";;20;', $inhoud);

        $this->actingAs($this->eigenaar)->get('/exports/cursusvoortgang?format=csv')->assertOk();
    }

    public function test_de_eigenaar_wisselt_de_kaart_en_stelt_de_punten_in(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/instellingen/spelerskaart')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('schools/CardSettings')->where('mode', 'inzet')->count('effortLevels', 3));

        $this->actingAs($this->trainer)->get('/instellingen/spelerskaart')->assertForbidden();
        $this->actingAs($this->trainer)->post('/instellingen/spelerskaart/variant', ['mode' => 'prestatie'])->assertForbidden();

        $instellingen = [
            'grading' => 'cijfers',
            'attendance_points' => 12,
            'effort_levels' => [
                ['label' => 'Goed bezig', 'points' => 5],
                ['label' => 'Hard gewerkt', 'points' => 10],
                ['label' => 'Uitblinker', 'points' => 15],
                ['label' => 'Superster', 'points' => 20],
            ],
            'attitude_levels' => [['label' => 'Luistert', 'points' => 5], ['label' => 'Top', 'points' => 10], ['label' => 'Voorbeeld', 'points' => 15]],
            'progress_levels' => [['label' => 'Nog niet', 'color' => 'rood'], ['label' => 'Bijna', 'color' => 'geel'], ['label' => 'Kan het', 'color' => 'groen']],
        ];

        $this->actingAs($this->eigenaar)->patch('/instellingen/spelerskaart', $instellingen)->assertSessionHasNoErrors();

        $opgeslagen = RatingSettings::for($this->school->refresh());
        $this->assertSame(12, $opgeslagen->xpForAttendance());
        $this->assertSame(['n1', 'n2', 'n3', 'n4'], array_column($opgeslagen->effortLevels(), 'key'));
        $this->assertSame(20, $opgeslagen->effortLevel('n4')['points']);
        $this->assertSame(['Nog niet', 'Bijna', 'Kan het'], array_column($opgeslagen->progressLevels(), 'label'));

        // Twee treden is te weinig, een negatieve waarde bestaat niet.
        $this->actingAs($this->eigenaar)
            ->patch('/instellingen/spelerskaart', [...$instellingen, 'effort_levels' => [['label' => 'A', 'points' => 5], ['label' => 'B', 'points' => -5]]])
            ->assertSessionHasErrors(['effort_levels', 'effort_levels.1.points']);

        $this->actingAs($this->eigenaar)->post('/instellingen/spelerskaart/variant', ['mode' => 'prestatie'])->assertSessionHas('status');
        $this->assertSame(RatingSettings::PRESTATIE, RatingSettings::for($this->school->refresh())->cardMode());
    }
}
