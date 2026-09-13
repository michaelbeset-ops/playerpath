<?php

namespace Tests\Feature\Onboarding;

use App\Actions\Onboarding\SeedDemoData;
use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Onboarding\OnboardingTour;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De rondleiding: zestien stappen die elk zeggen wat je ziet, wat je ermee
 * doet, en wat je zelf kunt proberen.
 */
class OnboardingTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_elke_stap_heeft_een_titel_een_uitleg_en_een_tip(): void
    {
        $this->seed(RoleSeeder::class);

        $school = School::factory()->create();
        $stappen = app(OnboardingTour::class)->steps($school);

        $this->assertCount(16, $stappen);
        $this->assertSame('welkom', $stappen[0]['key']);
        $this->assertSame('afsluiting', $stappen[15]['key']);

        foreach ($stappen as $stap) {
            $this->assertStringStartsWith('/', $stap['url'], $stap['key']);
            $this->assertNotSame('', trim($stap['title']), $stap['key']);
            $this->assertNotSame('', trim($stap['body']), $stap['key']);
            $this->assertNotSame('', trim((string) $stap['tip']), $stap['key']);
            // Geen vaktaal zonder uitleg: "XP" staat nergens los in de tekst.
            $this->assertStringNotContainsString('XP', $stap['body'].$stap['tip'], $stap['key']);
        }
    }

    public function test_de_stappen_gaan_mee_zolang_de_rondleiding_loopt(): void
    {
        $this->seed(RoleSeeder::class);

        $school = School::factory()->create();
        $eigenaar = User::factory()->for($school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($eigenaar)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('onboarding.tour', true)
                ->count('onboarding.tourSteps', 16)
                ->has('onboarding.tourSteps.0.tip')
            );
    }

    /**
     * Twee stappen laten zien hoe een ouder zich inschrijft: de echte
     * inschrijfpagina in een kader, en de trainingen zoals een ouder ze ziet,
     * met een open voorbeeldtraining onder "Inschrijven".
     */
    public function test_de_eigenaar_ziet_hoe_een_ouder_zich_inschrijft(): void
    {
        $this->seed(RoleSeeder::class);

        $school = School::factory()->create(['slug' => 'keepersschool-test']);
        $eigenaar = User::factory()->for($school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);
        app(SeedDemoData::class)->handle($school, $eigenaar);

        $this->actingAs($eigenaar)
            ->get('/onboarding/aanmeldpagina')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('onboarding/EnrollPreview')
                ->where('url', fn ($url) => str_ends_with($url, '/inschrijven/keepersschool-test'))
            );

        $this->actingAs($eigenaar)
            ->get('/onboarding/ouderweergave/trainingen')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('trainings/Index')
                ->where('preview', true)
                ->where('isParticipant', true)
                ->count('children', 2)
                ->where('enrollable', fn ($lijst) => count($lijst) >= 1)
            );

        $trainer = User::factory()->for($school)->create();
        $trainer->assignRole(Role::Trainer->value);
        $this->actingAs($trainer)->get('/onboarding/aanmeldpagina')->assertForbidden();
    }
}
