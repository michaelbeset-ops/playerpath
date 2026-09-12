<?php

namespace Tests\Feature\Players;

use App\Enums\Role;
use App\Models\Player;
use App\Models\PlayerCardSeason;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mijn kaarten: de kaart van nu en de bewaarde seizoenskaarten op een rij.
 */
class CardCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $ouder;

    protected Player $keeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->keeper = Player::factory()->for($this->school)->keeper()->create();

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);
        $this->keeper->guardians()->attach($this->ouder->id, ['relationship' => 'moeder']);
    }

    public function test_de_verzameling_toont_de_kaart_van_nu_en_de_seizoenskaarten(): void
    {
        $this->actingAs($this->ouder)
            ->get("/players/{$this->keeper->id}/kaarten")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('players/Cards')
                ->where('current.first_name', $this->keeper->first_name)
                ->count('seasons', 0)
                ->where('isOwn', false)
            );

        PlayerCardSeason::create([
            'player_id' => $this->keeper->id,
            'season' => '2025/26',
            'age_category' => 'O12',
            'overall_rating' => 68,
            'category_ratings' => ['reflexen' => 71.5, 'uitkomen' => 64],
            'xp' => 220,
            'level' => 'zilver',
            'report_count' => 9,
        ]);

        $this->actingAs($this->ouder)
            ->get("/players/{$this->keeper->id}/kaarten")
            ->assertInertia(fn ($page) => $page
                ->count('seasons', 1)
                ->where('seasons.0.season', '2025/26')
                ->where('seasons.0.age_category.key', 'O12')
                ->where('seasons.0.overall', 68)
                ->where('seasons.0.level.key', 'zilver')
                // Naar boven afgerond, net als op de kaart van nu.
                ->where('seasons.0.categories.0.rating', 72)
                ->where('seasons.0.report_count', 9)
            );
    }

    public function test_een_ouder_van_een_ander_kind_komt_er_niet_bij(): void
    {
        $ander = User::factory()->for($this->school)->create();
        $ander->assignRole(Role::Ouder->value);

        $this->actingAs($ander)->get("/players/{$this->keeper->id}/kaarten")->assertForbidden();
    }
}
