<?php

namespace Tests\Feature\Platform;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlatformSchoolTest extends TestCase
{
    use RefreshDatabase;

    protected User $beheerder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->beheerder = User::factory()->create(['school_id' => null]);
        $this->beheerder->assignRole(Role::Platformbeheerder->value);
    }

    protected function schoolMet(string $naam, string $slug, int $spelers = 0): School
    {
        $school = School::factory()->create(['name' => $naam, 'slug' => $slug]);

        app(Tenancy::class)->forSchool($school, function () use ($school, $spelers) {
            Player::factory()->count($spelers)->for($school)->create();
        });

        return $school;
    }

    // ---------------------------------------------------------------
    // Het scenario uit de opdracht
    // ---------------------------------------------------------------

    public function test_school_a_ziet_niets_van_school_b_terwijl_de_beheerder_beide_ziet(): void
    {
        $a = $this->schoolMet('Keepersschool Rob', 'keepersschool-rob', spelers: 2);
        $b = $this->schoolMet('Voetbalschool Yoel', 'voetbalschool-yoel', spelers: 3);

        $eigenaarA = User::factory()->for($a)->create();
        $eigenaarA->assignRole(Role::Eigenaar->value);

        $spelerVanB = app(Tenancy::class)->forSchool($b, fn () => Player::query()->first());

        // De eigenaar van A ziet alleen zijn eigen spelers.
        $this->actingAs($eigenaarA)
            ->get('/users')
            ->assertOk()
            ->assertDontSee($spelerVanB->first_name);

        // En komt niet bij een speler van B.
        $this->actingAs($eigenaarA)->get("/players/{$spelerVanB->id}")->assertNotFound();

        // Hij weet niet eens dat de beheeromgeving bestaat.
        $this->actingAs($eigenaarA)->get('/beheer/scholen')->assertNotFound();

        // De platformbeheerder ziet beide scholen mét hun aantallen.
        $this->actingAs($this->beheerder)
            ->get('/beheer/scholen')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('schools', 2)
                ->where('schools', fn ($scholen) => collect($scholen)->firstWhere('name', 'Keepersschool Rob')['players_count'] === 2
                    && collect($scholen)->firstWhere('name', 'Voetbalschool Yoel')['players_count'] === 3)
            );
    }

    // ---------------------------------------------------------------
    // Rol en scoping
    // ---------------------------------------------------------------

    public function test_de_scope_blijft_dicht_buiten_de_beheeromgeving(): void
    {
        $school = $this->schoolMet('School', 'school', spelers: 4);

        // Een platformbeheerder heeft geen school, dus in de gewone app is de
        // scope voor hem fail-closed: hij ziet niets, en dat is de bedoeling.
        $this->actingAs($this->beheerder)->get('/dashboard')->assertRedirect('/beheer/scholen');

        $this->assertSame(4, app(Tenancy::class)->forSchool($school, fn () => Player::count()));
        $this->assertFalse(app(Tenancy::class)->isPlatform());
    }

    public function test_de_platformmodus_blijft_na_afloop_niet_hangen(): void
    {
        $school = $this->schoolMet('School', 'school', spelers: 2);

        $this->assertFalse(app(Tenancy::class)->isPlatform());

        $this->actingAs($this->beheerder)->get('/beheer/scholen')->assertOk();

        // Zonder opruimen bleef de scope open staan in hetzelfde proces, en dan
        // draait alles daarna over alle scholen heen. Juist dat mag niet.
        $this->assertFalse(app(Tenancy::class)->isPlatform());
        $this->assertSame(0, Player::count(), 'De scope hoort na de beheeromgeving weer dicht te staan.');
        $this->assertSame(2, app(Tenancy::class)->forSchool($school, fn () => Player::count()));
    }

    public function test_een_gewone_gebruiker_komt_er_niet_in(): void
    {
        $school = $this->schoolMet('School', 'school');

        foreach ([Role::Eigenaar, Role::Trainer, Role::Ouder, Role::Speler] as $rol) {
            $user = User::factory()->for($school)->create();
            $user->assignRole($rol->value);

            $this->actingAs($user)->get('/beheer/scholen')->assertNotFound();
            $this->actingAs($user)->get('/beheer/scholen/nieuw')->assertNotFound();
            $this->actingAs($user)->post('/beheer/scholen', ['name' => 'X', 'slug' => 'x'])->assertNotFound();
        }
    }

    public function test_wie_niet_ingelogd_is_wordt_naar_de_inlogpagina_gestuurd(): void
    {
        $this->get('/beheer/scholen')->assertRedirect('/login');
    }

    // ---------------------------------------------------------------
    // Scholen aanmaken en beheren
    // ---------------------------------------------------------------

    public function test_de_beheerder_maakt_een_school_met_eigenaar_aan(): void
    {
        Notification::fake();

        $this->actingAs($this->beheerder)
            ->post('/beheer/scholen', [
                'name' => 'Keepersschool Rob',
                'slug' => 'keepersschool-rob',
                'brand_color' => '#7B1FA2',
                'contact_email' => 'info@rob.nl',
                'owner_name' => 'Rob',
                'owner_email' => 'rob@rob.nl',
            ])
            ->assertRedirect();

        $school = School::where('slug', 'keepersschool-rob')->firstOrFail();
        $this->assertTrue($school->is_active);
        $this->assertSame('#7B1FA2', $school->brand_color);

        $eigenaar = User::where('email', 'rob@rob.nl')->firstOrFail();
        $this->assertSame($school->id, $eigenaar->school_id);
        $this->assertTrue($eigenaar->isEigenaar());
    }

    public function test_een_school_zonder_eigenaar_mag_maar_wordt_gemeld(): void
    {
        $this->actingAs($this->beheerder)
            ->post('/beheer/scholen', ['name' => 'Later', 'slug' => 'later'])
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $melding) => str_contains($melding, 'eigenaar'));

        $this->assertDatabaseHas('schools', ['slug' => 'later']);
    }

    public function test_het_adres_moet_uniek_en_bruikbaar_zijn(): void
    {
        $this->schoolMet('Bestaand', 'bestaand');

        $this->actingAs($this->beheerder)
            ->post('/beheer/scholen', ['name' => 'Nieuw', 'slug' => 'bestaand'])
            ->assertSessionHasErrors('slug');

        // Hoofdletters, spaties en punten kunnen niet in een subdomein.
        foreach (['Met Spaties', 'MetHoofdletters', 'met.punt', '-begint-met-streepje'] as $slug) {
            $this->actingAs($this->beheerder)
                ->post('/beheer/scholen', ['name' => 'Nieuw', 'slug' => $slug])
                ->assertSessionHasErrors('slug');
        }
    }

    public function test_een_bestaand_e_mailadres_voor_de_eigenaar_wordt_geweigerd(): void
    {
        $school = $this->schoolMet('School', 'school');
        $bestaand = User::factory()->for($school)->create(['email' => 'al@bezet.nl']);

        $this->actingAs($this->beheerder)
            ->post('/beheer/scholen', [
                'name' => 'Nieuw',
                'slug' => 'nieuw',
                'owner_name' => 'Iemand',
                'owner_email' => $bestaand->email,
            ])
            ->assertSessionHasErrors('owner_email');

        $this->assertDatabaseMissing('schools', ['slug' => 'nieuw']);
    }

    public function test_een_school_bewerken_behoudt_zijn_eigen_adres(): void
    {
        $school = $this->schoolMet('Oud', 'oud');

        $this->actingAs($this->beheerder)
            ->patch("/beheer/scholen/{$school->id}", [
                'name' => 'Nieuwe naam',
                'slug' => 'oud',
                'contact_name' => 'Rob',
            ])
            ->assertRedirect();

        $school->refresh();
        $this->assertSame('Nieuwe naam', $school->name);
        $this->assertSame('Rob', $school->contact_name);
    }

    public function test_een_school_uit_en_weer_aanzetten(): void
    {
        $school = $this->schoolMet('School', 'school');

        $this->actingAs($this->beheerder)->patch("/beheer/scholen/{$school->id}/status");
        $this->assertFalse($school->refresh()->is_active);

        $this->actingAs($this->beheerder)->patch("/beheer/scholen/{$school->id}/status");
        $this->assertTrue($school->refresh()->is_active);
    }

    public function test_een_uitgezette_school_laat_niemand_meer_binnen(): void
    {
        $school = $this->schoolMet('School', 'school');
        $eigenaar = User::factory()->for($school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($this->beheerder)->patch("/beheer/scholen/{$school->id}/status");

        // SetCurrentSchool weigert een inactieve school; dat gold al en blijft.
        $this->actingAs($eigenaar)->get('/dashboard')->assertForbidden();
    }

    public function test_zoeken_en_filteren_op_status(): void
    {
        $this->schoolMet('Keepersschool Rob', 'keepersschool-rob');
        $uit = $this->schoolMet('Voetbalschool Yoel', 'voetbalschool-yoel');
        $uit->update(['is_active' => false]);

        $this->actingAs($this->beheerder)
            ->get('/beheer/scholen?search=keepers')
            ->assertInertia(fn ($page) => $page->has('schools', 1)->where('schools.0.slug', 'keepersschool-rob'));

        $this->actingAs($this->beheerder)
            ->get('/beheer/scholen?status=inactive')
            ->assertInertia(fn ($page) => $page->has('schools', 1)->where('schools.0.slug', 'voetbalschool-yoel'));

        $this->actingAs($this->beheerder)
            ->get('/beheer/scholen')
            ->assertInertia(fn ($page) => $page->has('schools', 2)->where('totals.active', 1));
    }

    public function test_de_detailpagina_toont_de_eigenaren_en_de_aantallen(): void
    {
        $school = $this->schoolMet('School', 'school', spelers: 3);

        $eigenaar = User::factory()->for($school)->create(['name' => 'Rob']);
        $eigenaar->assignRole(Role::Eigenaar->value);

        $trainer = User::factory()->for($school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($this->beheerder)
            ->get("/beheer/scholen/{$school->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('school.players_count', 3)
                ->where('school.trainers_count', 1)
                ->where('school.users_count', 2)
                ->has('owners', 1)
                ->where('owners.0.name', 'Rob')
            );
    }

    public function test_het_commando_maakt_een_beheerder_zonder_school(): void
    {
        $this->artisan('platform:create-admin', [
            '--name' => 'Michael',
            '--email' => 'michael@playerpath.test',
            '--password' => 'een-heel-lang-wachtwoord',
        ])->assertSuccessful();

        $beheerder = User::where('email', 'michael@playerpath.test')->firstOrFail();

        $this->assertNull($beheerder->school_id);
        $this->assertTrue($beheerder->isPlatformbeheerder());
    }
}
