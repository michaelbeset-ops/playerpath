<?php

namespace Tests\Feature\Onboarding;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * playerpath:test-ouder: een ouder met kind en kind-link om mee te testen.
 */
class CreateTestParentTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->school = School::factory()->create(['slug' => 'testschool']);
    }

    public function test_maakt_een_ouder_aan_gekoppeld_aan_de_best_gevulde_speler_met_kind_link(): void
    {
        app(Tenancy::class)->set($this->school);
        $leeg = Player::factory()->for($this->school)->create();
        $gevuld = Player::factory()->for($this->school)->create();
        $gevuld->forceFill(['overall_rating' => 81])->save();

        $this->artisan('playerpath:test-ouder', ['school' => 'testschool', '--email' => 'mobiel@playerpath.nl'])
            ->expectsOutputToContain('Testouder aangemaakt')
            ->assertSuccessful();

        $ouder = User::where('email', 'mobiel@playerpath.nl')->firstOrFail();
        $this->assertTrue($ouder->isOuder());
        $this->assertSame($this->school->id, $ouder->school_id);
        $this->assertNotNull($ouder->email_verified_at);

        app(Tenancy::class)->set($this->school);
        $this->assertTrue($ouder->children()->whereKey($gevuld->id)->exists());
        $this->assertFalse($ouder->children()->whereKey($leeg->id)->exists());
        $this->assertNotNull($gevuld->refresh()->child_token);
    }

    public function test_zonder_spelers_komt_er_een_testkind(): void
    {
        $this->artisan('playerpath:test-ouder', ['school' => 'testschool', '--email' => 'leeg@playerpath.nl'])->assertSuccessful();

        app(Tenancy::class)->set($this->school);
        $ouder = User::where('email', 'leeg@playerpath.nl')->firstOrFail();
        $kind = $ouder->children()->first();

        $this->assertNotNull($kind);
        $this->assertSame('Test', $kind->first_name);
        $this->assertNotNull($kind->child_token);
    }

    public function test_weigert_een_onbekende_school_en_een_bestaand_adres(): void
    {
        $this->artisan('playerpath:test-ouder', ['school' => 'bestaat-niet'])->assertFailed();

        User::factory()->for($this->school)->create(['email' => 'bezet@playerpath.nl'])->assignRole(Role::Ouder->value);
        $this->artisan('playerpath:test-ouder', ['school' => 'testschool', '--email' => 'bezet@playerpath.nl'])->assertFailed();

        // Zonder school: de lijst met slugs, en dat is geen fout.
        $this->artisan('playerpath:test-ouder')->expectsOutputToContain('testschool')->assertSuccessful();
        $this->assertSame(1, User::count());
    }
}
