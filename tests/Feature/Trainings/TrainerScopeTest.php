<?php

namespace Tests\Feature\Trainings;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use App\Support\Trainers\TrainerScope;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Een trainer ziet zijn eigen werk: de groepen waar hij voor staat en de
 * spelers daarin. Niet het hele ledenbestand, en niets van het bedrijf.
 *
 * Wat hier echt toe doet: het is server-side, in de policies en de lijsten -
 * niet alleen in het menu. Een URL intypen helpt niet.
 */
class TrainerScopeTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $trainer;

    protected Group $eigen;

    protected Group $andere;

    protected Player $eigenSpeler;

    protected Player $andereSpeler;

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

        $this->eigen = Group::factory()->for($this->school)->create(['name' => 'Keepers O12']);
        $this->andere = Group::factory()->for($this->school)->create(['name' => 'Veld O14']);

        $this->eigenSpeler = Player::factory()->for($this->school)->keeper()->create(['first_name' => 'Sem']);
        $this->eigen->players()->attach($this->eigenSpeler->id);

        $this->andereSpeler = Player::factory()->for($this->school)->keeper()->create(['first_name' => 'Noud']);
        $this->andere->players()->attach($this->andereSpeler->id);
    }

    /** Koppel de trainer aan een training van zijn eigen groep. */
    protected function koppel(): void
    {
        Training::factory()->for($this->school)->for($this->eigen)->create([
            'starts_at' => now()->addDay()->setTime(18, 0),
            'ends_at' => now()->addDay()->setTime(19, 0),
        ])->trainers()->attach($this->trainer->id);

        app(TrainerScope::class)->forget();
    }

    /** @return array<string, float> */
    protected function cijfers(): array
    {
        return collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => 7.0])
            ->all();
    }

    public function test_een_gekoppelde_trainer_ziet_alleen_zijn_eigen_spelers(): void
    {
        $this->koppel();

        $this->actingAs($this->trainer)
            ->get('/clients')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isTrainer', true)
                ->where('players', function ($spelers) {
                    $this->assertSame([$this->eigenSpeler->id], collect($spelers)->pluck('id')->all());

                    return true;
                }));

        $this->actingAs($this->trainer)
            ->get('/reports')
            ->assertInertia(fn ($page) => $page->where('players', fn ($spelers) => collect($spelers)->pluck('id')->all() === [$this->eigenSpeler->id]));

        $this->actingAs($this->trainer)
            ->get('/groups')
            ->assertInertia(fn ($page) => $page->where('groups', fn ($groepen) => collect($groepen)->pluck('id')->all() === [$this->eigen->id]));
    }

    public function test_de_url_van_een_andere_speler_intypen_helpt_niet(): void
    {
        $this->koppel();

        $this->actingAs($this->trainer)->get('/players/'.$this->andereSpeler->id)->assertForbidden();
        $this->actingAs($this->trainer)->get('/players/'.$this->andereSpeler->id.'/reports/create')->assertForbidden();
        $this->actingAs($this->trainer)
            ->post('/players/'.$this->andereSpeler->id.'/reports', ['scores' => $this->cijfers()])
            ->assertForbidden();

        // Zijn eigen speler wel.
        $this->actingAs($this->trainer)->get('/players/'.$this->eigenSpeler->id)->assertOk();
        $this->actingAs($this->trainer)
            ->post('/players/'.$this->eigenSpeler->id.'/reports', ['scores' => $this->cijfers()])
            ->assertRedirect();
    }

    /**
     * Koppelen is bij veel scholen niet gebruikelijk. Een trainer die nergens
     * bij staat en na het inloggen nul spelers ziet, denkt dat het stuk is.
     */
    /**
     * Het rooster is schoolbreed te zien, maar afvinken doet een gekoppelde
     * trainer alleen bij zijn eigen trainingen en groepen.
     */
    public function test_een_gekoppelde_trainer_vinkt_alleen_zijn_eigen_trainingen_af(): void
    {
        $this->koppel();

        $andere = Training::factory()->for($this->school)->for($this->andere)->create([
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
        ]);

        $this->actingAs($this->trainer)
            ->get('/trainings/'.$andere->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.record', false)->count('players', 0));

        $this->actingAs($this->trainer)
            ->patch('/trainings/'.$andere->id.'/attendance/'.$this->andereSpeler->id, ['status' => 'present'])
            ->assertForbidden();

        // Een training van zijn eigen groep zonder gekoppelde trainer: wel.
        $eigen = Training::factory()->for($this->school)->for($this->eigen)->create([
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
        ]);

        $this->actingAs($this->trainer)
            ->get('/trainings/'.$eigen->id)
            ->assertInertia(fn ($page) => $page
                ->where('can.record', true)
                ->where('players', fn ($spelers) => collect($spelers)->pluck('id')->all() === [$this->eigenSpeler->id]));

        $this->actingAs($this->trainer)
            ->patch('/trainings/'.$eigen->id.'/attendance/'.$this->eigenSpeler->id, ['status' => 'present'])
            ->assertRedirect();
    }

    /** Afvinken bij een training van zijn groep toont alleen spelers die van hem zijn. */
    public function test_de_afvinklijst_toont_alleen_zijn_eigen_spelers(): void
    {
        $this->koppel();

        // Een speler van de andere groep die los op zijn training is ingeschreven.
        $training = Training::query()->where('group_id', $this->eigen->id)->firstOrFail();
        $training->enrollments()->create([
            'player_id' => $this->andereSpeler->id,
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->eigenaar)
            ->get('/trainings/'.$training->id)
            ->assertInertia(fn ($page) => $page->count('players', 2));

        $this->actingAs($this->trainer)
            ->get('/trainings/'.$training->id)
            ->assertInertia(fn ($page) => $page->where('players', fn ($spelers) => collect($spelers)->pluck('id')->all() === [$this->eigenSpeler->id]));
    }

    /** Het aandacht-blok van een trainer noemt alleen zijn eigen spelers. */
    public function test_het_aandachtblok_noemt_alleen_zijn_eigen_spelers(): void
    {
        $this->koppel();

        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('attention', function ($items) {
                    $stil = collect($items)->firstWhere('key', 'silent_players');

                    $this->assertNotNull($stil);
                    $this->assertStringContainsString($this->eigenSpeler->full_name, $stil['title']);
                    // Hoe stil de rest van de school is, gaat een trainer niet aan.
                    $this->assertNull(collect($items)->firstWhere('key', 'quiet_trainers'));

                    return true;
                }));
    }

    public function test_nergens_gekoppeld_betekent_de_hele_school(): void
    {
        $this->actingAs($this->trainer)
            ->get('/clients')
            ->assertInertia(fn ($page) => $page->count('players', 2));

        $this->actingAs($this->trainer)->get('/players/'.$this->andereSpeler->id)->assertOk();
    }

    public function test_de_eigenaar_ziet_altijd_alles(): void
    {
        Training::factory()->for($this->school)->for($this->eigen)->create()->trainers()->attach($this->eigenaar->id);

        $this->actingAs($this->eigenaar)
            ->get('/clients')
            ->assertInertia(fn ($page) => $page->count('players', 2)->where('isTrainer', false));
    }

    public function test_een_trainer_krijgt_de_ouders_niet_mee(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $this->eigenSpeler->guardians()->attach($ouder->id);

        $this->actingAs($this->trainer)
            ->get('/clients')
            ->assertInertia(fn ($page) => $page->where('players.0.guardians', []));
    }

    public function test_het_bedrijf_zit_voor_een_trainer_server_side_dicht(): void
    {
        foreach (['/staff', '/locaties', '/mijlpalen', '/announcements', '/personeel/beschikbaarheid', '/payments', '/subscriptions', '/aanbod', '/exports', '/branding', '/verantwoording', '/instellingen/inschrijven', '/players/create', '/groups/create'] as $pad) {
            $this->actingAs($this->trainer)->get($pad)->assertForbidden("{$pad} hoort dicht te zitten voor een trainer");
        }
    }

    public function test_het_menu_van_een_trainer_is_klein(): void
    {
        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('nav', function ($nav) {
                $hrefs = $this->navHrefs($nav);

                foreach (['/trainings/mijn', '/clients', '/reports', '/verjaardagen', '/beschikbaarheid', '/settings/profile'] as $pad) {
                    $this->assertContains($pad, $hrefs);
                }

                foreach (['/staff', '/locaties', '/payments', '/announcements', '/mijlpalen', '/groups', '/enrollments', '/branding'] as $pad) {
                    $this->assertNotContains($pad, $hrefs);
                }

                return true;
            }));
    }

    public function test_verjaardagen_alleen_van_zijn_eigen_spelers(): void
    {
        $this->koppel();

        $this->eigenSpeler->update(['date_of_birth' => now()->subYears(10)->addDays(3)->toDateString()]);
        $this->andereSpeler->update(['date_of_birth' => now()->subYears(11)->addDays(5)->toDateString()]);

        $this->actingAs($this->trainer)
            ->get('/verjaardagen')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('players/Birthdays')
                ->count('birthdays', 1)
                ->where('birthdays.0.first_name', 'Sem')
                ->where('birthdays.0.turns', 10));

        // Op zijn dashboard staan de verjaardagen ook, en niet de kerncijfers.
        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('widgets.kpi_players', null)
                ->where('widgets.birthdays', fn ($rijen) => count($rijen) === 1));
    }
}
