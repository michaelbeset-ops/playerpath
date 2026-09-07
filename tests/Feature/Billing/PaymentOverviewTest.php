<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het financiële overzicht: tabbladen, periode en het totaal onder de kop.
 *
 * Het punt van deze test is dat het totaal precies de rijen telt die je
 * eronder ziet. Een boekhouder die een verschil van drie euro vindt belt niet
 * over drie euro maar over de vraag of hij het systeem kan vertrouwen.
 */
class PaymentOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->speler = Player::factory()->for($this->school)->create();
    }

    protected function betaling(array $velden = []): Payment
    {
        return Payment::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            ...$velden,
        ]);
    }

    public function test_het_totaal_rekent_de_btw_per_regel_terug(): void
    {
        // Verschillende tarieven door elkaar: één percentage van het totaal
        // afhalen zou een getal opleveren dat nergens op slaat.
        $this->betaling(['amount_cents' => 12100, 'vat_rate' => 21, 'status' => PaymentStatus::Paid, 'paid_at' => now()]);
        $this->betaling(['amount_cents' => 10900, 'vat_rate' => 9, 'status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->actingAs($this->eigenaar)
            ->get('/payments?tab=paid&period=this_month')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totals.count', 2)
                ->where('totals.total', '€ 230,00')
                // 12100 / 1,21 = 10000 en 10900 / 1,09 = 10000.
                ->where('totals.excl_vat', '€ 200,00')
                ->where('totals.vat', '€ 30,00')
            );
    }

    public function test_ontvangen_filtert_op_de_dag_dat_het_geld_binnenkwam(): void
    {
        // Vorige maand gefactureerd, deze maand betaald: dat hoort bij de
        // omzet van deze maand, niet van vorige.
        $this->betaling([
            'amount_cents' => 5000,
            'status' => PaymentStatus::Paid,
            'due_on' => now()->subMonthNoOverflow()->startOfMonth(),
            'paid_at' => now(),
        ]);

        $this->actingAs($this->eigenaar)
            ->get('/payments?tab=paid&period=this_month')
            ->assertInertia(fn ($page) => $page->where('totals.count', 1));

        $this->actingAs($this->eigenaar)
            ->get('/payments?tab=paid&period=last_month')
            ->assertInertia(fn ($page) => $page->where('totals.count', 0));
    }

    public function test_te_laat_en_gepland_zijn_allebei_openstaand_maar_niet_hetzelfde(): void
    {
        $this->betaling(['status' => PaymentStatus::Open, 'due_on' => now()->subDays(3)]);
        $this->betaling(['status' => PaymentStatus::Open, 'due_on' => now()->addDays(10)]);

        $this->actingAs($this->eigenaar)
            ->get('/payments?tab=overdue&period=all')
            ->assertInertia(fn ($page) => $page->where('totals.count', 1));

        $this->actingAs($this->eigenaar)
            ->get('/payments?tab=planned&period=all')
            ->assertInertia(fn ($page) => $page->where('totals.count', 1));

        $this->actingAs($this->eigenaar)
            ->get('/payments?tab=open&period=all')
            ->assertInertia(fn ($page) => $page->where('totals.count', 2));
    }

    public function test_zoeken_kijkt_naar_de_speler_en_de_omschrijving(): void
    {
        $this->speler->update(['first_name' => 'Sem', 'last_name' => 'de Vries']);

        $this->betaling(['description' => 'Zomerkamp', 'status' => PaymentStatus::Open, 'due_on' => now()]);

        $ander = Player::factory()->for($this->school)->create(['first_name' => 'Noud', 'last_name' => 'Jansen']);
        Payment::factory()->for($this->school)->create([
            'player_id' => $ander->id,
            'description' => 'Keeperstraining',
            'status' => PaymentStatus::Open,
            'due_on' => now(),
        ]);

        $this->actingAs($this->eigenaar)
            ->get('/payments?period=all&search=Sem')
            ->assertInertia(fn ($page) => $page->where('totals.count', 1));

        $this->actingAs($this->eigenaar)
            ->get('/payments?period=all&search=kamp')
            ->assertInertia(fn ($page) => $page->where('totals.count', 1));
    }

    public function test_een_onzinnig_tabblad_valt_terug_op_alles(): void
    {
        $this->betaling(['status' => PaymentStatus::Open, 'due_on' => now()]);

        $this->actingAs($this->eigenaar)
            ->get('/payments?tab=onzin&period=onzin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.tab', 'all')
                ->where('filters.period', 'this_month')
            );
    }

    public function test_betalingen_van_een_andere_school_tellen_niet_mee(): void
    {
        $andere = School::factory()->create();
        $vreemdeSpeler = app(Tenancy::class)->forSchool($andere, fn () => Player::factory()->for($andere)->create());

        Payment::factory()->for($andere)->create([
            'player_id' => $vreemdeSpeler->id,
            'amount_cents' => 99900,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->betaling(['amount_cents' => 5000, 'status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->actingAs($this->eigenaar)
            ->get('/payments?tab=paid&period=this_month')
            ->assertInertia(fn ($page) => $page
                ->where('totals.count', 1)
                ->where('totals.total', '€ 50,00')
            );
    }
}
