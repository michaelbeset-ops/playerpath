<?php

namespace Tests\Feature\Billing;

use App\Enums\DiscountKind;
use App\Enums\OrderLineType;
use App\Enums\PaymentOptionType;
use App\Enums\PlayerPosition;
use App\Enums\ProductAudience;
use App\Enums\Role;
use App\Models\Consent;
use App\Models\ConsentDocument;
use App\Models\Discount;
use App\Models\Mandate;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\PaymentOption;
use App\Models\Product;
use App\Models\School;
use App\Models\User;
use App\Models\WaitlistInvitation;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Het datamodel van inschrijven en betalen (onderdeel 2): betaalvormen onder
 * een aanbod, orders met regels, mandaten zonder IBAN, kortingen,
 * toestemmingen met versie en wachtlijst-uitnodigingen. Alles per school.
 */
class EnrollmentDataModelTest extends TestCase
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

    public function test_een_aanbod_heeft_altijd_een_standaard_betaalvorm(): void
    {
        $blok = Product::factory()->for($this->school)->blok()->create(['amount_cents' => 12000]);

        $this->assertSame(1, $blok->paymentOptions()->count());
        $this->assertTrue($blok->defaultPaymentOption->is_default);
        $this->assertSame(PaymentOptionType::Eenmalig, $blok->defaultPaymentOption->type);
        $this->assertSame(12000, $blok->defaultPaymentOption->amount_cents);
        $this->assertSame('€ 120,00 ineens', $blok->defaultPaymentOption->describe());
    }

    public function test_meerdere_betaalvormen_en_de_standaard_komt_op_het_aanbod(): void
    {
        $blok = Product::factory()->for($this->school)->blok()->create();

        $blok->syncPaymentOptions([
            ['type' => 'eenmalig', 'amount_cents' => 12000],
            ['type' => 'termijnen', 'amount_cents' => 4000, 'installments' => 3],
            ['type' => 'abonnement', 'amount_cents' => 3000, 'interval' => 'monthly'],
        ]);

        $opties = $blok->paymentOptions()->get();

        $this->assertCount(3, $opties);
        $this->assertSame([true, false, false], $opties->pluck('is_default')->all());
        $this->assertSame('3 × € 40,00 per maand', $opties[1]->describe());
        $this->assertSame(12000, $opties[1]->totalCents());
        $this->assertSame('€ 30,00 per maand', $opties[2]->describe());
        $this->assertNull($opties[2]->totalCents());

        // De standaard staat ook op het aanbod, voor alles wat er al was.
        $this->assertSame('eenmalig', $blok->refresh()->billing_type->value);
        $this->assertSame(12000, $blok->amount_cents);

        // Abonnement als standaard maakt het aanbod "per maand".
        $blok->syncPaymentOptions([['type' => 'abonnement', 'amount_cents' => 3000, 'interval' => 'monthly']]);

        $this->assertSame('maandelijks', $blok->refresh()->billing_type->value);
        $this->assertSame('monthly', $blok->interval->value);
    }

    public function test_het_aanbodformulier_slaat_extra_betaalvormen_en_de_doelgroep_op(): void
    {
        $this->actingAs($this->eigenaar)->post('/aanbod', [
            'name' => 'Keepersblok herfst',
            'type' => 'blok',
            'billing_type' => 'eenmalig',
            'amount' => '120,00',
            'vat_rate' => 9,
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeeks(7)->toDateString(),
            'status' => 'open',
            'stops_at_end' => true,
            'is_active' => true,
            'audience' => 'keeper',
            'sessions_count' => 6,
            'payment_options' => [
                ['type' => 'termijnen', 'amount' => '40,00', 'installments' => 3],
            ],
        ])->assertRedirect('/aanbod');

        $blok = Product::where('name', 'Keepersblok herfst')->firstOrFail();

        $this->assertSame(ProductAudience::Keeper, $blok->audience);
        $this->assertSame(6, $blok->sessions_count);
        $this->assertSame(2, $blok->paymentOptions()->count());
        $this->assertSame(4000, $blok->paymentOptions()->where('is_default', false)->first()->amount_cents);
        $this->assertTrue($blok->fitsPosition(PlayerPosition::Keeper));
        $this->assertFalse($blok->fitsPosition(PlayerPosition::Field));
    }

    public function test_een_order_bundelt_regels_en_korting_is_een_regel_met_een_negatief_bedrag(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $order = Order::factory()->for($this->school)->create(['user_id' => $ouder->id]);

        OrderLine::factory()->for($this->school)->create(['order_id' => $order->id, 'type' => OrderLineType::Offering, 'amount_cents' => 12000]);
        OrderLine::factory()->for($this->school)->create(['order_id' => $order->id, 'type' => OrderLineType::Offering, 'description' => 'Tweede kind', 'amount_cents' => 12000]);
        OrderLine::factory()->for($this->school)->create(['order_id' => $order->id, 'type' => OrderLineType::RegistrationFee, 'description' => 'Inschrijfgeld', 'amount_cents' => 2500]);
        OrderLine::factory()->for($this->school)->create(['order_id' => $order->id, 'type' => OrderLineType::Discount, 'description' => 'Gezinskorting', 'amount_cents' => -1200]);

        $order->recalculate();

        $this->assertSame(25300, $order->total_cents);
        $this->assertSame(1200, $order->discount_cents);
        $this->assertSame($ouder->id, $order->user->id);
        $this->assertCount(1, $ouder->orders);
    }

    public function test_een_korting_rekent_in_centen_en_nooit_meer_dan_het_bedrag(): void
    {
        $procent = Discount::factory()->for($this->school)->create(['kind' => DiscountKind::Family, 'percent' => 10, 'code' => null]);
        $vast = Discount::factory()->for($this->school)->create(['kind' => DiscountKind::Code, 'code' => 'WELKOM', 'percent' => null, 'amount_cents' => 5000]);

        $this->assertSame(1250, $procent->applyTo(12500));
        $this->assertSame(3000, $vast->applyTo(3000));
        $this->assertTrue($vast->isUsable());

        $vast->update(['max_uses' => 1, 'uses' => 1]);
        $this->assertFalse($vast->refresh()->isUsable());

        $vast->update(['max_uses' => null, 'valid_until' => now()->subDay()]);
        $this->assertFalse($vast->refresh()->isUsable());
    }

    public function test_een_toestemming_hangt_aan_een_documentversie(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $document = ConsentDocument::put('avg', 'Privacy', 'Eerste tekst.', true);

        $toestemming = Consent::factory()->for($this->school)->create([
            'user_id' => $ouder->id,
            'consent_document_id' => $document->id,
            'version' => $document->version,
        ]);

        $this->assertTrue($toestemming->isCurrent());

        // Een andere tekst is een nieuwe versie; de oude toestemming geldt niet meer.
        ConsentDocument::put('avg', 'Privacy', 'Andere tekst.', true);

        $this->assertFalse($toestemming->fresh()->load('document')->isCurrent());
    }

    public function test_een_mandaat_bewaart_alleen_kenmerken_en_een_uitnodiging_krijgt_een_token(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $mandaat = Mandate::factory()->for($this->school)->create(['user_id' => $ouder->id]);

        $this->assertTrue($mandaat->isValid());
        $this->assertFalse(Schema::hasColumn('mandates', 'iban'));
        $this->assertCount(1, $ouder->mandates);

        $uitnodiging = WaitlistInvitation::factory()->for($this->school)->create();

        $this->assertSame(48, strlen($uitnodiging->token));
        $this->assertTrue($uitnodiging->isOpen());
    }

    public function test_alles_is_per_school_afgeschermd(): void
    {
        $andere = School::factory()->create();

        app(Tenancy::class)->forSchool($andere, function () use ($andere) {
            $aanbod = Product::factory()->for($andere)->blok()->create();
            $ouder = User::factory()->for($andere)->create();
            $order = Order::factory()->for($andere)->create(['user_id' => $ouder->id]);
            OrderLine::factory()->for($andere)->create(['order_id' => $order->id, 'product_id' => $aanbod->id]);
            Discount::factory()->for($andere)->create();
            Mandate::factory()->for($andere)->create(['user_id' => $ouder->id]);
        });

        app(Tenancy::class)->set($this->school);

        $this->assertSame(0, PaymentOption::count());
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderLine::count());
        $this->assertSame(0, Discount::count());
        $this->assertSame(0, Mandate::count());

        // En binnen de eigen school wordt school_id automatisch gezet.
        $order = Order::create(['user_id' => $this->eigenaar->id]);
        $this->assertSame($this->school->id, $order->school_id);
    }
}
