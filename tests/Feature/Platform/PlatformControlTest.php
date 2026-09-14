<?php

namespace Tests\Feature\Platform;

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\Impersonation;
use App\Models\Invitation;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Uitnodiging;
use App\Support\Features\Features;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlatformControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $beheerder;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->beheerder = User::factory()->create(['school_id' => null, 'name' => 'Michael']);
        $this->beheerder->assignRole(Role::Platformbeheerder->value);

        $this->school = School::factory()->create(['name' => 'Keepersschool Rob', 'slug' => 'rob']);
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create(['name' => 'Rob']);
        $this->eigenaar->assignRole(Role::Eigenaar->value);
    }

    protected function zetUit(Feature ...$features): void
    {
        $this->school->update([
            'features' => collect(Feature::cases())
                ->mapWithKeys(fn (Feature $f) => [$f->value => ! in_array($f, $features, true)])
                ->all(),
        ]);
    }

    // ---------------------------------------------------------------
    // 4. Features per school
    // ---------------------------------------------------------------

    public function test_alles_staat_standaard_aan(): void
    {
        foreach (Feature::cases() as $feature) {
            $this->assertTrue(Features::enabledFor($this->school, $feature), $feature->value);
        }
    }

    public function test_een_nieuwe_feature_staat_aan_bij_een_school_die_er_niets_van_weet(): void
    {
        // Alleen een oude sleutel opgeslagen: de rest hoort gewoon aan te staan
        // en niet stilzwijgend uit.
        $this->school->update(['features' => ['kalender' => false]]);

        $this->assertFalse(Features::enabledFor($this->school, Feature::Kalender));
        $this->assertTrue(Features::enabledFor($this->school, Feature::Betalingen));
    }

    public function test_een_uitgezette_functie_is_serverside_dicht(): void
    {
        $this->zetUit(Feature::Kalender, Feature::Betalingen, Feature::Exports);

        // Niet alleen uit het menu, maar echt onbereikbaar.
        $this->actingAs($this->eigenaar)->get('/calendar')->assertNotFound();
        $this->actingAs($this->eigenaar)->get('/payments')->assertNotFound();
        $this->actingAs($this->eigenaar)->get('/subscriptions')->assertNotFound();
        $this->actingAs($this->eigenaar)->get('/aanbod')->assertNotFound();
        $this->actingAs($this->eigenaar)->get('/exports')->assertNotFound();

        // Wat aan blijft, blijft gewoon werken.
        $this->actingAs($this->eigenaar)->get('/trainings')->assertOk();
        $this->actingAs($this->eigenaar)->get('/reports')->assertOk();
    }

    public function test_de_ontwikkelingslaag_gaat_in_zijn_geheel_dicht(): void
    {
        $speler = Player::factory()->for($this->school)->create();

        $this->zetUit(Feature::Ontwikkeling);

        $this->actingAs($this->eigenaar)->get('/reports')->assertNotFound();
        $this->actingAs($this->eigenaar)->get("/players/{$speler->id}/reports/create")->assertNotFound();
        $this->actingAs($this->eigenaar)->get("/players/{$speler->id}/card")->assertNotFound();
        $this->actingAs($this->eigenaar)->get("/players/{$speler->id}/progress")->assertNotFound();

        // De ledenadministratie blijft wel bestaan.
        $this->actingAs($this->eigenaar)->get('/clients')->assertOk();
    }

    public function test_het_menu_laat_uitgezette_functies_weg(): void
    {
        $this->zetUit(Feature::Betalingen, Feature::Kalender);

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(function ($page) {
                $hrefs = $this->navHrefs($page->toArray()['props']['nav']);

                $this->assertNotContains('/payments', $hrefs);
                $this->assertNotContains('/calendar', $hrefs);
                $this->assertContains('/trainings', $hrefs);
            });
    }

    public function test_de_achtergrondtaken_slaan_een_uitgezette_school_over(): void
    {
        $speler = Player::factory()->for($this->school)->create();

        Subscription::factory()->for($this->school)->create([
            'player_id' => $speler->id,
            'product_id' => null,
            'starts_on' => now()->subMonth()->toDateString(),
        ]);

        $this->zetUit(Feature::Betalingen);

        $this->artisan('payments:generate')->assertSuccessful();

        // Alleen het scherm verbergen zou betekenen dat er tóch rekeningen
        // ontstaan bij een school die betalingen niet heeft.
        $this->assertSame(0, app(Tenancy::class)->forSchool($this->school, fn () => Payment::count()));
    }

    public function test_de_beheerder_zet_functies_aan_en_uit(): void
    {
        $this->actingAs($this->beheerder)
            ->patch("/beheer/scholen/{$this->school->id}/functies", [
                'features' => collect(Feature::cases())
                    ->mapWithKeys(fn (Feature $f) => [$f->value => $f !== Feature::Exports])
                    ->all(),
            ])
            ->assertRedirect();

        $this->assertFalse(Features::enabledFor($this->school->refresh(), Feature::Exports));
        $this->assertTrue(Features::enabledFor($this->school, Feature::Kalender));
    }

    public function test_een_onvolledige_featurelijst_wordt_geweigerd(): void
    {
        $this->actingAs($this->beheerder)
            ->patch("/beheer/scholen/{$this->school->id}/functies", ['features' => ['kalender' => false]])
            ->assertSessionHasErrors('features.betalingen');
    }

    // ---------------------------------------------------------------
    // 5. Gebruikers beheren
    // ---------------------------------------------------------------

    public function test_de_beheerder_maakt_een_account_voor_een_school(): void
    {
        Notification::fake();

        $this->actingAs($this->beheerder)
            ->post("/beheer/scholen/{$this->school->id}/gebruikers", [
                'name' => 'Nieuwe trainer',
                'email' => 'trainer@rob.nl',
                'role' => 'trainer',
            ])
            ->assertRedirect();

        // Een uitnodiging met een welkomstmail, zichtbaar in de lijst tot hij
        // geactiveerd is.
        $this->assertDatabaseMissing('users', ['email' => 'trainer@rob.nl']);

        $uitnodiging = Invitation::withoutSchoolScope()->where('email', 'trainer@rob.nl')->firstOrFail();
        $this->assertSame($this->school->id, $uitnodiging->school_id);
        $this->assertSame('trainer', $uitnodiging->role);

        Notification::assertSentOnDemand(Uitnodiging::class, fn ($melding, $kanalen, $ontvanger) => $ontvanger->routes['mail'] === 'trainer@rob.nl');

        $this->actingAs($this->beheerder)
            ->get("/beheer/scholen/{$this->school->id}/gebruikers")
            ->assertInertia(fn ($pagina) => $pagina->has('invitations', 1)->where('invitations.0.email', 'trainer@rob.nl'));
    }

    public function test_een_niet_geactiveerd_account_wordt_opnieuw_uitgenodigd_en_een_actief_account_niet(): void
    {
        Notification::fake();

        // Zoals platformbeheer hem vroeger aanmaakte: een account, nooit geactiveerd.
        $oud = User::factory()->for($this->school)->create(['name' => 'Goat', 'email' => 'info@goat.nl', 'email_verified_at' => null]);
        $oud->assignRole('eigenaar');

        $this->actingAs($this->beheerder)
            ->post("/beheer/scholen/{$this->school->id}/gebruikers/{$oud->id}/opnieuw-uitnodigen")
            ->assertRedirect()
            ->assertSessionHas('status');

        // Het lege account is weg, er staat een uitnodiging als eigenaar, en de welkomstmail is verstuurd.
        $this->assertDatabaseMissing('users', ['email' => 'info@goat.nl']);

        $uitnodiging = Invitation::withoutSchoolScope()->where('email', 'info@goat.nl')->firstOrFail();
        $this->assertSame($this->school->id, $uitnodiging->school_id);
        $this->assertSame('eigenaar', $uitnodiging->role);
        $this->assertSame('Goat', $uitnodiging->name);

        Notification::assertSentOnDemand(Uitnodiging::class, fn ($melding, $kanalen, $ontvanger) => $ontvanger->routes['mail'] === 'info@goat.nl');

        // Activeren kan nu, en logt meteen in als eigenaar.
        $this->app['auth']->forgetGuards();
        $this->post('/uitnodiging/'.$uitnodiging->token, [
            'password' => 'Welkom-Goat-2026!',
            'password_confirmation' => 'Welkom-Goat-2026!',
        ])->assertRedirect('/dashboard');
        $this->assertTrue(User::where('email', 'info@goat.nl')->firstOrFail()->isEigenaar());

        // Een account dat in gebruik is, wordt nooit weggehaald.
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->beheerder)
            ->post("/beheer/scholen/{$this->school->id}/gebruikers/{$this->eigenaar->id}/opnieuw-uitnodigen")
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $this->eigenaar->id]);
    }

    public function test_een_platformbeheerder_is_geen_rol_die_je_hier_kunt_uitdelen(): void
    {
        $this->actingAs($this->beheerder)
            ->post("/beheer/scholen/{$this->school->id}/gebruikers", [
                'name' => 'Sluipweg',
                'email' => 'sluipweg@rob.nl',
                'role' => 'platformbeheerder',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'sluipweg@rob.nl']);
    }

    public function test_een_gedeactiveerd_account_komt_er_niet_meer_in(): void
    {
        $this->actingAs($this->beheerder)
            ->patch("/beheer/scholen/{$this->school->id}/gebruikers/{$this->eigenaar->id}/status")
            ->assertRedirect();

        $this->assertNotNull($this->eigenaar->refresh()->deactivated_at);
        $this->actingAs($this->eigenaar)->get('/dashboard')->assertForbidden();

        // En weer terug.
        $this->actingAs($this->beheerder)->patch("/beheer/scholen/{$this->school->id}/gebruikers/{$this->eigenaar->id}/status");
        $this->actingAs($this->eigenaar->refresh())->get('/dashboard')->assertOk();
    }

    public function test_een_gebruiker_van_een_andere_school_hoort_hier_niet(): void
    {
        $andere = School::factory()->create(['slug' => 'anders']);
        $vreemde = User::factory()->for($andere)->create();

        $this->actingAs($this->beheerder)
            ->patch("/beheer/scholen/{$this->school->id}/gebruikers/{$vreemde->id}/status")
            ->assertNotFound();
    }

    public function test_een_wachtwoordreset_is_te_starten(): void
    {
        Notification::fake();

        $this->actingAs($this->beheerder)
            ->post("/beheer/scholen/{$this->school->id}/gebruikers/{$this->eigenaar->id}/wachtwoord")
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    // ---------------------------------------------------------------
    // 6. Bekijken als
    // ---------------------------------------------------------------

    public function test_bekijken_als_een_schooleigenaar_en_weer_terug(): void
    {
        Player::factory()->for($this->school)->create(['first_name' => 'Sem']);

        $this->actingAs($this->beheerder)
            ->post("/beheer/gebruikers/{$this->eigenaar->id}/bekijken")
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($this->eigenaar);

        // Hij ziet nu écht de school, inclusief de balk die dat meldt.
        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('impersonating.name', 'Rob')
                ->where('impersonating.school', 'Keepersschool Rob'));

        // Terug kan, en dat kan niet via de beheeromgeving zelf.
        $this->get('/beheer/scholen')->assertForbidden();
        $this->post('/stop-bekijken')->assertRedirect('/beheer');

        $this->assertAuthenticatedAs($this->beheerder);
        $this->get('/dashboard')->assertRedirect('/beheer');
    }

    public function test_bekijken_wordt_vastgelegd_met_begin_en_eind(): void
    {
        $this->actingAs($this->beheerder)->post("/beheer/gebruikers/{$this->eigenaar->id}/bekijken");

        $log = Impersonation::firstOrFail();
        $this->assertSame($this->beheerder->email, $log->admin_email);
        $this->assertSame($this->eigenaar->email, $log->user_email);
        $this->assertSame($this->school->id, $log->school_id);
        $this->assertNotNull($log->started_at);
        $this->assertNull($log->ended_at);

        $this->post('/stop-bekijken');

        $this->assertNotNull($log->refresh()->ended_at);
    }

    public function test_je_kunt_niet_als_een_andere_platformbeheerder_kijken(): void
    {
        $collega = User::factory()->create(['school_id' => null]);
        $collega->assignRole(Role::Platformbeheerder->value);

        $this->actingAs($this->beheerder)
            ->post("/beheer/gebruikers/{$collega->id}/bekijken")
            ->assertForbidden();

        $this->assertDatabaseCount('impersonations', 0);
    }

    public function test_een_gedeactiveerd_account_kun_je_niet_bekijken(): void
    {
        $this->eigenaar->forceFill(['deactivated_at' => now()])->save();

        $this->actingAs($this->beheerder)
            ->post("/beheer/gebruikers/{$this->eigenaar->id}/bekijken")
            ->assertForbidden();
    }

    public function test_een_schooleigenaar_kan_niemand_bekijken(): void
    {
        $ander = User::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->post("/beheer/gebruikers/{$ander->id}/bekijken")
            ->assertNotFound();
    }

    public function test_stoppen_zonder_te_kijken_kan_niet(): void
    {
        $this->actingAs($this->eigenaar)->post('/stop-bekijken')->assertForbidden();
    }

    // ---------------------------------------------------------------
    // 7. Platformdashboard
    // ---------------------------------------------------------------

    public function test_het_overzicht_telt_over_alle_scholen_heen(): void
    {
        $tweede = School::factory()->create(['slug' => 'tweede']);

        app(Tenancy::class)->forSchool($this->school, fn () => Player::factory()->count(2)->for($this->school)->create());
        app(Tenancy::class)->forSchool($tweede, fn () => Player::factory()->count(3)->for($tweede)->create());

        $trainer = User::factory()->for($tweede)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($this->beheerder)
            ->get('/beheer')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.schools', 2)
                ->where('stats.activeSchools', 2)
                ->where('stats.players', 5)
                ->where('stats.trainers', 1)
                ->where('stats.owners', 1)
            );
    }

    public function test_het_overzicht_wijst_lege_scholen_aan(): void
    {
        School::factory()->create(['name' => 'Nog leeg', 'slug' => 'leeg']);
        app(Tenancy::class)->forSchool($this->school, fn () => Player::factory()->for($this->school)->create());

        $this->actingAs($this->beheerder)
            ->get('/beheer')
            ->assertInertia(fn ($page) => $page
                ->has('attention', 1)
                ->where('attention.0.name', 'Nog leeg')
            );
    }

    public function test_een_gewone_gebruiker_ziet_het_overzicht_niet(): void
    {
        $this->actingAs($this->eigenaar)->get('/beheer')->assertNotFound();
    }
}
