<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Feature;
use App\Enums\PaymentStatus;
use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Dashboard\AttentionItems;
use App\Support\Dashboard\DevelopmentOverview;
use App\Support\Dashboard\WidgetRegistry;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Het opnieuw opgebouwde eigenaar-dashboard.
 *
 * Drie dingen die deze test bewaakt: het aandacht-blok staat altijd bovenaan,
 * elk cijfer staat op precies één plek, en er wordt alleen berekend wat er ook
 * op het scherm komt.
 */
class WidgetDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);
    }

    protected function speler(string $voornaam = 'Sem'): Player
    {
        return Player::factory()->for($this->school)->keeper()->create([
            'first_name' => $voornaam,
            'last_name' => 'de Vries',
        ]);
    }

    protected function rapporteer(Player $speler, int $cijfer): void
    {
        $cijfers = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => $cijfer])
            ->all();

        $this->actingAs($this->trainer)->post("/players/{$speler->id}/reports", ['scores' => $cijfers]);
    }

    // ---------------------------------------------------------------
    // De indeling
    // ---------------------------------------------------------------

    public function test_een_eigenaar_krijgt_de_standaardindeling(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('layout', function ($layout) {
                $this->assertSame([
                    'kpi_players', 'kpi_rating', 'kpi_reports', 'kpi_revenue',
                    'development', 'finance', 'trainings', 'birthdays',
                ], $this->widgetKeys($layout));

                return true;
            }));
    }

    /**
     * Een trainer is personeel, geen directie: zijn standaarddashboard gaat over
     * zijn eigen werk. Geld krijgt hij niet eens aangeboden.
     */
    public function test_een_trainer_begint_met_zijn_eigen_werk(): void
    {
        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('layout', function ($layout) {
                $sleutels = $this->widgetKeys($layout);

                $this->assertSame(['my_trainings', 'my_players', 'kpi_reports', 'kpi_rating'], $sleutels);
                $this->assertNotContains('kpi_revenue', $sleutels);
                $this->assertNotContains('finance', $sleutels);

                return true;
            }));
    }

    /** De eigenaar houdt het schoolbrede dashboard; hij kan de trainerwidgets erbij zetten. */
    public function test_de_eigenaar_kan_de_trainerwidgets_erbij_zetten(): void
    {
        $sleutels = collect(app(WidgetRegistry::class)->describe($this->eigenaar))->pluck('key');

        $this->assertContains('my_trainings', $sleutels);
        $this->assertContains('my_players', $sleutels);
    }

    public function test_een_uitgezette_functie_haalt_de_widget_weg(): void
    {
        $this->school->update(['features' => [Feature::Ontwikkeling->value => false]]);

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('layout', function ($layout) {
                $sleutels = $this->widgetKeys($layout);

                $this->assertNotContains('development', $sleutels);
                $this->assertNotContains('kpi_rating', $sleutels);
                $this->assertContains('kpi_players', $sleutels);

                return true;
            }));
    }

    public function test_een_weggehaalde_widget_wordt_ook_niet_berekend(): void
    {
        $this->eigenaar->forceFill([
            'dashboard_layout' => ['widgets' => [['key' => 'kpi_players', 'x' => 0, 'y' => 0, 'w' => 3]]],
        ])->save();

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(function ($page) {
                $props = $page->toArray()['props'];

                $this->assertSame(['kpi_players'], $this->widgetKeys($props['layout']));
                $this->assertNull($props['widgets']['development']);
                $this->assertNull($props['widgets']['finance']);
                $this->assertNotNull($props['widgets']['kpi_players']);
            });
    }

    public function test_een_onbekende_widget_in_de_opslag_wordt_genegeerd(): void
    {
        $this->eigenaar->forceFill([
            'dashboard_layout' => ['widgets' => [
                ['key' => 'bestaat_niet', 'x' => 0, 'y' => 0, 'w' => 3],
                ['key' => 'kpi_players', 'x' => 0, 'y' => 0, 'w' => 99],
            ]],
        ])->save();

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('layout', function ($layout) {
                $rijen = is_array($layout) ? $layout : iterator_to_array($layout);

                $this->assertSame(['kpi_players'], $this->widgetKeys($rijen));
                // Een breedte die niet mag valt terug op de standaard.
                $this->assertSame(3, $rijen[0]['size']);

                return true;
            }));
    }

    // ---------------------------------------------------------------
    // Het aandacht-blok
    // ---------------------------------------------------------------

    public function test_zonder_signalen_is_het_aandacht_blok_leeg(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->count('attention', 0));
    }

    public function test_mislukte_betalingen_staan_bovenaan(): void
    {
        $speler = $this->speler();

        Payment::factory()->for($this->school)->create([
            'player_id' => $speler->id,
            'status' => PaymentStatus::Failed,
            'amount_cents' => 2750,
        ]);

        $items = collect(app(AttentionItems::class)->for($this->eigenaar));

        $this->assertSame('failed_payments', $items->first()['key']);
        $this->assertStringContainsString('27,50', $items->first()['body']);
    }

    public function test_een_rekening_binnen_de_termijn_vraagt_nog_niet_om_actie(): void
    {
        $speler = $this->speler();

        Payment::factory()->for($this->school)->create([
            'player_id' => $speler->id,
            'status' => PaymentStatus::Open,
            'due_on' => now()->subDays(3),
        ]);

        $sleutels = collect(app(AttentionItems::class)->for($this->eigenaar))->pluck('key');

        $this->assertNotContains('overdue_payments', $sleutels);

        // Voorbij de termijn wél.
        Payment::query()->update(['due_on' => now()->subDays(AttentionItems::OPENSTAAND_NA_DAGEN + 1)]);

        $this->assertContains('overdue_payments', collect(app(AttentionItems::class)->for($this->eigenaar))->pluck('key'));
    }

    public function test_een_trainer_krijgt_geen_betaalsignalen(): void
    {
        $speler = $this->speler();

        Payment::factory()->for($this->school)->create([
            'player_id' => $speler->id,
            'status' => PaymentStatus::Failed,
        ]);

        $sleutels = collect(app(AttentionItems::class)->for($this->trainer))->pluck('key');

        // Daar kan hij niets mee.
        $this->assertNotContains('failed_payments', $sleutels);
    }

    public function test_een_speler_zonder_recent_rapport_komt_in_het_blok(): void
    {
        $speler = $this->speler('Vergeten');

        $items = collect(app(AttentionItems::class)->for($this->eigenaar));
        $stil = $items->firstWhere('key', 'silent_players');

        $this->assertNotNull($stil);
        $this->assertStringContainsString('Vergeten', $stil['title']);
        $this->assertSame('/players/'.$speler->id.'/reports/create', $stil['href']);
    }

    // ---------------------------------------------------------------
    // Ontwikkeling
    // ---------------------------------------------------------------

    public function test_de_stijgers_komen_uit_twee_rapporten_in_de_periode(): void
    {
        $stijger = $this->speler('Stijger');
        $daler = $this->speler('Daler');

        $this->travelTo(now()->subDays(20));
        $this->rapporteer($stijger, 5);
        $this->rapporteer($daler, 8);

        $this->travelBack();
        $this->rapporteer($stijger, 8);
        $this->rapporteer($daler, 5);

        $overzicht = app(DevelopmentOverview::class)->for();

        $this->assertSame('Stijger de Vries', $overzicht['risers'][0]['name']);
        $this->assertSame(30, $overzicht['risers'][0]['change']);

        $this->assertSame('Daler de Vries', $overzicht['fallers'][0]['name']);
        $this->assertSame(-30, $overzicht['fallers'][0]['change']);
    }

    public function test_een_speler_met_een_rapport_telt_niet_als_stijger(): void
    {
        // Met één rapport valt er niets te vergelijken.
        $this->rapporteer($this->speler(), 8);

        $overzicht = app(DevelopmentOverview::class)->for();

        $this->assertSame([], $overzicht['risers']);
        $this->assertSame([], $overzicht['fallers']);
    }

    public function test_de_dekking_telt_wie_een_actueel_rapport_heeft(): void
    {
        $this->rapporteer($this->speler('Met'), 7);
        $this->speler('Zonder');

        $dekking = app(DevelopmentOverview::class)->for()['coverage'];

        $this->assertSame(50, $dekking['percentage']);
        $this->assertSame(1, $dekking['current']);
        $this->assertSame(2, $dekking['total']);
    }

    // ---------------------------------------------------------------
    // Geen dubbele cijfers
    // ---------------------------------------------------------------

    public function test_omzet_staat_als_kerncijfer_en_niet_ook_in_het_financiele_vak(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(function ($page) {
                $widgets = $page->toArray()['props']['widgets'];

                $this->assertNotNull($widgets['kpi_revenue']);
                // Elk cijfer op precies één plek.
                $this->assertArrayNotHasKey('revenueThisMonth', $widgets['finance']);
            });
    }

    public function test_de_registry_biedt_alleen_aan_wat_je_mag_zien(): void
    {
        $voorTrainer = collect(app(WidgetRegistry::class)->describe($this->trainer))->pluck('key');

        $this->assertNotContains('finance', $voorTrainer);
        $this->assertNotContains('kpi_revenue', $voorTrainer);

        $voorEigenaar = collect(app(WidgetRegistry::class)->describe($this->eigenaar))->pluck('key');

        $this->assertContains('finance', $voorEigenaar);
    }
}
