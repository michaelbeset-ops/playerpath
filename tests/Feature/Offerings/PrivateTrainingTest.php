<?php

namespace Tests\Feature\Offerings;

use App\Enums\BillingType;
use App\Enums\ProductType;
use App\Enums\Role;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\Slot;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Privétraining: de school zet momenten neer, een ouder boekt er een.
 *
 * De kern is dat een boeking een echte training oplevert. Daardoor staat dat
 * uur in de agenda van de trainer en kan er gewoon aanwezigheid en een rapport
 * bij — zonder dat er een tweede soort training bestaat die overal apart
 * behandeld moet worden.
 */
class PrivateTrainingTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $trainer;

    protected User $ouder;

    protected Player $kind;

    protected Product $prive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->trainer = User::factory()->for($this->school)->create(['name' => 'Piet Trainer']);
        $this->trainer->assignRole(Role::Trainer->value);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        app(Tenancy::class)->set($this->school);

        $this->kind = Player::factory()->for($this->school)->create(['first_name' => 'Sem']);
        $this->ouder->children()->attach($this->kind->id);

        $this->prive = Product::factory()->for($this->school)->create([
            'name' => 'Privétraining 30 minuten',
            'type' => ProductType::Privetraining,
            'billing_type' => BillingType::Eenmalig,
            'amount_cents' => 3500,
            'interval' => null,
        ]);
    }

    protected function moment(array $overschrijf = []): Slot
    {
        return $this->prive->slots()->create(array_merge([
            'user_id' => $this->trainer->id,
            'starts_at' => CarbonImmutable::now()->addWeek()->setTime(16, 0),
            'ends_at' => CarbonImmutable::now()->addWeek()->setTime(16, 30),
            'location' => 'Sportpark De Vliert',
        ], $overschrijf));
    }

    public function test_de_school_zet_een_reeks_momenten_neer(): void
    {
        $this->actingAs($this->eigenaar)->post('/aanbod/'.$this->prive->id.'/momenten', [
            'date' => CarbonImmutable::now()->addWeek()->toDateString(),
            'starts_at' => '16:00',
            'ends_at' => '16:30',
            'user_id' => $this->trainer->id,
            'repeat_weeks' => 4,
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, $this->prive->slots()->count());

        // Twee keer hetzelfde uur neerzetten levert een ouder een lijst met
        // dubbele momenten op.
        $this->actingAs($this->eigenaar)->post('/aanbod/'.$this->prive->id.'/momenten', [
            'date' => CarbonImmutable::now()->addWeek()->toDateString(),
            'starts_at' => '16:00',
            'ends_at' => '16:30',
            'user_id' => $this->trainer->id,
        ]);

        $this->assertSame(4, $this->prive->slots()->count());
    }

    public function test_de_shop_toont_alleen_vrije_momenten(): void
    {
        $vrij = $this->moment();
        $this->moment(['starts_at' => CarbonImmutable::now()->subDay(), 'ends_at' => CarbonImmutable::now()->subDay()->addMinutes(30)]);
        $this->moment(['starts_at' => CarbonImmutable::now()->addWeeks(2), 'ends_at' => CarbonImmutable::now()->addWeeks(2)->addMinutes(30)])
            ->update(['player_id' => $this->kind->id, 'booked_at' => now()]);

        $this->actingAs($this->ouder)
            ->get('/shop')
            ->assertInertia(fn ($page) => $page
                ->where('products.0.name', 'Privétraining 30 minuten')
                ->count('products.0.slots', 1)
                ->where('products.0.slots.0.id', $vrij->id)
                ->where('products.0.slots.0.trainer', 'Piet Trainer')
            );
    }

    public function test_boeken_maakt_een_training_met_de_trainer_erbij(): void
    {
        $moment = $this->moment();

        $this->actingAs($this->ouder)
            ->post('/shop/'.$this->prive->id, ['player_id' => $this->kind->id, 'slot_id' => $moment->id])
            ->assertRedirect('/billing');

        $moment->refresh();

        $this->assertSame($this->kind->id, $moment->player_id);
        $this->assertNotNull($moment->booked_at);

        // De training staat in de agenda, met de trainer van dat moment.
        $training = Training::where('slot_id', $moment->id)->firstOrFail();

        $this->assertNull($training->group_id);
        $this->assertTrue($training->trainers->contains('id', $this->trainer->id));
        $this->assertSame('Sportpark De Vliert', $training->location);

        // En er wordt maar één kind verwacht: het kind dat geboekt heeft.
        $this->assertSame([$this->kind->id], $training->expectedPlayers()->pluck('id')->all());

        // De rekening loopt via dezelfde weg als de rest.
        $this->assertDatabaseHas('purchases', ['player_id' => $this->kind->id, 'amount_cents' => 3500]);
        $this->assertDatabaseHas('payments', ['player_id' => $this->kind->id, 'amount_cents' => 3500, 'status' => 'open']);
    }

    public function test_een_moment_kan_maar_een_keer_geboekt_worden(): void
    {
        $moment = $this->moment();

        $ander = Player::factory()->for($this->school)->create();
        $this->ouder->children()->attach($ander->id);

        $this->actingAs($this->ouder)->post('/shop/'.$this->prive->id, [
            'player_id' => $this->kind->id,
            'slot_id' => $moment->id,
        ]);

        // Twee kinderen op hetzelfde uur bij dezelfde trainer is geen
        // privétraining meer.
        $this->actingAs($this->ouder)
            ->post('/shop/'.$this->prive->id, ['player_id' => $ander->id, 'slot_id' => $moment->id])
            ->assertSessionHasErrors('slot_id');

        $this->assertSame($this->kind->id, $moment->refresh()->player_id);
        $this->assertSame(1, Training::where('slot_id', $moment->id)->count());
    }

    public function test_zonder_moment_boek_je_geen_privetraining(): void
    {
        $this->moment();

        $this->actingAs($this->ouder)
            ->post('/shop/'.$this->prive->id, ['player_id' => $this->kind->id])
            ->assertSessionHasErrors('slot_id');

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_de_ouder_ziet_zijn_eigen_privetraining_in_de_agenda(): void
    {
        $moment = $this->moment();

        $this->actingAs($this->ouder)->post('/shop/'.$this->prive->id, [
            'player_id' => $this->kind->id,
            'slot_id' => $moment->id,
        ]);

        // Een privétraining heeft geen groep; zonder de tweede tak in
        // VisibleTrainings zou een ouder zijn eigen afspraak niet zien.
        $this->actingAs($this->ouder)
            ->get('/trainings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('upcoming', 1)
                ->where('upcoming.0.group', 'Privétraining 30 minuten')
            );
    }

    public function test_de_boeking_terugdraaien_maakt_het_moment_weer_vrij(): void
    {
        $moment = $this->moment();

        $this->actingAs($this->ouder)->post('/shop/'.$this->prive->id, [
            'player_id' => $this->kind->id,
            'slot_id' => $moment->id,
        ]);

        $this->actingAs($this->eigenaar)
            ->delete('/aanbod/'.$this->prive->id.'/momenten/'.$moment->id)
            ->assertRedirect();

        $moment->refresh();

        $this->assertNull($moment->player_id);
        $this->assertSame(0, Training::where('slot_id', $moment->id)->count());

        // De rekening blijft staan: wat er is afgesproken hoort in de historie.
        $this->assertDatabaseCount('purchases', 1);
    }

    public function test_een_ouder_beheert_geen_momenten(): void
    {
        $moment = $this->moment();

        $this->actingAs($this->ouder)->get('/aanbod/'.$this->prive->id.'/momenten')->assertForbidden();
        $this->actingAs($this->ouder)->delete('/aanbod/'.$this->prive->id.'/momenten/'.$moment->id)->assertForbidden();
    }
}
