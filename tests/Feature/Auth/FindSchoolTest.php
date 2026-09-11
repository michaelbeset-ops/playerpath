<?php

namespace Tests\Feature\Auth;

use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Nog geen account? Zoek je school": van de inlogpagina naar de
 * inschrijfpagina van een school, zonder dat er meer over onze klanten
 * naar buiten gaat dan de naam van wie je toch al zocht.
 */
class FindSchoolTest extends TestCase
{
    use RefreshDatabase;

    public function test_de_inlogpagina_wijst_naar_het_zoeken(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/scholen/zoeken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/FindSchool')->count('schools', 0));
    }

    public function test_zoeken_vindt_alleen_actieve_scholen_op_naam(): void
    {
        School::factory()->create(['name' => 'Keepersschool Rob', 'slug' => 'keepersschool-rob', 'is_active' => true]);
        School::factory()->create(['name' => 'Keepersacademie Zuid', 'slug' => 'keepersacademie-zuid', 'is_active' => false]);
        School::factory()->create(['name' => 'Voetbalschool Noord', 'slug' => 'voetbalschool-noord', 'is_active' => true]);

        $this->get('/scholen/zoeken?q=keep')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('schools', 1)
                ->where('schools.0.name', 'Keepersschool Rob')
                ->where('schools.0.href', '/inschrijven/keepersschool-rob')
            );

        // Eén letter is geen zoekopdracht: dan zou de hele lijst zichtbaar zijn.
        $this->get('/scholen/zoeken?q=k')
            ->assertInertia(fn ($page) => $page->count('schools', 0));
    }
}
