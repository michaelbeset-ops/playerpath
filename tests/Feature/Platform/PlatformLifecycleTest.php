<?php

namespace Tests\Feature\Platform;

use App\Enums\Feature;
use App\Enums\Package;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\PlatformLog;
use App\Models\Player;
use App\Models\Report;
use App\Models\School;
use App\Models\User;
use App\Support\Features\Features;
use App\Support\Money\Money;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Het leven van een school in de beheeromgeving: een pakket kiezen, terugzien
 * wat je gedaan hebt, en aan het eind opzeggen.
 */
class PlatformLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $beheerder;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->beheerder = User::factory()->create(['school_id' => null, 'email' => 'michael@playerpath.nl']);
        $this->beheerder->assignRole(Role::Platformbeheerder->value);
    }

    // ---------------------------------------------------------------
    // Pakketten
    // ---------------------------------------------------------------

    public function test_een_pakket_zet_de_functies_in_een_keer_goed(): void
    {
        $this->actingAs($this->beheerder)
            ->post('/beheer/scholen', [
                'name' => 'Keepersschool Rob',
                'slug' => 'rob',
                'package' => Package::Start->value,
            ])
            ->assertSessionHasNoErrors();

        $school = School::firstWhere('slug', 'rob');

        $this->assertSame(Package::Start->value, $school->package);

        // Start is de administratie, zonder de ontwikkelingslaag.
        $this->assertTrue(Features::enabledFor($school, Feature::Betalingen));
        $this->assertFalse(Features::enabledFor($school, Feature::Ontwikkeling));
    }

    public function test_zonder_pakket_staat_alles_gewoon_aan(): void
    {
        $this->actingAs($this->beheerder)
            ->post('/beheer/scholen', ['name' => 'Los', 'slug' => 'los'])
            ->assertSessionHasNoErrors();

        $school = School::firstWhere('slug', 'los');

        $this->assertNull($school->package);

        foreach (Feature::cases() as $feature) {
            $this->assertTrue(Features::enabledFor($school, $feature), $feature->value);
        }
    }

    public function test_opslaan_zonder_pakketwissel_draait_een_bewuste_afwijking_niet_terug(): void
    {
        $school = School::factory()->create(['slug' => 'rob', 'package' => Package::Academie->value]);
        $school->update(['features' => [Feature::Betalingen->value => false] + Package::Academie->featureMap()]);

        $this->actingAs($this->beheerder)
            ->patch("/beheer/scholen/{$school->id}", [
                'name' => 'Andere naam',
                'slug' => 'rob',
                'package' => Package::Academie->value,
            ])
            ->assertSessionHasNoErrors();

        // De naam is gewijzigd, de afwijking staat er nog.
        $this->assertSame('Andere naam', $school->fresh()->name);
        $this->assertFalse(Features::enabledFor($school->fresh(), Feature::Betalingen));
    }

    public function test_een_echte_pakketwissel_zet_de_functies_wel_opnieuw(): void
    {
        $school = School::factory()->create(['slug' => 'rob', 'package' => Package::Academie->value]);
        $school->update(['features' => Package::Academie->featureMap()]);

        $this->actingAs($this->beheerder)
            ->patch("/beheer/scholen/{$school->id}", [
                'name' => $school->name,
                'slug' => 'rob',
                'package' => Package::Start->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(Features::enabledFor($school->fresh(), Feature::Ontwikkeling));
    }

    public function test_een_onbekend_pakket_wordt_geweigerd(): void
    {
        $this->actingAs($this->beheerder)
            ->post('/beheer/scholen', ['name' => 'Rob', 'slug' => 'rob', 'package' => 'goud'])
            ->assertSessionHasErrors('package');
    }

    // ---------------------------------------------------------------
    // Omzet op het platformoverzicht
    // ---------------------------------------------------------------

    public function test_de_omzet_telt_de_pakketten_van_actieve_scholen_op(): void
    {
        School::factory()->create(['slug' => 'a', 'package' => Package::Start->value]);
        School::factory()->create(['slug' => 'b', 'package' => Package::Academie->value]);

        // Telt niet mee: uit, en zonder pakket.
        School::factory()->create(['slug' => 'c', 'package' => Package::Academie->value, 'is_active' => false]);
        School::factory()->create(['slug' => 'd', 'package' => null]);

        $verwacht = Package::Start->priceCents() + Package::Academie->priceCents();

        $this->actingAs($this->beheerder)
            ->get('/beheer')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.mrrCents', $verwacht)
                ->where('stats.mrrPerYear', Money::format($verwacht * 12))
                ->where('stats.withoutPackage', 1));
    }

    public function test_zonder_pakketten_staat_de_omzet_op_nul(): void
    {
        School::factory()->create(['slug' => 'a', 'package' => null]);

        $this->actingAs($this->beheerder)
            ->get('/beheer')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('stats.mrrCents', 0));
    }

    // ---------------------------------------------------------------
    // Logboek
    // ---------------------------------------------------------------

    public function test_beheeracties_komen_in_het_logboek(): void
    {
        $school = School::factory()->create(['name' => 'Keepersschool Rob', 'slug' => 'rob']);

        $this->actingAs($this->beheerder)
            ->patch("/beheer/scholen/{$school->id}/status")
            ->assertRedirect();

        $log = PlatformLog::latest('id')->first();

        $this->assertSame('school.deactivated', $log->action);
        $this->assertSame($school->id, $log->school_id);
        $this->assertSame('Keepersschool Rob', $log->school_name);
        $this->assertSame('michael@playerpath.nl', $log->admin_email);
    }

    public function test_het_logboek_zegt_in_woorden_welke_functie_er_veranderde(): void
    {
        $school = School::factory()->create(['slug' => 'rob']);

        $this->actingAs($this->beheerder)
            ->patch("/beheer/scholen/{$school->id}/functies", [
                'features' => collect(Feature::cases())
                    ->mapWithKeys(fn (Feature $f) => [$f->value => $f !== Feature::Kalender])
                    ->all(),
            ])
            ->assertSessionHasNoErrors();

        $log = PlatformLog::where('action', 'school.features')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Kalender', $log->summary);
        $this->assertStringContainsString('uitgezet', $log->summary);
    }

    public function test_een_opslag_zonder_wijziging_vervuilt_het_logboek_niet(): void
    {
        $school = School::factory()->create(['slug' => 'rob']);

        $alles = collect(Feature::cases())->mapWithKeys(fn (Feature $f) => [$f->value => true])->all();

        $this->actingAs($this->beheerder)
            ->patch("/beheer/scholen/{$school->id}/functies", ['features' => $alles])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, PlatformLog::where('action', 'school.features')->count());
    }

    public function test_het_logboek_is_alleen_voor_de_platformbeheerder(): void
    {
        $school = School::factory()->create(['slug' => 'rob']);
        $eigenaar = User::factory()->for($school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($eigenaar)->get('/beheer/logboek')->assertNotFound();
        $this->actingAs($this->beheerder)->get('/beheer/logboek')->assertOk();
    }

    // ---------------------------------------------------------------
    // Opzeggen: echt weg
    // ---------------------------------------------------------------

    public function test_verwijderen_haalt_de_school_en_alles_eraan_weg(): void
    {
        $school = School::factory()->create(['name' => 'Keepersschool Rob', 'slug' => 'rob']);
        $andere = School::factory()->create(['name' => 'Voetbalschool Yoel', 'slug' => 'yoel']);

        $trainer = User::factory()->for($school)->create();
        $trainer->assignRole(Role::Trainer->value);

        app(Tenancy::class)->forSchool($school, function () use ($school, $trainer) {
            $speler = Player::factory()->for($school)->create();
            Report::factory()->for($speler)->create(['trainer_id' => $trainer->id]);
            Payment::factory()->for($speler)->create();
        });

        $blijver = app(Tenancy::class)->forSchool(
            $andere,
            fn () => Player::factory()->for($andere)->create(),
        );

        DB::table('password_reset_tokens')->insert([
            'email' => $trainer->email,
            'token' => 'x',
            'created_at' => now(),
        ]);

        $this->actingAs($this->beheerder)
            ->delete("/beheer/scholen/{$school->id}", ['confirm' => 'Keepersschool Rob'])
            ->assertRedirect('/beheer/scholen');

        $this->assertDatabaseMissing('schools', ['id' => $school->id]);
        $this->assertDatabaseMissing('users', ['id' => $trainer->id]);
        $this->assertSame(0, Player::withoutSchoolScope()->where('school_id', $school->id)->count());
        $this->assertSame(0, Report::withoutSchoolScope()->where('school_id', $school->id)->count());
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $trainer->email]);

        // De buren merken er niets van.
        $this->assertDatabaseHas('schools', ['id' => $andere->id]);
        $this->assertSame(1, Player::withoutSchoolScope()->where('school_id', $andere->id)->count());
        $this->assertNotNull($blijver->fresh());
    }

    public function test_de_verwijdering_blijft_in_het_logboek_staan(): void
    {
        $school = School::factory()->create(['name' => 'Keepersschool Rob', 'slug' => 'rob']);

        $this->actingAs($this->beheerder)
            ->delete("/beheer/scholen/{$school->id}", ['confirm' => 'Keepersschool Rob']);

        $log = PlatformLog::where('action', 'school.deleted')->latest('id')->first();

        $this->assertNotNull($log);
        // De school is weg, de naam staat er nog.
        $this->assertNull($log->school_id);
        $this->assertSame('Keepersschool Rob', $log->school_name);
    }

    public function test_een_verkeerd_overgetypte_naam_verwijdert_niets(): void
    {
        $school = School::factory()->create(['name' => 'Keepersschool Rob', 'slug' => 'rob']);

        $this->actingAs($this->beheerder)
            ->delete("/beheer/scholen/{$school->id}", ['confirm' => 'keepersschool rob'])
            ->assertSessionHasErrors('confirm');

        $this->assertDatabaseHas('schools', ['id' => $school->id]);
    }

    public function test_de_tellingen_in_de_bevestiging_gaan_alleen_over_deze_school(): void
    {
        $school = School::factory()->create(['name' => 'Keepersschool Rob', 'slug' => 'rob']);
        $andere = School::factory()->create(['slug' => 'yoel']);

        app(Tenancy::class)->forSchool($school, fn () => Player::factory()->count(2)->for($school)->create());
        app(Tenancy::class)->forSchool($andere, fn () => Player::factory()->count(5)->for($andere)->create());

        $this->actingAs($this->beheerder)
            ->get("/beheer/scholen/{$school->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('deletes.players', 2));
    }

    public function test_een_eigenaar_kan_geen_school_verwijderen(): void
    {
        $school = School::factory()->create(['name' => 'Keepersschool Rob', 'slug' => 'rob']);
        $eigenaar = User::factory()->for($school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($eigenaar)
            ->delete("/beheer/scholen/{$school->id}", ['confirm' => 'Keepersschool Rob'])
            ->assertNotFound();

        $this->assertDatabaseHas('schools', ['id' => $school->id]);
    }
}
