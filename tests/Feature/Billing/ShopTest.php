<?php

namespace Tests\Feature\Billing;

use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\User;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeGateway;
use Tests\TestCase;

/**
 * De shop: wat een ouder zelf kan afnemen.
 *
 * De prijslijst is dezelfde als bij Producten; wat hier getest wordt is vooral
 * wie er wat mag kopen, en dat een abonnement er niet tussen staat.
 */
class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $ouder;

    protected Player $kind;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        app(Tenancy::class)->set($this->school);

        $this->kind = Player::factory()->for($this->school)->create(['first_name' => 'Sem']);
        $this->ouder->children()->attach($this->kind->id);
    }

    protected function product(array $overschrijf = []): Product
    {
        return Product::factory()->for($this->school)->create(array_merge([
            'name' => 'Rittenkaart 10',
            'type' => ProductType::Rittenkaart,
            'amount_cents' => 12500,
            'credits' => 10,
            'is_active' => true,
        ], $overschrijf));
    }

    public function test_de_shop_toont_de_producten_van_de_school_zonder_abonnementen(): void
    {
        $this->product();
        $this->product(['name' => 'Zomerkamp', 'type' => ProductType::Kamp, 'amount_cents' => 9500, 'credits' => null]);

        // Een abonnement loopt door; dat regelt de school, anders krijgt een
        // ouder twee keer per maand een rekening.
        $this->product(['name' => 'Maandabonnement', 'type' => ProductType::Abonnement, 'credits' => null]);

        // En wat niet meer verkocht wordt hoort er ook niet te staan.
        $this->product(['name' => 'Oude clinic', 'type' => ProductType::Kamp, 'credits' => null, 'is_active' => false]);

        $this->actingAs($this->ouder)
            ->get('/shop')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/Shop')
                ->count('products', 2)
                ->where('products.0.name', 'Zomerkamp')
                ->where('products.1.name', 'Rittenkaart 10')
                ->where('products.1.credits', 10)
                ->count('players', 1)
            );
    }

    public function test_kopen_maakt_een_aankoop_en_een_rekening(): void
    {
        $product = $this->product();

        $this->actingAs($this->ouder)
            ->post('/shop/'.$product->id, ['player_id' => $this->kind->id])
            ->assertRedirect('/billing');

        $this->assertDatabaseHas('purchases', [
            'player_id' => $this->kind->id,
            'name' => 'Rittenkaart 10',
            'amount_cents' => 12500,
            'credits_total' => 10,
        ]);

        // Zonder betaalprovider staat de rekening gewoon open: doen alsof er
        // betaald is gebeurt nooit.
        $this->assertDatabaseHas('payments', [
            'player_id' => $this->kind->id,
            'amount_cents' => 12500,
            'status' => 'open',
        ]);
    }

    public function test_met_een_provider_ga_je_meteen_afrekenen(): void
    {
        $gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $gateway);

        $product = $this->product();

        $antwoord = $this->actingAs($this->ouder)->post('/shop/'.$product->id, ['player_id' => $this->kind->id]);

        $antwoord->assertRedirect();
        $this->assertStringContainsString('checkout', (string) $antwoord->headers->get('Location'));
        $this->assertCount(1, $gateway->started);
    }

    public function test_je_koopt_niet_voor_het_kind_van_een_ander(): void
    {
        $product = $this->product();
        $vreemdKind = Player::factory()->for($this->school)->create();

        $this->actingAs($this->ouder)
            ->post('/shop/'.$product->id, ['player_id' => $vreemdKind->id])
            ->assertSessionHasErrors('player_id');

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_een_abonnement_koop_je_hier_niet(): void
    {
        $abonnement = $this->product(['type' => ProductType::Abonnement, 'credits' => null]);

        $this->actingAs($this->ouder)
            ->post('/shop/'.$abonnement->id, ['player_id' => $this->kind->id])
            ->assertStatus(422);

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_een_product_van_een_andere_school_bestaat_hier_niet(): void
    {
        $andere = School::factory()->create();
        $vreemd = Product::factory()->for($andere)->create(['type' => ProductType::Kamp]);

        $this->actingAs($this->ouder)
            ->post('/shop/'.$vreemd->id, ['player_id' => $this->kind->id])
            ->assertNotFound();
    }

    public function test_zonder_eigen_speler_valt_er_niets_te_kopen(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->get('/shop')->assertForbidden();
    }
}
