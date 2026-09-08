<?php

namespace Tests\Feature\Schools;

use App\Enums\BillingType;
use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Location;
use App\Models\Product;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locaties als echt ding in plaats van een tekstveld.
 *
 * De kern: je kiest een locatie, en wat er is afgesproken houdt de naam vast
 * zoals die op dat moment was. Een locatie hernoemen mag de agenda van vorig
 * seizoen niet herschrijven.
 */
class LocationTest extends TestCase
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

    public function test_de_eigenaar_beheert_locaties(): void
    {
        $this->actingAs($this->eigenaar)->post('/locaties', [
            'name' => 'Sportpark De Vliert',
            'address' => 'Vlierweg 1',
            'note' => 'Kunstgras',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', [
            'school_id' => $this->school->id,
            'name' => 'Sportpark De Vliert',
            'address' => 'Vlierweg 1',
        ]);

        // Twee keer dezelfde naam maakt elk overzicht onbruikbaar.
        $this->actingAs($this->eigenaar)
            ->post('/locaties', ['name' => 'Sportpark De Vliert', 'is_active' => true])
            ->assertSessionHasErrors('name');
    }

    public function test_een_training_krijgt_de_naam_van_de_gekozen_locatie(): void
    {
        $locatie = Location::create(['name' => 'Sportpark De Vliert', 'is_active' => true]);
        $groep = Group::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)->post('/trainings', [
            'group_id' => $groep->id,
            'date' => now()->addWeek()->toDateString(),
            'starts_at' => '18:00',
            'ends_at' => '19:30',
            'location_id' => $locatie->id,
        ])->assertRedirect();

        $training = Training::firstOrFail();

        $this->assertSame($locatie->id, $training->location_id);
        $this->assertSame('Sportpark De Vliert', $training->location);

        // Hernoemen raakt wat er al staat niet: de agenda van vorig seizoen
        // hoort te blijven kloppen.
        $locatie->update(['name' => 'Sportpark Noord']);

        $this->assertSame('Sportpark De Vliert', $training->fresh()->location);
        $this->assertSame('Sportpark Noord', $training->fresh()->venue->name);
    }

    public function test_een_aanbod_geeft_zijn_locatie_door_aan_de_trainingen(): void
    {
        $locatie = Location::create(['name' => 'Sporthal Zuid', 'is_active' => true]);

        $this->actingAs($this->eigenaar)->post('/aanbod', [
            'name' => 'Keepersblok',
            'type' => ProductType::Blok->value,
            'billing_type' => BillingType::Eenmalig->value,
            'amount' => '120,00',
            'vat_rate' => 21,
            'starts_on' => now()->addWeek()->next('monday')->toDateString(),
            'ends_on' => now()->addWeeks(3)->next('monday')->toDateString(),
            'location_id' => $locatie->id,
            'status' => 'open',
            'stops_at_end' => true,
            'is_active' => true,
            'weekdays' => [1],
            'starts_at' => '18:00',
            'ends_at' => '19:30',
        ])->assertSessionHasNoErrors();

        $blok = Product::firstWhere('name', 'Keepersblok');

        $this->assertSame($locatie->id, $blok->location_id);
        $this->assertSame('Sporthal Zuid', $blok->location);
        $this->assertSame('Sporthal Zuid', Training::first()->location);
    }

    public function test_een_locatie_van_een_andere_school_wordt_geweigerd(): void
    {
        $andere = School::factory()->create();

        app(Tenancy::class)->set($andere);
        $vreemd = Location::create(['name' => 'Vreemd veld', 'is_active' => true]);
        app(Tenancy::class)->set($this->school);

        $groep = Group::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)->post('/trainings', [
            'group_id' => $groep->id,
            'date' => now()->addWeek()->toDateString(),
            'starts_at' => '18:00',
            'ends_at' => '19:30',
            'location_id' => $vreemd->id,
        ])->assertSessionHasErrors('location_id');
    }

    public function test_het_overzicht_telt_waar_een_locatie_gebruikt_wordt(): void
    {
        $locatie = Location::create(['name' => 'Sportpark De Vliert', 'is_active' => true]);
        $groep = Group::factory()->for($this->school)->create();

        Training::factory()->count(2)->for($this->school)->for($groep)->create(['location_id' => $locatie->id]);

        $this->actingAs($this->eigenaar)
            ->get('/locaties')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('schools/Locations')
                ->count('locations', 1)
                ->where('locations.0.trainings_count', 2)
                ->where('locations.0.name', 'Sportpark De Vliert')
            );
    }

    public function test_een_trainer_kiest_een_locatie_in_het_formulier_maar_beheert_ze_niet(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        // Kiezen doet hij in het inplanformulier; het beheerscherm hoort bij
        // Mijn bedrijf en dat is van de eigenaar.
        $this->actingAs($trainer)->get('/locaties')->assertForbidden();
        $this->actingAs($trainer)->get('/trainings/create')->assertOk();

        $this->actingAs($trainer)
            ->post('/locaties', ['name' => 'Eigen veldje', 'is_active' => true])
            ->assertForbidden();
    }
}
