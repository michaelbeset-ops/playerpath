<?php

namespace Tests\Feature\Onboarding;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Support\Onboarding\OnboardingTour;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De rondleiding: veertien stappen die elk zeggen wat je ziet, wat je ermee
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

        $this->assertCount(14, $stappen);
        $this->assertSame('welkom', $stappen[0]['key']);
        $this->assertSame('afsluiting', $stappen[13]['key']);

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
                ->count('onboarding.tourSteps', 14)
                ->has('onboarding.tourSteps.0.tip')
            );
    }
}
