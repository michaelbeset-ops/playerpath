<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentSchoolMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_de_actieve_school_komt_uit_het_ingelogde_account(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->for($school)->create();
        $user->assignRole(Role::Eigenaar->value);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame($school->id, app(Tenancy::class)->id());
    }

    public function test_een_gebruiker_zonder_school_wordt_geweigerd(): void
    {
        $user = User::factory()->create(['school_id' => null]);

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    public function test_een_inactieve_school_wordt_geweigerd(): void
    {
        $school = School::factory()->inactive()->create();
        $user = User::factory()->for($school)->create();

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }
}
