<?php

namespace Tests\Feature\Trainings;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Notifications\AfmeldingOntvangen;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Afmelden voor een training vanaf het ouder-dashboard: met reden, de trainer
 * hoort het, en de ouder ziet het terug. Plus: geen meldingsblokken meer op
 * het dashboard; een openstaande rekening is een bolletje in het menu.
 */
class DeclineTrainingTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $ouder;

    protected User $trainer;

    protected Player $sem;

    protected Training $training;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        app(Tenancy::class)->set($this->school);

        $this->sem = Player::factory()->for($this->school)->create(['first_name' => 'Sem']);
        $this->ouder->children()->attach($this->sem->id);

        $groep = Group::factory()->for($this->school)->create(['name' => 'Keepers']);
        $this->sem->groups()->attach($groep->id);

        $this->training = Training::factory()->for($this->school)->for($groep)->create([
            'starts_at' => now()->addDays(2)->setTime(18, 0),
            'ends_at' => now()->addDays(2)->setTime(19, 0),
        ]);
        $this->training->trainers()->attach($this->trainer->id);
    }

    public function test_afmelden_met_reden_bereikt_de_trainer_en_staat_op_het_dashboard(): void
    {
        Notification::fake();

        $this->actingAs($this->ouder)
            ->post("/trainings/{$this->training->id}/registration/{$this->sem->id}", ['registration' => 'declined', 'reason' => 'Ziek'])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', [
            'training_id' => $this->training->id,
            'player_id' => $this->sem->id,
            'registration' => 'declined',
            'registration_note' => 'Ziek',
        ]);

        Notification::assertSentTo($this->trainer, AfmeldingOntvangen::class, fn (AfmeldingOntvangen $m) => $m->reason === 'Ziek');

        // Het dashboard zegt het, per kind, met de knop om weer aan te melden.
        $this->actingAs($this->ouder)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('upcoming.0.children.0.first_name', 'Sem')
                ->where('upcoming.0.children.0.registration', 'declined')
                ->missing('todo')
            );

        // En de trainer ziet de reden in zijn aanwezigheidslijst.
        $this->actingAs($this->trainer)
            ->get('/trainings/'.$this->training->id)
            ->assertInertia(fn ($page) => $page
                ->where('players.0.registration', 'declined')
                ->where('players.0.registration_note', 'Ziek')
            );

        // Weer aanmelden wist de reden en stuurt geen afmeldbericht.
        $this->actingAs($this->ouder)
            ->post("/trainings/{$this->training->id}/registration/{$this->sem->id}", ['registration' => 'attending']);

        $this->assertDatabaseHas('attendances', ['player_id' => $this->sem->id, 'registration' => 'attending', 'registration_note' => null]);
        Notification::assertSentToTimes($this->trainer, AfmeldingOntvangen::class, 1);
    }

    public function test_zonder_gekoppelde_trainer_hoort_de_hele_staf_het(): void
    {
        Notification::fake();
        $this->training->trainers()->detach();

        $eigenaar = User::factory()->for($this->school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($this->ouder)
            ->post("/trainings/{$this->training->id}/registration/{$this->sem->id}", ['registration' => 'declined']);

        Notification::assertSentTo($this->trainer, AfmeldingOntvangen::class);
        Notification::assertSentTo($eigenaar, AfmeldingOntvangen::class);
    }

    public function test_een_openstaande_rekening_is_een_bolletje_in_het_menu(): void
    {
        Payment::factory()->for($this->school)->create([
            'player_id' => $this->sem->id,
            'status' => PaymentStatus::Open,
            'amount_cents' => 2750,
            'due_on' => now()->addWeek(),
        ]);

        $this->actingAs($this->ouder)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('nav', function ($nav) {
                foreach ($nav as $groep) {
                    foreach ($groep['items'] ?? [] as $item) {
                        if ($item['href'] === '/billing') {
                            return ($item['badge'] ?? 0) === 1;
                        }
                    }
                }

                return false;
            }));
    }
}
