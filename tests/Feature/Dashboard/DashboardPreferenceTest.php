<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Dashboard\DashboardPreferences;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zelf bepalen wat er op je dashboard staat.
 *
 * De twee dingen die hier echt toe doen: je kunt niets aanzetten wat je niet
 * mag zien, en wat uitstaat wordt ook niet berekend.
 */
class DashboardPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);
    }

    protected function trainer(): User
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        return $trainer;
    }

    public function test_zonder_voorkeur_staan_er_vier_cijfers(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'tiles',
                fn ($tiles) => $this->tileKeys($tiles) === ['players', 'rating', 'reports', 'attendance'],
            ));
    }

    public function test_een_gekozen_cijfer_komt_erbij_en_een_uitgezet_cijfer_verdwijnt(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/settings/dashboard', [
                'tiles' => ['keepers' => true, 'rating' => false],
                'blocks' => [],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(function ($page) {
                $sleutels = $this->tileKeys($page->toArray()['props']['tiles']);

                $this->assertContains('keepers', $sleutels);
                $this->assertNotContains('rating', $sleutels);
                // Wat je niet hebt aangeraakt houdt zijn eigen standaard.
                $this->assertContains('reports', $sleutels);
            });
    }

    public function test_een_trainer_kan_de_omzettegel_niet_aanzetten(): void
    {
        $trainer = $this->trainer();

        // Het scherm biedt hem geen geldtegels aan...
        $this->actingAs($trainer)
            ->get('/settings/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'tiles',
                fn ($tiles) => ! collect($tiles)->pluck('key')->contains('revenue'),
            ));

        // ...en het formulier omzeilen helpt ook niet.
        $this->actingAs($trainer)
            ->patch('/settings/dashboard', ['tiles' => ['revenue' => true], 'blocks' => ['finance' => true]])
            ->assertSessionHasNoErrors();

        $this->actingAs($trainer)
            ->get('/dashboard')
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];

                $this->assertNotContains('revenue', $this->tileKeys($props['tiles']));
                $this->assertNotContains('finance', $props['blocks']);
                $this->assertNull($props['finance']);
            });
    }

    public function test_een_uitgezette_functie_haalt_ook_de_tegel_weg(): void
    {
        $this->school->update(['features' => [Feature::Ontwikkeling->value => false]]);

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(function ($page) {
                $sleutels = $this->tileKeys($page->toArray()['props']['tiles']);

                $this->assertNotContains('rating', $sleutels);
                $this->assertNotContains('reports', $sleutels);
                $this->assertContains('players', $sleutels);
            });
    }

    public function test_een_uitgezet_blok_wordt_ook_niet_berekend(): void
    {
        Player::factory()->count(2)->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->patch('/settings/dashboard', ['tiles' => [], 'blocks' => ['attention' => false]])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->count('needsAttention', 0)
                ->where('blocks', fn ($blocks) => ! collect($blocks)->contains('attention'))
            );
    }

    public function test_de_voorkeur_geldt_per_gebruiker(): void
    {
        $trainer = $this->trainer();

        app(DashboardPreferences::class)->save($this->eigenaar, ['reports' => false], []);

        $this->actingAs($trainer)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $this->assertContains('reports', $this->tileKeys($page->toArray()['props']['tiles'])));

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $this->assertNotContains('reports', $this->tileKeys($page->toArray()['props']['tiles'])));
    }

    public function test_een_ouder_heeft_hier_niets_te_zoeken(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $ouder->children()->attach(Player::factory()->for($this->school)->create()->id);

        // Geen leeg scherm aanbieden: hij ziet de kaart van zijn kind.
        $this->actingAs($ouder)->get('/settings/dashboard')->assertNotFound();
        $this->actingAs($ouder)->patch('/settings/dashboard', ['tiles' => []])->assertNotFound();
    }
}
