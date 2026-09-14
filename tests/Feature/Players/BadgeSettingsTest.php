<?php

namespace Tests\Feature\Players;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\PlayerCard\BadgeSettings;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\PlayerCard\PlayerProgress;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mijlpalen: standaard vier, instelbaar voor alle spelers of per
 * leeftijdscategorie, en de kaart toont alleen wat er geldt.
 */
class BadgeSettingsTest extends TestCase
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

    public function test_standaard_gelden_er_vier_mijlpalen(): void
    {
        $speler = Player::factory()->for($this->school)->create();

        $badges = app(PlayerBadges::class)->for($speler, app(PlayerProgress::class));

        $this->assertCount(4, $badges);
        $this->assertSame(['eerste_rapport', 'groei', 'doel_gehaald', 'aanwezig_vijf'], array_column($badges, 'key'));
        // Vijftien bestaan er; bij de prestatiekaart gaan er negen over rapporten en aanwezigheid.
        $this->assertCount(15, PlayerBadges::catalogue());
        $this->assertCount(9, PlayerBadges::catalogueFor('prestatie'));
    }

    public function test_de_eigenaar_kiest_de_mijlpalen_voor_iedereen_en_per_categorie(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/mijlpalen')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('badges/Edit')->count('catalogue', 9)->count('defaultKeys', 4));

        $this->actingAs($this->eigenaar)->patch('/mijlpalen', [
            'default' => ['eerste_rapport', 'uitblinker'],
            'overrides' => ['O10' => ['aanwezig_vijf'], 'O14' => []],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $instellingen = BadgeSettings::for($this->school->refresh());

        $this->assertSame(['eerste_rapport', 'uitblinker'], $instellingen->defaultKeys());
        $this->assertSame(['aanwezig_vijf'], $instellingen->keysFor('O10'));
        // Leeg is geen afwijking: O14 volgt de standaard.
        $this->assertSame(['eerste_rapport', 'uitblinker'], $instellingen->keysFor('O14'));
        $this->assertSame(['eerste_rapport', 'uitblinker'], $instellingen->keysFor(null));

        // En de kaart volgt het, per categorie van de speler.
        $o10 = Player::factory()->for($this->school)->create(['date_of_birth' => now()->subYears(9)->toDateString()]);
        $o10->forceFill(['age_category' => 'O10'])->save();
        $ouder = Player::factory()->for($this->school)->create(['date_of_birth' => now()->subYears(13)->toDateString()]);
        $ouder->forceFill(['age_category' => 'O14'])->save();

        $this->assertSame(['aanwezig_vijf'], array_column(app(PlayerBadges::class)->for($o10, app(PlayerProgress::class)), 'key'));
        $this->assertSame(['eerste_rapport', 'uitblinker'], array_column(app(PlayerBadges::class)->for($ouder, app(PlayerProgress::class)), 'key'));
    }

    public function test_onbekende_sleutels_en_een_lege_lijst_worden_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)->patch('/mijlpalen', ['default' => ['bestaat_niet']])->assertSessionHasErrors('default.0');
        $this->actingAs($this->eigenaar)->patch('/mijlpalen', ['default' => []])->assertSessionHasErrors('default');
    }

    /** Welke mijlpalen gelden is een schoolbrede instelling: van de eigenaar. */
    public function test_een_trainer_en_een_ouder_mogen_niet(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $this->actingAs($trainer)->get('/mijlpalen')->assertForbidden();
        $this->actingAs($ouder)->get('/mijlpalen')->assertForbidden();
    }

    /**
     * Eigen mijlpalen: de school typt ze zelf, en een trainer kent ze toe.
     * Wat niet af te leiden is moet iemand met de hand zetten.
     */
    public function test_de_eigenaar_bedenkt_een_eigen_mijlpaal_en_die_houdt_zijn_sleutel(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/mijlpalen', [
                'default' => ['eerste_rapport'],
                'custom' => [['key' => null, 'label' => 'Eerste wedstrijd gekeept', 'description' => 'Een hele wedstrijd in het doel']],
            ])
            ->assertSessionHas('status');

        $eigen = BadgeSettings::for($this->school->refresh())->customBadges();

        $this->assertCount(1, $eigen);
        $this->assertSame('Eerste wedstrijd gekeept', $eigen[0]['label']);
        $this->assertStringStartsWith('eigen_', $eigen[0]['key']);

        // Hernoemen met dezelfde sleutel: de sleutel blijft, dus een toekenning ook.
        $this->actingAs($this->eigenaar)->patch('/mijlpalen', [
            'default' => ['eerste_rapport'],
            'custom' => [['key' => $eigen[0]['key'], 'label' => 'Eerste wedstrijd', 'description' => '']],
        ]);

        $this->assertSame($eigen[0]['key'], BadgeSettings::for($this->school->refresh())->customBadges()[0]['key']);
    }

    public function test_een_eigen_mijlpaal_zonder_naam_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->from('/mijlpalen')
            ->patch('/mijlpalen', ['default' => ['eerste_rapport'], 'custom' => [['key' => null, 'label' => '', 'description' => 'x']]])
            ->assertSessionHasErrors('custom.0.label');
    }

    public function test_een_trainer_kent_een_eigen_mijlpaal_toe_en_die_staat_op_de_kaart(): void
    {
        BadgeSettings::save($this->school, ['eerste_rapport'], [], [['label' => 'Strafschop gestopt', 'description' => '']]);
        $key = BadgeSettings::for($this->school->refresh())->customBadges()[0]['key'];

        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $speler = Player::factory()->for($this->school)->keeper()->create();

        $this->actingAs($trainer)
            ->post('/players/'.$speler->id.'/mijlpalen/'.$key)
            ->assertSessionHas('status');

        $badges = collect(app(PlayerBadges::class)->for($speler->refresh(), app(PlayerProgress::class)));

        $this->assertTrue($badges->firstWhere('key', $key)['earned']);

        // Intrekken haalt hem weer weg; een onbekende sleutel bestaat niet.
        $this->actingAs($trainer)->delete('/players/'.$speler->id.'/mijlpalen/'.$key)->assertSessionHas('status');
        $this->assertFalse(collect(app(PlayerBadges::class)->for($speler->refresh(), app(PlayerProgress::class)))->firstWhere('key', $key)['earned']);
        $this->actingAs($trainer)->post('/players/'.$speler->id.'/mijlpalen/eigen_bestaatniet')->assertNotFound();
    }

    public function test_een_ouder_kent_geen_mijlpaal_toe(): void
    {
        BadgeSettings::save($this->school, ['eerste_rapport'], [], [['label' => 'Strafschop gestopt']]);
        $key = BadgeSettings::for($this->school->refresh())->customBadges()[0]['key'];

        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $speler = Player::factory()->for($this->school)->create();
        $ouder->children()->attach($speler->id, ['relationship' => 'moeder', 'school_id' => $this->school->id]);

        $this->actingAs($ouder)->post('/players/'.$speler->id.'/mijlpalen/'.$key)->assertForbidden();
    }
}
