<?php

namespace Tests\Feature\Trainings;

use App\Enums\Daypart;
use App\Enums\Role;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\Group;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Availability\TrainerAvailability;
use App\Support\Dashboard\AttentionItems;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Beschikbaarheid van trainers, en wat de planning ermee doet.
 *
 * Het gevoeligste punt zit in het verschil tussen "onbekend" en "kan niet". Een
 * trainer die niets heeft ingevuld hoort geen waarschuwing op te leveren; zou
 * dat wel zo zijn, dan kleurt bij elke school die dit nog niet gebruikt de hele
 * planning rood en kijkt niemand er meer naar.
 */
class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $trainer;

    protected Group $groep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->trainer = User::factory()->for($this->school)->create(['name' => 'Sanne Bakker']);
        $this->trainer->assignRole(Role::Trainer->value);

        $this->groep = Group::factory()->for($this->school)->create();

        // Zaterdag 12 september 2026.
        $this->travelTo('2026-09-12 09:00:00');
    }

    protected function moment(string $tijd): CarbonImmutable
    {
        return CarbonImmutable::parse($tijd);
    }

    public function test_niets_ingevuld_is_onbekend_en_geen_nee(): void
    {
        $this->assertNull(app(TrainerAvailability::class)->isAvailableAt($this->trainer, $this->moment('2026-09-15 19:00:00')));
    }

    public function test_het_ritme_bepaalt_of_iemand_kan(): void
    {
        // Dinsdagavond wel, dinsdagochtend niet.
        AvailabilityRule::create(['user_id' => $this->trainer->id, 'weekday' => 2, 'daypart' => Daypart::Avond->value]);

        $beschikbaarheid = app(TrainerAvailability::class);

        $this->assertTrue($beschikbaarheid->isAvailableAt($this->trainer, $this->moment('2026-09-15 19:00:00')));
        $this->assertFalse($beschikbaarheid->isAvailableAt($this->trainer, $this->moment('2026-09-15 10:00:00')));
        // Woensdagavond staat er niet, dus dat is nee.
        $this->assertFalse($beschikbaarheid->isAvailableAt($this->trainer, $this->moment('2026-09-16 19:00:00')));
    }

    public function test_een_uitzondering_wint_van_het_ritme(): void
    {
        AvailabilityRule::create(['user_id' => $this->trainer->id, 'weekday' => 2, 'daypart' => Daypart::Avond->value]);

        AvailabilityException::create([
            'user_id' => $this->trainer->id,
            'starts_on' => '2026-10-12',
            'ends_on' => '2026-10-19',
            'available' => false,
            'note' => 'Vakantie',
        ]);

        $beschikbaarheid = app(TrainerAvailability::class);

        // Buiten de vakantie geldt het ritme, erbinnen de uitzondering.
        $this->assertTrue($beschikbaarheid->isAvailableAt($this->trainer, $this->moment('2026-10-06 19:00:00')));
        $this->assertFalse($beschikbaarheid->isAvailableAt($this->trainer, $this->moment('2026-10-13 19:00:00')));
    }

    public function test_de_fijnste_uitzondering_wint(): void
    {
        AvailabilityException::create([
            'user_id' => $this->trainer->id,
            'starts_on' => '2026-10-12',
            'ends_on' => '2026-10-19',
            'available' => true,
        ]);

        // Die ene zaterdagochtend toch niet.
        AvailabilityException::create([
            'user_id' => $this->trainer->id,
            'starts_on' => '2026-10-17',
            'ends_on' => '2026-10-17',
            'daypart' => Daypart::Ochtend->value,
            'available' => false,
        ]);

        $beschikbaarheid = app(TrainerAvailability::class);

        $this->assertFalse($beschikbaarheid->isAvailableAt($this->trainer, $this->moment('2026-10-17 10:00:00')));
        // 's Middags geldt de ruimere uitzondering weer.
        $this->assertTrue($beschikbaarheid->isAvailableAt($this->trainer, $this->moment('2026-10-17 14:00:00')));
    }

    public function test_de_trainer_slaat_zijn_raster_in_een_keer_op(): void
    {
        $this->actingAs($this->trainer)
            ->put('/beschikbaarheid', ['slots' => [
                ['weekday' => 2, 'daypart' => 'avond'],
                ['weekday' => 4, 'daypart' => 'avond'],
            ]])
            ->assertRedirect();

        $this->assertSame(2, AvailabilityRule::where('user_id', $this->trainer->id)->count());

        // Opnieuw opslaan met minder: wat er niet meer in staat is uitgevinkt.
        $this->actingAs($this->trainer)
            ->put('/beschikbaarheid', ['slots' => [['weekday' => 2, 'daypart' => 'avond']]]);

        $this->assertSame(1, AvailabilityRule::where('user_id', $this->trainer->id)->count());
    }

    public function test_een_uitzondering_die_eindigt_voor_hij_begint_bestaat_niet(): void
    {
        $this->actingAs($this->trainer)
            ->post('/beschikbaarheid/uitzonderingen', [
                'starts_on' => '2026-10-19',
                'ends_on' => '2026-10-12',
            ])
            ->assertSessionHasErrors('ends_on');
    }

    public function test_niemand_vult_de_beschikbaarheid_van_een_ander_in(): void
    {
        $vanEenAnder = AvailabilityException::create([
            'user_id' => $this->trainer->id,
            'starts_on' => '2026-10-12',
            'ends_on' => '2026-10-19',
            'available' => false,
        ]);

        // Ook de eigenaar niet: hij mag kijken, niet invullen.
        $this->actingAs($this->eigenaar)
            ->delete('/beschikbaarheid/uitzonderingen/'.$vanEenAnder->id)
            ->assertForbidden();
    }

    public function test_het_teamoverzicht_is_van_de_eigenaar(): void
    {
        $this->actingAs($this->trainer)->get('/personeel/beschikbaarheid')->assertForbidden();

        $this->actingAs($this->eigenaar)
            ->get('/personeel/beschikbaarheid')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('staff/AvailabilityOverview')->count('trainers', 2));
    }

    protected function training(string $begint): Training
    {
        return Training::factory()->for($this->school)->for($this->groep)->create([
            'starts_at' => CarbonImmutable::parse($begint),
            'ends_at' => CarbonImmutable::parse($begint)->addHour(),
        ]);
    }

    public function test_de_eigenaar_wordt_gewaarschuwd_als_de_trainer_niet_kan(): void
    {
        AvailabilityRule::create(['user_id' => $this->trainer->id, 'weekday' => 2, 'daypart' => Daypart::Avond->value]);

        // Woensdagavond: staat niet in zijn ritme.
        $this->training('2026-09-16 19:00:00')->trainers()->attach($this->trainer->id);

        $sleutels = collect(app(AttentionItems::class)->for($this->eigenaar))->pluck('key');

        $this->assertContains('unavailable_trainers', $sleutels);
    }

    public function test_een_training_zonder_trainer_komt_in_het_aandacht_blok(): void
    {
        $this->training('2026-09-16 19:00:00');

        $sleutels = collect(app(AttentionItems::class)->for($this->eigenaar))->pluck('key');

        $this->assertContains('unstaffed_trainings', $sleutels);
    }

    public function test_zonder_ingevulde_beschikbaarheid_geen_valse_waarschuwing(): void
    {
        $this->training('2026-09-16 19:00:00')->trainers()->attach($this->trainer->id);

        $sleutels = collect(app(AttentionItems::class)->for($this->eigenaar))->pluck('key');

        $this->assertNotContains('unavailable_trainers', $sleutels);
        $this->assertNotContains('unstaffed_trainings', $sleutels);
    }

    public function test_een_trainer_krijgt_geen_planningssignalen(): void
    {
        $this->training('2026-09-16 19:00:00');

        $sleutels = collect(app(AttentionItems::class)->for($this->trainer))->pluck('key');

        // Dat er nergens een trainer bij staat is werk van de eigenaar.
        $this->assertNotContains('unstaffed_trainings', $sleutels);
    }
}
