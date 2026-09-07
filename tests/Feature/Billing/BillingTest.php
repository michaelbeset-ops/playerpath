<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Payments\PaymentGateway;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);
    }

    // --- Geld ---

    public function test_bedragen_worden_als_centen_opgeslagen_en_als_euros_getoond(): void
    {
        $this->assertSame(1250, Money::toCents('12,50'));
        $this->assertSame(1250, Money::toCents('12.50'));
        $this->assertSame(1250, Money::toCents('€ 12,50'));
        $this->assertSame(275000, Money::toCents('2.750,00'));
        $this->assertSame(0, Money::toCents('nul'));

        $this->assertSame('€ 12,50', Money::format(1250));
        $this->assertSame('€ 2.750,00', Money::format(275000));
        $this->assertSame('€ 0,00', Money::format(0));
        $this->assertSame('—', Money::format(null));
    }

    public function test_een_bedrag_van_12_50_wordt_geen_1249_centen(): void
    {
        // 12.50 * 100 geeft in floating point 1249.9999999999998. Precies
        // hierom mag geld geen float zijn. Zie CLAUDE.md 3.2.
        $this->actingAs($this->eigenaar)->post('/aanbod', [
            'name' => 'Keeperstraining',
            'type' => ProductType::Doorlopend->value,
            'amount' => '12,50',
            'vat_rate' => 21,
            'interval' => BillingInterval::Monthly->value,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['name' => 'Keeperstraining', 'amount_cents' => 1250]);
    }

    public function test_een_gratis_product_mag(): void
    {
        // Nul is een geldige prijs: een proefles kost niets. Er ontstaat dan
        // ook geen rekening; zie Actions\\Products\\SellProduct.
        $this->actingAs($this->eigenaar)
            ->post('/aanbod', [
                'name' => 'Proefles',
                'type' => ProductType::LosseTraining->value,
                'amount' => '0',
                'vat_rate' => 21,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['name' => 'Proefles', 'amount_cents' => 0]);
    }

    public function test_een_negatief_bedrag_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/aanbod', [
                'name' => 'Fout',
                'type' => ProductType::LosseTraining->value,
                'amount' => '-5',
                'vat_rate' => 21,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('amount');
    }

    // --- Tarieven ---

    public function test_twee_tarieven_met_dezelfde_naam_mogen_niet_binnen_een_school(): void
    {
        Product::factory()->for($this->school)->create(['name' => 'Keeperstraining']);

        $this->actingAs($this->eigenaar)
            ->post('/aanbod', [
                'name' => 'Keeperstraining',
                'type' => ProductType::Doorlopend->value,
                'amount' => '30,00',
                'vat_rate' => 21,
                'interval' => BillingInterval::Monthly->value,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_een_tarief_verwijderen_laat_lopende_abonnementen_bestaan(): void
    {
        $product = Product::factory()->for($this->school)->create();
        $speler = Player::factory()->for($this->school)->create();

        $abonnement = Subscription::factory()->for($this->school)->create([
            'player_id' => $speler->id,
            'product_id' => $product->id,
            'amount_cents' => 2750,
        ]);

        $this->actingAs($this->eigenaar)->delete('/aanbod/'.$product->id)->assertRedirect('/aanbod');

        $abonnement->refresh();

        $this->assertNull($abonnement->product_id);
        $this->assertSame(2750, $abonnement->amount_cents, 'Het bedrag hoort bij het abonnement te blijven.');
    }

    public function test_een_tariefwijziging_raakt_lopende_abonnementen_niet(): void
    {
        $product = Product::factory()->for($this->school)->create(['amount_cents' => 2750]);
        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)->post('/subscriptions', [
            'player_id' => $speler->id,
            'product_id' => $product->id,
            'payment_method' => 'directdebit',
            'starts_on' => now()->toDateString(),
        ]);

        $this->actingAs($this->eigenaar)->put('/aanbod/'.$product->id, [
            'name' => $product->name,
            'type' => ProductType::Doorlopend->value,
            'amount' => '35,00',
            'vat_rate' => 21,
            'interval' => BillingInterval::Monthly->value,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2750, Subscription::firstOrFail()->amount_cents);
        $this->assertSame(3500, $product->refresh()->amount_cents);
    }

    // --- Abonnementen ---

    public function test_een_abonnement_neemt_bedrag_en_frequentie_over_van_het_tarief(): void
    {
        $product = Product::factory()->for($this->school)->create([
            'amount_cents' => 4500,
            'interval' => BillingInterval::Quarterly,
        ]);

        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)->post('/subscriptions', [
            'player_id' => $speler->id,
            'product_id' => $product->id,
            'payment_method' => 'ideal',
            'starts_on' => now()->toDateString(),
        ])->assertRedirect();

        $abonnement = Subscription::firstOrFail();

        $this->assertSame(4500, $abonnement->amount_cents);
        $this->assertSame(BillingInterval::Quarterly, $abonnement->interval);
        // Per kwartaal maal vier = de jaarwaarde.
        $this->assertSame(18000, $abonnement->yearlyValueCents());
    }

    public function test_een_speler_of_tarief_van_een_andere_school_wordt_geweigerd(): void
    {
        $andereSchool = School::factory()->create();
        $vreemdeSpeler = Player::factory()->for($andereSchool)->create();
        $vreemdPlan = Product::factory()->for($andereSchool)->create();
        $eigenPlan = Product::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->post('/subscriptions', [
                'player_id' => $vreemdeSpeler->id,
                'product_id' => $eigenPlan->id,
                'payment_method' => 'ideal',
                'starts_on' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('player_id');

        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->post('/subscriptions', [
                'player_id' => $speler->id,
                'product_id' => $vreemdPlan->id,
                'payment_method' => 'ideal',
                'starts_on' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('subscriptions', 0);
    }

    // --- Betalingen ---

    public function test_het_overzicht_telt_ontvangen_openstaand_en_achterstallig(): void
    {
        $speler = Player::factory()->for($this->school)->create();

        Payment::factory()->for($this->school)->paid()->create(['player_id' => $speler->id, 'amount_cents' => 2750]);
        Payment::factory()->for($this->school)->paid()->create(['player_id' => $speler->id, 'amount_cents' => 2750]);
        Payment::factory()->for($this->school)->create(['player_id' => $speler->id, 'amount_cents' => 2750, 'due_on' => now()->subWeek()]);
        Payment::factory()->for($this->school)->failed()->create(['player_id' => $speler->id, 'amount_cents' => 1000]);

        $this->actingAs($this->eigenaar)
            ->get('/payments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/Payments')
                ->where('summary.revenueThisMonth', '€ 55,00')
                ->where('summary.outstanding', '€ 37,50')
                ->where('summary.overdueCount', 1)
                ->where('summary.needsAttentionCount', 1)
            );
    }

    public function test_een_betaling_op_betaald_zetten_vult_de_betaaldatum(): void
    {
        $speler = Player::factory()->for($this->school)->create();
        $betaling = Payment::factory()->for($this->school)->create(['player_id' => $speler->id]);

        $this->actingAs($this->eigenaar)
            ->patch('/payments/'.$betaling->id, ['status' => PaymentStatus::Paid->value])
            ->assertRedirect();

        $betaling->refresh();

        $this->assertSame(PaymentStatus::Paid, $betaling->status);
        $this->assertNotNull($betaling->paid_at);
    }

    public function test_een_betaling_terugzetten_wist_de_betaaldatum(): void
    {
        $speler = Player::factory()->for($this->school)->create();
        $betaling = Payment::factory()->for($this->school)->paid()->create(['player_id' => $speler->id]);

        $this->actingAs($this->eigenaar)->patch('/payments/'.$betaling->id, ['status' => PaymentStatus::Open->value]);

        $this->assertNull($betaling->refresh()->paid_at);
    }

    public function test_betalingen_blijven_binnen_de_eigen_school(): void
    {
        $andereSchool = School::factory()->create();
        $vreemdeSpeler = Player::factory()->for($andereSchool)->create();
        Payment::factory()->for($andereSchool)->paid()->create(['player_id' => $vreemdeSpeler->id, 'amount_cents' => 99999]);

        $eigenSpeler = Player::factory()->for($this->school)->create();
        Payment::factory()->for($this->school)->paid()->create(['player_id' => $eigenSpeler->id, 'amount_cents' => 2750]);

        $this->actingAs($this->eigenaar)
            ->get('/payments')
            ->assertInertia(fn ($page) => $page
                ->count('payments', 1)
                ->where('summary.revenueThisMonth', '€ 27,50')
            );
    }

    // --- Rollen ---

    public function test_een_trainer_komt_nergens_bij_de_administratie(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->get('/aanbod')->assertForbidden();
        $this->actingAs($trainer)->get('/payments')->assertForbidden();
        $this->actingAs($trainer)->get('/subscriptions')->assertForbidden();
    }

    public function test_een_ouder_ziet_alleen_de_eigen_betalingen(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $eigenKind = Player::factory()->for($this->school)->create();
        $anderKind = Player::factory()->for($this->school)->create();
        $ouder->children()->attach($eigenKind->id);

        Payment::factory()->for($this->school)->paid()->create(['player_id' => $eigenKind->id, 'description' => 'Eigen kind']);
        Payment::factory()->for($this->school)->paid()->create(['player_id' => $anderKind->id, 'description' => 'Ander kind']);

        $this->actingAs($ouder)
            ->get('/billing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/MyBilling')
                ->count('payments', 1)
                ->where('payments.0.description', 'Eigen kind')
            );

        // En niet via het schooloverzicht.
        $this->actingAs($ouder)->get('/payments')->assertForbidden();
    }

    // --- De koppeling zelf ---

    public function test_de_betaalprovider_is_nog_niet_aangesloten_en_de_app_zegt_dat(): void
    {
        $gateway = app(PaymentGateway::class);

        $this->assertFalse($gateway->isConnected());
        $this->assertSame('Mollie', $gateway->name());

        $this->actingAs($this->eigenaar)
            ->get('/payments')
            ->assertInertia(fn ($page) => $page->where('gateway.connected', false));
    }

    public function test_een_abonnement_levert_een_openstaande_rekening_op_maar_incasseert_niets(): void
    {
        $product = Product::factory()->for($this->school)->create();
        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)->post('/subscriptions', [
            'player_id' => $speler->id,
            'product_id' => $product->id,
            'payment_method' => 'directdebit',
            'starts_on' => now()->toDateString(),
        ]);

        $this->assertDatabaseCount('subscriptions', 1);

        // Er ontstaat wel een vordering — anders weet een school die per
        // overboeking int niet wie er nog moet betalen. Maar hij staat open:
        // er is geen cent verplaatst en niets doet alsof.
        $this->assertDatabaseCount('payments', 1);

        $betaling = Payment::firstOrFail();
        $this->assertSame(PaymentStatus::Open, $betaling->status);
        $this->assertNull($betaling->paid_at);
        $this->assertNull($betaling->external_reference);
    }
}
