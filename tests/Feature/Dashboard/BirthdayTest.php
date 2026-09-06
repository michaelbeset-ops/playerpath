<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Dashboard\SchoolDashboard;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verjaardagen: op dag en maand, niet op datum.
 *
 * Het jaar in date_of_birth is het geboortejaar en zegt hier niets. De valkuil
 * zit rond de jaarwisseling: wie op 3 januari jarig is moet in december in het
 * lijstje staan, en dat gaat mis zodra je op de kale datum vergelijkt.
 */
class BirthdayTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);
    }

    protected function speler(string $naam, string $geboren): Player
    {
        return Player::factory()->for($this->school)->create([
            'first_name' => $naam,
            'last_name' => 'Jansen',
            'date_of_birth' => $geboren,
        ]);
    }

    public function test_alleen_wie_binnen_dertig_dagen_jarig_is(): void
    {
        $this->travelTo('2026-06-01');

        $this->speler('Binnenkort', '2014-06-10');
        $this->speler('Later', '2014-09-20');

        $jarig = collect(app(SchoolDashboard::class)->birthdays());

        $this->assertSame(['Binnenkort'], $jarig->pluck('first_name')->all());
    }

    public function test_de_leeftijd_is_die_van_de_komende_verjaardag(): void
    {
        $this->travelTo('2026-06-01');

        $this->speler('Sem', '2014-06-10');

        $rij = app(SchoolDashboard::class)->birthdays()[0];

        // Op 10 juni 2026 wordt hij twaalf, niet elf.
        $this->assertSame(12, $rij['turns']);
        $this->assertFalse($rij['today']);
    }

    public function test_de_jaarwisseling_breekt_het_lijstje_niet(): void
    {
        $this->travelTo('2026-12-20');

        $this->speler('Nieuwjaar', '2015-01-03');

        $jarig = collect(app(SchoolDashboard::class)->birthdays());

        $this->assertSame(['Nieuwjaar'], $jarig->pluck('first_name')->all());
        // Op 3 januari 2027 wordt hij twaalf: die van 2026 is al geweest.
        $this->assertSame(12, $jarig->first()['turns']);
    }

    public function test_vandaag_jarig_wordt_als_zodanig_gemarkeerd(): void
    {
        $this->travelTo('2026-06-10');

        $this->speler('Sem', '2014-06-10');

        $this->assertTrue(app(SchoolDashboard::class)->birthdays()[0]['today']);
    }

    public function test_niet_actieve_spelers_tellen_niet_mee(): void
    {
        $this->travelTo('2026-06-01');

        $this->speler('Gestopt', '2014-06-10')->update(['is_active' => false]);

        $this->assertSame([], app(SchoolDashboard::class)->birthdays());
    }

    public function test_het_blok_staat_standaard_op_het_dashboard(): void
    {
        $this->travelTo('2026-06-01');

        $this->speler('Sem', '2014-06-10');

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('blocks', fn ($blocks) => collect($blocks)->contains('birthdays'))
                ->count('birthdays', 1)
                ->where('birthdays.0.first_name', 'Sem')
            );
    }
}
