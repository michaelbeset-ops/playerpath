<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Navigation\MainNavigation;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Het menu mag nooit iets tonen dat je niet mag openen, en nooit iets missen
 * dat je wel mag.
 *
 * Aanleiding, twee keer:
 * 1. NavMain las item.url terwijl de items item.href hebben â€” klikken deed
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

        $this->seed(RoleSeeder::class);
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

    /**
     * Alle adressen die deze rol in de balk ziet, groepen platgeslagen.
     *
     * @return list<array{title: string, href: string}>
     */
    protected function menu(User $user): array
    {
        $plat = [];

        foreach (app(MainNavigation::class)->for($user) as $groep) {
            if ($groep['href'] !== null) {
                $plat[] = ['title' => $groep['title'], 'href' => $groep['href']];

                continue;
            }

            foreach ($groep['items'] as $item) {
                $plat[] = ['title' => $groep['title'].' > '.$item['title'], 'href' => $item['href']];
            }
        }

        return $plat;
    }

    #[DataProvider('rollen')]
    public function test_elk_getoond_menu_item_werkt_ook_echt(Role $rol): void
    {
        $user = $this->gebruiker($rol);

        $items = $this->menu($user);

        $this->assertNotEmpty($items, "De rol {$rol->value} ziet helemaal geen menu.");

        foreach ($items as $item) {
            $this->actingAs($user)
                ->get($item['href'])
                ->assertOk("Het menu-item {$item['title']} ({$item['href']}) werkt niet voor een {$rol->value}.");
        }
    }

    public function test_een_lege_groep_verdwijnt_en_een_groep_van_een_wordt_het_item(): void
    {
        $eigenaar = $this->gebruiker(Role::Eigenaar);

        $groepen = app(MainNavigation::class)->for($eigenaar);

        foreach ($groepen as $groep) {
            // Of het is zelf een link, of het is een uitklap met minstens twee
            // items. Een uitklap met een is een woord dat iets anders belooft.
            if ($groep['href'] === null) {
                $this->assertGreaterThan(1, count($groep['items']), "De groep {$groep['title']} klapt uit naar te weinig items.");
            } else {
                $this->assertSame([], $groep['items']);
            }
        }

        // Ontwikkeling heeft maar een item (Rapporten) en toont dat dus zelf.
        $titels = array_column($groepen, 'title');
        $this->assertContains('Rapporten', $titels);
        $this->assertNotContains('Ontwikkeling', $titels);
    }

    public function test_een_ouder_krijgt_geen_beheer_items_te_zien(): void
    {
        $ouder = $this->gebruiker(Role::Ouder);

        $hrefs = array_column($this->menu($ouder), 'href');

        $this->assertContains('/dashboard', $hrefs);
        $this->assertContains('/trainings', $hrefs);
        $this->assertNotContains('/clients', $hrefs);
        $this->assertNotContains('/staff', $hrefs);
        $this->assertNotContains('/groups', $hrefs);
        $this->assertNotContains('/reports', $hrefs);
        // De administratie van de school is niet van een ouder; die heeft een
        // eigen scherm met alleen het eigen abonnement.
        $this->assertNotContains('/payments', $hrefs);
        $this->assertNotContains('/aanbod', $hrefs);
        $this->assertNotContains('/subscriptions', $hrefs);
        $this->assertContains('/billing', $hrefs);
    }

    public function test_een_eigenaar_ziet_het_hele_menu(): void
    {
        $eigenaar = $this->gebruiker(Role::Eigenaar);

        $hrefs = array_column($this->menu($eigenaar), 'href');

        $this->assertSame([
            '/dashboard',
            '/calendar', '/trainings', '/trainings/mijn',
            '/clients', '/groups', '/enrollments',
            '/reports',
            '/payments', '/subscriptions', '/aanbod', '/exports',
            '/announcements', '/announcements/verjaardagen',
            '/staff', '/locaties', '/branding', '/verantwoording', '/settings/profile',
        ], $hrefs);
    }

    public function test_een_ouder_komt_niet_bij_het_ledenbestand(): void
    {
        $ouder = $this->gebruiker(Role::Ouder);

        // Een ouder hoort niet te zien welke andere kinderen op de school zitten.
        $this->actingAs($ouder)->get('/clients')->assertForbidden();
        $this->actingAs($ouder)->get('/staff')->assertForbidden();

        // Het oude ouder-tabblad stuurt door naar het overzicht, en dat weigert.
        $this->actingAs($ouder)->get('/clients/guardians')->assertRedirect('/clients');
    }
}
