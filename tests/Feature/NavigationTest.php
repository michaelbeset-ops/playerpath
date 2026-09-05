<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Navigation\MainNavigation;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Het menu mag nooit iets tonen dat je niet mag openen, en nooit iets missen
 * dat je wel mag.
 *
 * Aanleiding, twee keer:
 * 1. NavMain las item.url terwijl de items item.href hebben — klikken deed
 *    niets, zonder foutmelding, want er ging geen request uit.
 * 2. Het menu was hardgecodeerd, dus een ouder zag Spelers, Groepen en
 *    Rapporten staan die allemaal 403 gaven.
 *
 * Sindsdien bepaalt de server het menu op basis van de policies, en loopt deze
 * test er per rol echt doorheen.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public static function rollen(): array
    {
        return [
            'eigenaar' => [Role::Eigenaar],
            'trainer' => [Role::Trainer],
            'ouder' => [Role::Ouder],
            'speler' => [Role::Speler],
        ];
    }

    protected function gebruiker(Role $rol): User
    {
        $school = School::factory()->create();
        $user = User::factory()->for($school)->create();
        $user->assignRole($rol->value);

        app(Tenancy::class)->set($school);

        // Een ouder of speler zonder eigen speler ziet nergens iets; geef ze er
        // dus een, zodat de test het echte geval dekt.
        $speler = Player::factory()->for($school)->create();

        if ($rol === Role::Ouder) {
            $user->children()->attach($speler->id);
        }

        if ($rol === Role::Speler) {
            $speler->update(['user_id' => $user->id]);
        }

        return $user;
    }

    #[DataProvider('rollen')]
    public function test_elk_getoond_menu_item_werkt_ook_echt(Role $rol): void
    {
        $user = $this->gebruiker($rol);

        $items = app(MainNavigation::class)->for($user);

        $this->assertNotEmpty($items, "De rol {$rol->value} ziet helemaal geen menu.");

        foreach ($items as $item) {
            $this->actingAs($user)
                ->get($item['href'])
                ->assertOk("Het menu-item {$item['title']} ({$item['href']}) werkt niet voor een {$rol->value}.");
        }
    }

    public function test_een_ouder_krijgt_geen_beheer_items_te_zien(): void
    {
        $ouder = $this->gebruiker(Role::Ouder);

        $hrefs = array_column(app(MainNavigation::class)->for($ouder), 'href');

        $this->assertContains('/dashboard', $hrefs);
        $this->assertContains('/trainings', $hrefs);
        $this->assertNotContains('/players', $hrefs);
        $this->assertNotContains('/groups', $hrefs);
        $this->assertNotContains('/reports', $hrefs);
    }

    public function test_een_eigenaar_ziet_het_hele_menu(): void
    {
        $eigenaar = $this->gebruiker(Role::Eigenaar);

        $hrefs = array_column(app(MainNavigation::class)->for($eigenaar), 'href');

        $this->assertSame(['/dashboard', '/players', '/groups', '/trainings', '/reports'], $hrefs);
    }

    public function test_een_ouder_komt_niet_bij_het_ledenbestand(): void
    {
        $ouder = $this->gebruiker(Role::Ouder);

        // Een ouder hoort niet te zien welke andere kinderen op de school zitten.
        $this->actingAs($ouder)->get('/players')->assertForbidden();
    }
}
