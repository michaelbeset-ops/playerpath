<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Je eigen indeling opslaan.
 *
 * Wat hier binnenkomt wordt niet vertrouwd. Een aangepast verzoek mag nooit een
 * dashboard opleveren dat cijfers toont waar iemand niet bij mag, en een oude
 * of verzonnen indeling mag het scherm niet stukmaken.
 */
class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $trainer;

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
    }

    public function test_een_indeling_wordt_opgeslagen(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/dashboard/indeling', ['widgets' => [
                ['key' => 'development', 'x' => 0, 'y' => 0, 'w' => 12],
                ['key' => 'kpi_players', 'x' => 0, 'y' => 7, 'w' => 3],
            ]])
            ->assertRedirect();

        $this->assertSame([
            ['key' => 'development', 'x' => 0, 'y' => 0, 'w' => 12],
            ['key' => 'kpi_players', 'x' => 0, 'y' => 7, 'w' => 3],
        ], $this->eigenaar->fresh()->dashboard_layout['widgets']);

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('layout', function ($layout) {
                $this->assertSame(['development', 'kpi_players'], $this->widgetKeys($layout));

                return true;
            }));
    }

    public function test_een_trainer_kan_de_geldwidgets_niet_via_het_verzoek_toevoegen(): void
    {
        $this->actingAs($this->trainer)
            ->patch('/dashboard/indeling', ['widgets' => [
                ['key' => 'finance', 'x' => 0, 'y' => 0, 'w' => 4],
                ['key' => 'kpi_revenue', 'x' => 4, 'y' => 0, 'w' => 3],
                ['key' => 'kpi_players', 'x' => 0, 'y' => 0, 'w' => 3],
            ]])
            ->assertRedirect();

        $bewaard = collect($this->trainer->fresh()->dashboard_layout['widgets'])->pluck('key');

        // Het formulier omzeilen helpt niet.
        $this->assertSame(['kpi_players'], $bewaard->all());
    }

    public function test_een_onbekende_widget_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/dashboard/indeling', ['widgets' => [
                ['key' => 'bestaat_niet', 'x' => 0, 'y' => 0, 'w' => 3],
            ]])
            ->assertSessionHasErrors('widgets.0.key');
    }

    public function test_een_breedte_die_niet_mag_valt_terug_op_de_standaard(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/dashboard/indeling', ['widgets' => [
                ['key' => 'kpi_players', 'x' => 0, 'y' => 0, 'w' => 11],
            ]])
            ->assertRedirect();

        // Elf van de twaalf bestaat niet voor een kerncijfer.
        $this->assertSame(3, $this->eigenaar->fresh()->dashboard_layout['widgets'][0]['w']);
    }

    public function test_dezelfde_widget_twee_keer_wordt_er_een(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/dashboard/indeling', ['widgets' => [
                ['key' => 'kpi_players', 'x' => 0, 'y' => 0, 'w' => 3],
                ['key' => 'kpi_players', 'x' => 3, 'y' => 0, 'w' => 3],
            ]])
            ->assertRedirect();

        $this->assertCount(1, $this->eigenaar->fresh()->dashboard_layout['widgets']);
    }

    public function test_een_leeg_dashboard_mag(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/dashboard/indeling', ['widgets' => []])
            ->assertRedirect();

        $this->assertSame([], $this->eigenaar->fresh()->dashboard_layout['widgets']);

        // En dan staat er ook echt niets; niet stiekem de standaard.
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->count('layout', 0));
    }

    public function test_herstellen_zet_de_standaard_terug(): void
    {
        $this->eigenaar->forceFill([
            'dashboard_layout' => ['widgets' => [['key' => 'kpi_players', 'x' => 0, 'y' => 0, 'w' => 3]]],
        ])->save();

        $this->actingAs($this->eigenaar)
            ->delete('/dashboard/indeling')
            ->assertRedirect();

        // Leeggooien en niet de standaard wegschrijven: zo verschijnt een
        // widget die er later bijkomt vanzelf.
        $this->assertNull($this->eigenaar->fresh()->dashboard_layout);

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('layout', fn ($layout) => count($this->widgetKeys($layout)) === 8));
    }

    public function test_de_indeling_geldt_per_gebruiker(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/dashboard/indeling', ['widgets' => [['key' => 'kpi_players', 'x' => 0, 'y' => 0, 'w' => 3]]]);

        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('layout', fn ($layout) => count($this->widgetKeys($layout)) > 1));
    }

    public function test_een_ouder_heeft_hier_niets_te_zoeken(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $ouder->children()->attach(Player::factory()->for($this->school)->create()->id);

        $this->actingAs($ouder)->patch('/dashboard/indeling', ['widgets' => []])->assertNotFound();
        $this->actingAs($ouder)->delete('/dashboard/indeling')->assertNotFound();
    }
}
