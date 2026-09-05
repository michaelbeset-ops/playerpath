<?php

namespace Tests\Feature\Billing;

use App\Enums\Role;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Notifications\BetalingHerinnering;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentReminderTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $ouder;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        $this->speler = Player::factory()->for($this->school)->create();
        $this->speler->guardians()->attach($this->ouder->id);
    }

    protected function betaling(int $dagenGeleden): Payment
    {
        return Payment::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'due_on' => now()->subDays($dagenGeleden)->toDateString(),
        ]);
    }

    public function test_een_te_late_betaling_levert_een_herinnering_op(): void
    {
        Notification::fake();

        $betaling = $this->betaling(10);

        $this->artisan('payments:remind')->assertSuccessful();

        Notification::assertSentTo($this->ouder, BetalingHerinnering::class);
        $this->assertNotNull($betaling->refresh()->reminded_at);
    }

    public function test_binnen_de_respijtperiode_gebeurt_er_niets(): void
    {
        Notification::fake();

        $this->betaling(1);

        $this->artisan('payments:remind')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_een_betaalde_rekening_krijgt_geen_herinnering(): void
    {
        Notification::fake();

        Payment::factory()->for($this->school)->paid()->create([
            'player_id' => $this->speler->id,
            'due_on' => now()->subDays(30)->toDateString(),
        ]);

        $this->artisan('payments:remind')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_er_gaat_niet_elke_dag_opnieuw_een_mail_uit(): void
    {
        Notification::fake();

        $this->betaling(10);

        $this->artisan('payments:remind');
        $this->artisan('payments:remind');

        Notification::assertSentToTimes($this->ouder, BetalingHerinnering::class, 1);
    }

    public function test_na_twee_weken_mag_er_weer_een_herinnering(): void
    {
        Notification::fake();

        $betaling = $this->betaling(10);
        $betaling->forceFill(['reminded_at' => now()->subWeeks(3)])->save();

        $this->artisan('payments:remind');

        Notification::assertSentTo($this->ouder, BetalingHerinnering::class);
    }

    public function test_een_proefdraai_verstuurt_niets(): void
    {
        Notification::fake();

        $betaling = $this->betaling(10);

        $this->artisan('payments:remind', ['--dry-run' => true])->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($betaling->refresh()->reminded_at);
    }

    public function test_een_speler_zonder_ouders_levert_geen_fout_op(): void
    {
        Notification::fake();

        $wees = Player::factory()->for($this->school)->create();
        Payment::factory()->for($this->school)->create([
            'player_id' => $wees->id,
            'due_on' => now()->subDays(20)->toDateString(),
        ]);

        $this->artisan('payments:remind')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_scholen_blijven_gescheiden(): void
    {
        Notification::fake();

        $andere = School::factory()->create();
        $andereOuder = User::factory()->for($andere)->create();
        $andereOuder->assignRole(Role::Ouder->value);

        app(Tenancy::class)->forSchool($andere, function () use ($andere, $andereOuder) {
            $speler = Player::factory()->for($andere)->create();
            $speler->guardians()->attach($andereOuder->id);

            Payment::factory()->for($andere)->create([
                'player_id' => $speler->id,
                'due_on' => now()->subDays(20)->toDateString(),
            ]);
        });

        $this->betaling(20);

        $this->artisan('payments:remind');

        // Elke ouder precies één keer, en nooit die van de andere school erbij.
        Notification::assertSentToTimes($this->ouder, BetalingHerinnering::class, 1);
        Notification::assertSentToTimes($andereOuder, BetalingHerinnering::class, 1);
    }
}
