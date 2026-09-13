<?php

namespace Tests\Feature\Onboarding;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * playerpath:test-ouder: een ouder met kind en kind-link om mee te testen.
 */
class CreateTestParentTest extends TestCase
{
    use RefreshDatabase;

    public function test_zonder_school_zegt_hij_dat_er_eerst_een_school_moet_komen(): void
    {
        $this->seed(RoleSeeder::class);

        $this->artisan('playerpath:test-ouder')
            ->expectsOutputToContain('Er is nog geen school')
            ->assertFailed();
    }

    public function test_met_eigen_email_en_wachtwoord_kun_je_meteen_inloggen(): void
    {
        $school = $this->school();
        app(Tenancy::class)->set($school);
        $leeg = Player::factory()->for($school)->create();
        $gevuld = Player::factory()->for($school)->create();
        $gevuld->forceFill(['overall_rating' => 81])->save();

        $this->artisan('playerpath:test-ouder', ['school' => 'testschool', '--email' => 'mobiel@playerpath.nl', '--wachtwoord' => 'Mobieltest-2026'])
            ->expectsOutputToContain('Wachtwoord: Mobieltest-2026')
            ->assertSuccessful();

        $ouder = User::where('email', 'mobiel@playerpath.nl')->firstOrFail();
        $this->assertTrue($ouder->isOuder());
        $this->assertSame($school->id, $ouder->school_id);
        $this->assertTrue(Hash::check('Mobieltest-2026', $ouder->password));

        app(Tenancy::class)->set($school);
        $this->assertTrue($ouder->children()->whereKey($gevuld->id)->exists());
        $this->assertFalse($ouder->children()->whereKey($leeg->id)->exists());
        $this->assertNotNull($gevuld->refresh()->child_token);

        // Echt inloggen werkt.
        $this->post('/login', ['email' => 'mobiel@playerpath.nl', 'password' => 'Mobieltest-2026'])->assertRedirect();
        $this->assertAuthenticatedAs($ouder);
    }

    public function test_opnieuw_draaien_met_hetzelfde_adres_zet_alleen_het_wachtwoord_opnieuw(): void
    {
        $this->school();

        $this->artisan('playerpath:test-ouder', ['school' => 'testschool', '--email' => 'twee@playerpath.nl', '--wachtwoord' => 'Eerste-12345'])->assertSuccessful();
        $this->artisan('playerpath:test-ouder', ['school' => 'testschool', '--email' => 'twee@playerpath.nl', '--wachtwoord' => 'Tweede-12345'])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'twee@playerpath.nl')->count());
        $this->assertTrue(Hash::check('Tweede-12345', User::where('email', 'twee@playerpath.nl')->value('password')));
    }

    public function test_zonder_spelers_komt_er_een_testkind(): void
    {
        $school = $this->school();

        $this->artisan('playerpath:test-ouder', ['school' => 'testschool', '--email' => 'leeg@playerpath.nl'])->assertSuccessful();

        app(Tenancy::class)->set($school);
        $kind = User::where('email', 'leeg@playerpath.nl')->firstOrFail()->children()->first();

        $this->assertNotNull($kind);
        $this->assertSame('Test', $kind->first_name);
        $this->assertNotNull($kind->child_token);
    }

    public function test_weigert_een_onbekende_school_en_een_adres_van_iemand_anders(): void
    {
        $school = $this->school();

        $this->artisan('playerpath:test-ouder', ['school' => 'bestaat-niet'])->expectsOutputToContain('testschool')->assertFailed();

        User::factory()->for($school)->create(['email' => 'eigenaar@playerpath.nl'])->assignRole(Role::Eigenaar->value);
        $this->artisan('playerpath:test-ouder', ['school' => 'testschool', '--email' => 'eigenaar@playerpath.nl'])->assertFailed();

        // Zonder school: de lijst met slugs, en dat is geen fout.
        $this->artisan('playerpath:test-ouder')->expectsOutputToContain('testschool')->assertSuccessful();
    }

    protected function school(): School
    {
        $this->seed(RoleSeeder::class);

        return School::factory()->create(['slug' => 'testschool']);
    }
}
