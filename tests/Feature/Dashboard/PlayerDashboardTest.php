<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\PlayerCard\CalculatePlayerCard;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het dashboard van een speler met een eigen inlog.
 *
 * Alleen zijn kaart, zijn voortgang en zijn volgende training. Inschrijven en
 * betalen doen zijn ouders, dus dat staat er niet - ook niet als je de URL
 * intypt.
 */
class PlayerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $account;

    protected Player $sem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();

        $this->account = User::factory()->for($this->school)->create(['name' => 'Sem de Vries']);
        $this->account->assignRole(Role::Speler->value);

        app(Tenancy::class)->set($this->school);

        $this->sem = Player::factory()->for($this->school)->keeper()->create([
            'first_name' => 'Sem',
            'last_name' => 'de Vries',
            'user_id' => $this->account->id,
        ]);
    }

    public function test_een_speler_ziet_zijn_eigen_kaart_en_niets_om_te_kopen(): void
    {
        $groep = Group::factory()->for($this->school)->create(['name' => 'Keepers']);
        $this->sem->groups()->attach($groep->id);

        Training::factory()->for($this->school)->for($groep)->create([
            'starts_at' => now()->addDay()->setTime(18, 0),
            'ends_at' => now()->addDay()->setTime(19, 0),
        ]);

        $this->actingAs($this->account)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('view', 'speler')
                ->where('player.first_name', 'Sem')
                ->where('card.name', 'Sem de Vries')
                ->where('nextTraining.label', 'Keepers')
                ->count('upcoming', 1)
                ->where('hasEnoughData', false)
                // Mijlpalen en de deel-link horen bij zijn kaart; delen staat
                // uit totdat een ouder of de school het aanzet.
                ->has('badges')
                ->where('share.url', null)
                // Geen inschrijf- of betaalblokken: dat is van de ouders.
                ->missing('offerings')
                ->missing('todo')
            );
    }

    public function test_met_twee_rapporten_staat_er_groei_en_een_volgende_stap(): void
    {
        $this->rapport(6, now()->subDays(20));
        $this->rapport(8, now()->subDays(2));

        // Het voorstel "hier kun je aan werken" leest de doorgerekende kaart.
        app(CalculatePlayerCard::class)->refresh($this->sem->refresh());

        $this->actingAs($this->account)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('hasEnoughData', true)
                ->where('quarter.growth', 20)
                ->count('categories', 6)
                ->where('categories.0.trend.key', 'sterk')
                ->where('nextStep.type', 'suggestion')
            );
    }

    public function test_shop_en_rekeningen_zijn_niet_van_de_speler(): void
    {
        $this->actingAs($this->account)->get('/shop')->assertForbidden();
        $this->actingAs($this->account)->get('/billing')->assertForbidden();

        $this->actingAs($this->account)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('nav', function ($nav) {
                $hrefs = $this->navHrefs($nav);

                return ! in_array('/shop', $hrefs, true) && ! in_array('/billing', $hrefs, true);
            }));
    }

    protected function rapport(int $cijfer, $op): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $rapport = $this->sem->reports()->create([
            'trainer_id' => $trainer->id,
            'reported_on' => $op->toDateString(),
        ]);

        foreach ($this->sem->position->categories() as $categorie) {
            $rapport->scores()->create(['category' => $categorie->value, 'score' => $cijfer]);
        }
    }
}
