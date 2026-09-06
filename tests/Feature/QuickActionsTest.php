<?php

namespace Tests\Feature;

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Navigation\QuickActions;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De plusknop in de balk.
 *
 * Zelfde eis als bij het hoofdmenu: er mag geen knop staan die op een 403 of
 * een 404 uitloopt. Deze test loopt elke getoonde actie echt af.
 */
class QuickActionsTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);
    }

    protected function gebruiker(Role $rol): User
    {
        $user = User::factory()->for($this->school)->create();
        $user->assignRole($rol->value);

        $speler = Player::factory()->for($this->school)->create();

        if ($rol === Role::Ouder) {
            $user->children()->attach($speler->id);
        }

        if ($rol === Role::Speler) {
            $speler->update(['user_id' => $user->id]);
        }

        return $user;
    }

    public function test_elke_getoonde_actie_werkt_ook_echt(): void
    {
        foreach ([Role::Eigenaar, Role::Trainer] as $rol) {
            $user = $this->gebruiker($rol);

            $acties = app(QuickActions::class)->for($user);

            $this->assertNotEmpty($acties, "De rol {$rol->value} ziet geen enkele actie.");

            foreach ($acties as $actie) {
                $this->actingAs($user)
                    ->get($actie['href'])
                    ->assertOk("De actie {$actie['title']} ({$actie['href']}) werkt niet voor een {$rol->value}.");
            }
        }
    }

    public function test_een_trainer_mag_geen_spelers_of_groepen_aanmaken(): void
    {
        $titels = array_column(app(QuickActions::class)->for($this->gebruiker(Role::Trainer)), 'title');

        $this->assertContains('Rapport invullen', $titels);
        $this->assertContains('Training inplannen', $titels);
        $this->assertNotContains('Speler toevoegen', $titels);
        $this->assertNotContains('Groep toevoegen', $titels);
        // De administratie is niet van de trainer.
        $this->assertNotContains('Betaling vastleggen', $titels);
    }

    public function test_een_ouder_krijgt_geen_plusknop(): void
    {
        $this->assertSame([], app(QuickActions::class)->for($this->gebruiker(Role::Ouder)));
        $this->assertSame([], app(QuickActions::class)->for($this->gebruiker(Role::Speler)));
    }

    public function test_een_uitgezette_functie_haalt_de_actie_weg(): void
    {
        $this->school->update(['features' => [Feature::Ontwikkeling->value => false]]);

        $titels = array_column(app(QuickActions::class)->for($this->gebruiker(Role::Eigenaar)), 'title');

        $this->assertNotContains('Rapport invullen', $titels);
        $this->assertContains('Speler toevoegen', $titels);
    }

    public function test_de_acties_komen_mee_met_elke_pagina(): void
    {
        $eigenaar = $this->gebruiker(Role::Eigenaar);

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where(
                'quickAdd',
                fn ($acties) => collect($acties)->pluck('title')->contains('Speler toevoegen'),
            ));
    }
}
