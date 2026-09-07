<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BillingType;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het dashboard van een ouder.
 *
 * De kern: praktische dingen bovenaan, de spelerskaart één tik verderop, en
 * alles noemt bij welk kind het hoort. Twee kinderen is het gewone geval.
 */
class FamilyDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $ouder;

    protected Player $sem;

    protected Player $liam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        app(Tenancy::class)->set($this->school);

        $this->sem = Player::factory()->for($this->school)->create(['first_name' => 'Sem', 'last_name' => 'de Vries']);
        $this->liam = Player::factory()->for($this->school)->create(['first_name' => 'Liam', 'last_name' => 'Bakker']);

        $this->ouder->children()->attach([$this->sem->id, $this->liam->id]);
    }

    public function test_beide_kinderen_staan_erop_en_die_van_een_ander_niet(): void
    {
        $vreemd = Player::factory()->for($this->school)->create(['first_name' => 'Noud']);

        $andereOuder = User::factory()->for($this->school)->create();
        $andereOuder->assignRole(Role::Ouder->value);
        $andereOuder->children()->attach($vreemd->id);

        $this->actingAs($this->ouder)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('view', 'gezin')
                ->count('children', 2)
                ->where('children.0.first_name', 'Liam')
                ->where('children.1.first_name', 'Sem')
            );

        // En andersom: de andere ouder ziet alleen zijn eigen kind.
        $this->actingAs($andereOuder)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->count('children', 1)
                ->where('children.0.first_name', 'Noud')
            );
    }

    public function test_de_trainingen_zeggen_bij_welk_kind_ze_horen(): void
    {
        $keepers = Group::factory()->for($this->school)->create(['name' => 'Keepers ochtend']);
        $velders = Group::factory()->for($this->school)->create(['name' => 'Veldspelers']);

        $this->sem->groups()->attach($keepers->id);
        $this->liam->groups()->attach($velders->id);

        Training::factory()->for($this->school)->for($keepers)->create([
            'starts_at' => now()->addDay()->setTime(18, 0),
            'ends_at' => now()->addDay()->setTime(19, 30),
            'location' => 'Sportpark De Vliert',
        ]);

        Training::factory()->for($this->school)->for($velders)->create([
            'starts_at' => now()->addDays(2)->setTime(17, 0),
            'ends_at' => now()->addDays(2)->setTime(18, 0),
        ]);

        // Een training van een groep waar geen van beide kinderen in zit.
        $vreemdeGroep = Group::factory()->for($this->school)->create();
        Training::factory()->for($this->school)->for($vreemdeGroep)->create(['starts_at' => now()->addHours(2)]);

        $this->actingAs($this->ouder)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->count('upcoming', 2)
                // Chronologisch, en met de naam van het kind erbij: een rij
                // tijdstippen zonder naam is bij twee kinderen onbruikbaar.
                ->where('upcoming.0.label', 'Keepers ochtend')
                ->where('upcoming.0.for', 'Sem')
                ->where('upcoming.0.location', 'Sportpark De Vliert')
                ->where('upcoming.1.for', 'Liam')
            );
    }

    public function test_een_openstaande_rekening_vraagt_om_actie_en_noemt_het_kind(): void
    {
        Payment::factory()->for($this->school)->create([
            'player_id' => $this->sem->id,
            'status' => PaymentStatus::Open,
            'amount_cents' => 2750,
            'due_on' => now()->addWeek(),
        ]);

        $this->actingAs($this->ouder)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->count('todo', 1)
                ->where('todo.0.key', 'payments')
                ->where('todo.0.body', 'Voor Sem.')
                ->where('todo.0.href', '/billing')
            );
    }

    public function test_zonder_signalen_staat_er_geen_blok(): void
    {
        $this->actingAs($this->ouder)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->count('todo', 0));
    }

    public function test_het_aanbod_toont_alleen_waar_je_je_op_kunt_inschrijven(): void
    {
        $blok = Product::factory()->for($this->school)->blok(capaciteit: 10)->create(['name' => 'Keepersblok']);

        // Doorlopende training regelt de school; die hoort hier niet.
        Product::factory()->for($this->school)->create([
            'name' => 'Doorlopend',
            'type' => ProductType::Doorlopend,
            'billing_type' => BillingType::Maandelijks,
        ]);

        Product::factory()->for($this->school)->blok()->create(['name' => 'Verborgen', 'is_active' => false]);

        $this->actingAs($this->ouder)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->count('offerings', 1)
                ->where('offerings.0.id', $blok->id)
                ->where('offerings.0.spots_left', 10)
            );
    }

    public function test_groei_komt_uit_twee_rapporten_in_de_maand(): void
    {
        $this->rapport($this->sem, 6, now()->subDays(20));
        $this->rapport($this->sem, 8, now()->subDays(2));

        // Eén rapport: dan valt er niets te vergelijken en staat er niets.
        $this->rapport($this->liam, 7, now()->subDays(3));

        $this->actingAs($this->ouder)
            ->get('/dashboard')
            ->assertInertia(function ($page) {
                $kinderen = collect($page->toArray()['props']['children'])->keyBy('first_name');

                $this->assertSame(20, $kinderen['Sem']['growth']);
                $this->assertNull($kinderen['Liam']['growth']);
            });
    }

    protected function rapport(Player $speler, int $cijfer, $op): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $rapport = $speler->reports()->create([
            'trainer_id' => $trainer->id,
            'reported_on' => $op->toDateString(),
        ]);

        foreach ($speler->position->categories() as $categorie) {
            $rapport->scores()->create(['category' => $categorie->value, 'score' => $cijfer]);
        }
    }
}
