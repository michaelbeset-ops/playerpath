<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    protected function eigenaar(School $school): User
    {
        $user = User::factory()->for($school)->create();
        $user->assignRole(Role::Eigenaar->value);

        return $user;
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $school = School::factory()->create();

        $this->actingAs($this->eigenaar($school))->get('/dashboard')->assertOk();
    }

    public function test_het_dashboard_telt_alleen_de_eigen_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        Player::factory()->count(3)->for($schoolA)->create();
        Player::factory()->count(7)->for($schoolB)->create();

        $this->actingAs($this->eigenaar($schoolA))
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('stats.players', 3)
                ->where('stats.reportsThisWeek', 0)
            );
    }

    public function test_inactieve_spelers_tellen_niet_mee(): void
    {
        $school = School::factory()->create();

        Player::factory()->count(2)->for($school)->create();
        Player::factory()->for($school)->create(['is_active' => false]);

        $this->actingAs($this->eigenaar($school))
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('stats.players', 2));
    }

    public function test_rapporten_van_deze_week_worden_geteld(): void
    {
        $school = School::factory()->create();
        $eigenaar = $this->eigenaar($school);

        app(Tenancy::class)->set($school);

        $speler = Player::factory()->for($school)->keeper()->create();

        \App\Models\Report::factory()->for($school)->create([
            'player_id' => $speler->id,
            'trainer_id' => $eigenaar->id,
            'reported_on' => now(),
        ]);

        \App\Models\Report::factory()->for($school)->create([
            'player_id' => $speler->id,
            'trainer_id' => $eigenaar->id,
            'reported_on' => now()->subWeeks(3),
        ]);

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('stats.reportsThisWeek', 1));
    }
}
