<?php

namespace Tests\Feature\Staff;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Notifications\Uitnodiging;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Een trainer uit een import heeft een account zonder adres. De eigenaar
 * geeft hem een inlog, en bij activatie wordt het dát account - geen tweede.
 */
class TrainerLoginTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $trainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->trainer = User::create([
            'school_id' => $this->school->id,
            'name' => 'Aad Kattevilder',
            'email' => null,
            'password' => Str::password(40),
        ]);
        $this->trainer->assignRole(Role::Trainer->value);
    }

    public function test_de_eigenaar_geeft_een_trainer_zonder_adres_een_inlog(): void
    {
        Notification::fake();

        $training = Training::factory()->for($this->school)->create();
        $training->trainers()->attach($this->trainer->id);

        $this->actingAs($this->eigenaar)
            ->post("/staff/trainers/{$this->trainer->id}/inlog", ['email' => 'Aad@Voorbeeld.nl'])
            ->assertSessionHas('status');

        $uitnodiging = Invitation::firstOrFail();
        $this->assertSame('aad@voorbeeld.nl', $uitnodiging->email);
        $this->assertSame($this->trainer->id, $uitnodiging->user_id);
        Notification::assertSentOnDemand(Uitnodiging::class);

        // Personeel zegt waar de uitnodiging heen ging.
        $this->actingAs($this->eigenaar)->get('/staff')->assertInertia(fn ($page) => $page
            ->where('trainers', fn ($rijen) => collect($rijen)->firstWhere('id', $this->trainer->id)['invited_email'] === 'aad@voorbeeld.nl'));

        auth()->logout();

        $this->get('/uitnodiging/'.$uitnodiging->token)->assertOk()
            ->assertInertia(fn ($page) => $page->where('invitation.email', 'aad@voorbeeld.nl'));

        $this->post('/uitnodiging/'.$uitnodiging->token, [
            'password' => 'Keeper-2026-lang',
            'password_confirmation' => 'Keeper-2026-lang',
        ])->assertRedirect();

        $this->trainer->refresh();
        $this->assertSame('aad@voorbeeld.nl', $this->trainer->email);
        $this->assertNotNull($this->trainer->email_verified_at);
        $this->assertTrue($this->trainer->isTrainer());
        $this->assertAuthenticatedAs($this->trainer);
        $this->assertSame(2, User::count(), 'Er mag geen tweede account ontstaan.');
        $this->assertTrue($training->trainers()->whereKey($this->trainer->id)->exists());
    }

    public function test_een_trainer_met_inlog_krijgt_geen_tweede(): void
    {
        $collega = User::factory()->for($this->school)->create();
        $collega->assignRole(Role::Trainer->value);

        $this->actingAs($this->eigenaar)
            ->post("/staff/trainers/{$collega->id}/inlog", ['email' => 'nieuw@voorbeeld.nl'])
            ->assertSessionHas('error');

        $this->assertSame(0, Invitation::count());
    }

    public function test_een_adres_dat_al_bestaat_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->post("/staff/trainers/{$this->trainer->id}/inlog", ['email' => $this->eigenaar->email])
            ->assertSessionHasErrors('email');
    }

    public function test_een_trainer_mag_geen_inlog_geven(): void
    {
        $collega = User::factory()->for($this->school)->create();
        $collega->assignRole(Role::Trainer->value);

        $this->actingAs($collega)
            ->post("/staff/trainers/{$this->trainer->id}/inlog", ['email' => 'aad@voorbeeld.nl'])
            ->assertForbidden();
    }

    public function test_een_trainer_van_een_andere_school_is_niet_te_bereiken(): void
    {
        $andere = School::factory()->create();
        $vreemd = User::factory()->for($andere)->create(['email' => null]);

        $this->actingAs($this->eigenaar)
            ->post("/staff/trainers/{$vreemd->id}/inlog", ['email' => 'x@voorbeeld.nl'])
            ->assertForbidden();
    }
}
