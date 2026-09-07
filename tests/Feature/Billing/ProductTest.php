<?php

namespace Tests\Feature\Billing;

use App\Enums\AttendanceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Producten: wat een school verkoopt, en wat een speler ervan afneemt.
 *
 * Het hart hiervan is de rittenkaart. Een kaart zonder afschrijven is een
 * prijslijst; wat hem een kaart maakt is dat er een beurt af gaat zodra een
 * trainer iemand aanwezig meldt — en dat die beurt terugkomt als hij zich
 * vergist.
 */
class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $trainer;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        $this->speler = Player::factory()->for($this->school)->create();
    }

    protected function rittenkaart(int $beurten = 10, ?int $maanden = 6): Product
    {
        return Product::factory()->for($this->school)->rittenkaart($beurten, $maanden)->create([
            'name' => $beurten.'-rittenkaart',
            'amount_cents' => 11000,
        ]);
    }

    protected function training(): Training
    {
        $groep = Group::factory()->for($this->school)->create();
        $groep->players()->attach($this->speler->id);

        return Training::factory()->for($this->school)->for($groep)->create();
    }

    // ---------------------------------------------------------------
    // Het aanbod
    // ---------------------------------------------------------------

    public function test_een_rittenkaart_aanmaken(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/aanbod', [
                'name' => '10-rittenkaart',
                'type' => ProductType::Rittenkaart->value,
                'amount' => '110,00',
                'vat_rate' => 9,
                'credits' => 10,
                'validity_months' => 6,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => '10-rittenkaart',
            'type' => 'rittenkaart',
            'amount_cents' => 11000,
            'vat_rate' => 9,
            'credits' => 10,
            'validity_months' => 6,
            // Geen frequentie: die hoort alleen bij een abonnement.
            'interval' => null,
        ]);
    }

    public function test_een_rittenkaart_zonder_beurten_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/aanbod', [
                'name' => 'Kaart',
                'type' => ProductType::Rittenkaart->value,
                'amount' => '110,00',
                'vat_rate' => 21,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('credits');
    }

    public function test_velden_die_niet_bij_het_soort_horen_worden_leeggemaakt(): void
    {
        // Een kamp met een maandfrequentie en beurten is een veld dat later
        // niemand meer snapt.
        $this->actingAs($this->eigenaar)->post('/aanbod', [
            'name' => 'Zomerkamp',
            'type' => ProductType::Kamp->value,
            'amount' => '150,00',
            'vat_rate' => 21,
            'credits' => 10,
            'interval' => 'monthly',
            'starts_on' => now()->addMonth()->toDateString(),
            'ends_on' => now()->addMonth()->addDays(4)->toDateString(),
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $product = Product::firstWhere('name', 'Zomerkamp');

        $this->assertNull($product->credits);
        $this->assertNull($product->interval);
    }

    public function test_het_bedrag_exclusief_btw_klopt(): void
    {
        $product = Product::factory()->for($this->school)->create(['amount_cents' => 12100, 'vat_rate' => 21]);

        $this->assertSame(10000, $product->amountExclVatCents());
        $this->assertSame(2100, $product->vatCents());
    }

    public function test_een_trainer_komt_niet_bij_de_producten(): void
    {
        $this->actingAs($this->trainer)->get('/aanbod')->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Toekennen
    // ---------------------------------------------------------------

    public function test_toekennen_maakt_een_aankoop_en_een_openstaande_rekening(): void
    {
        $product = $this->rittenkaart();

        $this->actingAs($this->eigenaar)
            ->post("/players/{$this->speler->id}/purchases", ['product_id' => $product->id])
            ->assertRedirect();

        $aankoop = Purchase::firstOrFail();

        $this->assertSame('10-rittenkaart', $aankoop->name);
        $this->assertSame(11000, $aankoop->amount_cents);
        $this->assertSame(10, $aankoop->credits_total);
        $this->assertSame(0, $aankoop->credits_used);
        $this->assertSame(now()->addMonths(6)->toDateString(), $aankoop->expires_on->toDateString());

        // Ook zonder betaalprovider ontstaat er een rekening, anders weet een
        // school die per overboeking int niet wie er nog moet betalen.
        $rekening = Payment::firstOrFail();
        $this->assertSame(PaymentStatus::Open, $rekening->status);
        $this->assertSame($aankoop->id, $rekening->purchase_id);
    }

    public function test_een_prijswijziging_raakt_een_gedane_aankoop_niet(): void
    {
        $product = $this->rittenkaart();

        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/purchases", ['product_id' => $product->id]);

        $product->update(['amount_cents' => 15000, 'name' => 'Andere naam']);

        $aankoop = Purchase::firstOrFail();
        $this->assertSame(11000, $aankoop->amount_cents);
        $this->assertSame('10-rittenkaart', $aankoop->name);
    }

    public function test_een_gratis_product_levert_geen_rekening_op(): void
    {
        $product = Product::factory()->for($this->school)->create([
            'type' => ProductType::LosseTraining,
            'amount_cents' => 0,
            'interval' => null,
        ]);

        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/purchases", ['product_id' => $product->id]);

        $this->assertSame(1, Purchase::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_een_abonnement_ken_je_niet_toe_als_aankoop(): void
    {
        $product = Product::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->post("/players/{$this->speler->id}/purchases", ['product_id' => $product->id])
            ->assertStatus(422);
    }

    public function test_een_product_van_een_andere_school_wordt_geweigerd(): void
    {
        $vreemd = Product::factory()->for(School::factory()->create())->rittenkaart()->create();

        $this->actingAs($this->eigenaar)
            ->post("/players/{$this->speler->id}/purchases", ['product_id' => $vreemd->id])
            ->assertSessionHasErrors('product_id');
    }

    public function test_intrekken_verwijdert_niets_maar_sluit_de_kaart(): void
    {
        $product = $this->rittenkaart();
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/purchases", ['product_id' => $product->id]);

        $aankoop = Purchase::firstOrFail();

        $this->actingAs($this->eigenaar)
            ->delete("/players/{$this->speler->id}/purchases/{$aankoop->id}")
            ->assertRedirect();

        $this->assertSame('cancelled', $aankoop->fresh()->status);
        // De rekening blijft: die is verstuurd en hoort in de historie.
        $this->assertSame(1, Payment::count());
    }

    // ---------------------------------------------------------------
    // Beurten
    // ---------------------------------------------------------------

    public function test_aanwezig_melden_haalt_een_beurt_van_de_kaart(): void
    {
        $product = $this->rittenkaart();
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/purchases", ['product_id' => $product->id]);

        $training = $this->training();

        $this->actingAs($this->trainer)
            ->patch("/trainings/{$training->id}/attendance/{$this->speler->id}", ['status' => AttendanceStatus::Present->value])
            ->assertRedirect();

        $this->assertSame(1, Purchase::firstOrFail()->credits_used);
    }

    public function test_afmelden_geeft_de_beurt_terug(): void
    {
        $product = $this->rittenkaart();
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/purchases", ['product_id' => $product->id]);

        $training = $this->training();
        $url = "/trainings/{$training->id}/attendance/{$this->speler->id}";

        $this->actingAs($this->trainer)->patch($url, ['status' => AttendanceStatus::Present->value]);
        $this->assertSame(1, Purchase::firstOrFail()->credits_used);

        // De trainer vergist zich en zet hem op afwezig.
        $this->actingAs($this->trainer)->patch($url, ['status' => AttendanceStatus::Absent->value]);
        $this->assertSame(0, Purchase::firstOrFail()->credits_used);
    }

    public function test_afwezig_melden_kost_geen_beurt(): void
    {
        $product = $this->rittenkaart();
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/purchases", ['product_id' => $product->id]);

        $training = $this->training();

        $this->actingAs($this->trainer)
            ->patch("/trainings/{$training->id}/attendance/{$this->speler->id}", ['status' => AttendanceStatus::Absent->value]);

        $this->assertSame(0, Purchase::firstOrFail()->credits_used);
    }

    public function test_een_lege_kaart_gaat_dicht(): void
    {
        $product = $this->rittenkaart(beurten: 1);
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/purchases", ['product_id' => $product->id]);

        $training = $this->training();

        $this->actingAs($this->trainer)
            ->patch("/trainings/{$training->id}/attendance/{$this->speler->id}", ['status' => AttendanceStatus::Present->value]);

        $kaart = Purchase::firstOrFail();
        $this->assertSame('used', $kaart->status);
        $this->assertSame(0, $kaart->creditsLeft());
    }

    public function test_zonder_kaart_gaat_afvinken_gewoon_door(): void
    {
        $training = $this->training();

        // Aanwezigheid vastleggen mag nooit stuklopen op de administratie.
        $this->actingAs($this->trainer)
            ->patch("/trainings/{$training->id}/attendance/{$this->speler->id}", ['status' => AttendanceStatus::Present->value])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'player_id' => $this->speler->id,
            'status' => AttendanceStatus::Present->value,
            'purchase_id' => null,
        ]);
    }

    public function test_een_verlopen_kaart_levert_geen_beurten_meer(): void
    {
        $product = $this->rittenkaart();
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/purchases", ['product_id' => $product->id]);

        // Zeven maanden later is de kaart van zes maanden op.
        $this->travel(7)->months();

        $training = $this->training();

        $this->actingAs($this->trainer)
            ->patch("/trainings/{$training->id}/attendance/{$this->speler->id}", ['status' => AttendanceStatus::Present->value]);

        $this->assertSame(0, Purchase::firstOrFail()->credits_used);
    }

    public function test_de_oudste_kaart_gaat_eerst(): void
    {
        $kort = $this->rittenkaart(beurten: 5, maanden: 1);
        $lang = Product::factory()->for($this->school)->rittenkaart(5, 12)->create([
            'name' => 'Lange kaart',
            'amount_cents' => 11000,
        ]);

        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/purchases", ['product_id' => $lang->id]);
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/purchases", ['product_id' => $kort->id]);

        $training = $this->training();

        $this->actingAs($this->trainer)
            ->patch("/trainings/{$training->id}/attendance/{$this->speler->id}", ['status' => AttendanceStatus::Present->value]);

        // De kaart die als eerste verloopt wordt als eerste opgemaakt; anders
        // raakt een ouder beurten kwijt die hij had kunnen gebruiken.
        $this->assertSame(1, Purchase::where('product_id', $kort->id)->firstOrFail()->credits_used);
        $this->assertSame(0, Purchase::where('product_id', $lang->id)->firstOrFail()->credits_used);
    }

    public function test_de_aankopen_staan_op_de_spelerspagina(): void
    {
        $product = $this->rittenkaart();
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/purchases", ['product_id' => $product->id]);

        $this->actingAs($this->eigenaar)
            ->get("/players/{$this->speler->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('purchases', 1)
                ->where('purchases.0.credits_left', 10)
                ->where('purchases.0.name', '10-rittenkaart')
            );
    }
}
