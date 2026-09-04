<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * De zijbalk-links moeten echt ergens heen gaan.
 *
 * Aanleiding: NavMain las item.url terwijl de items item.href hebben. De link
 * kreeg dus een lege href en klikken deed niets — zonder foutmelding, want er
 * ging simpelweg geen request uit. Zoiets vang je alleen met een test die de
 * doelen van het menu echt opvraagt.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /** De hrefs zoals ze in AppSidebar.vue staan. */
    public static function menuItems(): array
    {
        return [
            'Dashboard' => ['/dashboard'],
            'Spelers' => ['/players'],
            'Groepen' => ['/groups'],
            'Rapporten' => ['/reports'],
        ];
    }

    #[DataProvider('menuItems')]
    public function test_elk_menu_item_is_bereikbaar_voor_een_eigenaar(string $href): void
    {
        $school = School::factory()->create();
        $eigenaar = User::factory()->for($school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($eigenaar)->get($href)->assertOk();
    }

    #[DataProvider('menuItems')]
    public function test_elk_menu_item_is_bereikbaar_voor_een_trainer(string $href): void
    {
        $school = School::factory()->create();
        $trainer = User::factory()->for($school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->get($href)->assertOk();
    }

    public function test_de_zijbalk_verwijst_alleen_naar_bestaande_routes(): void
    {
        $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

        preg_match_all("/href: '([^']+)'/", $sidebar, $treffers);

        $this->assertNotEmpty($treffers[1], 'Geen menu-items gevonden in AppSidebar.vue.');

        $school = School::factory()->create();
        $eigenaar = User::factory()->for($school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        foreach ($treffers[1] as $href) {
            $this->actingAs($eigenaar)
                ->get($href)
                ->assertOk("Het menu-item {$href} leidt niet naar een werkend scherm.");
        }
    }
}
