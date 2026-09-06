<?php

namespace Tests\Feature\Clients;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\Report;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        $this->eigenaar = User::factory()->for($this->school)->create(['name' => 'Rob de Baas']);
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);
    }

    protected function trainer(string $naam = 'Piet Trainer'): User
    {
        $trainer = User::factory()->for($this->school)->create(['name' => $naam]);
        $trainer->assignRole(Role::Trainer->value);

        return $trainer;
    }

    // --- Het overzicht ---

    public function test_het_klantenoverzicht_toont_spelers_en_ouders(): void
    {
        Player::factory()->count(2)->for($this->school)->create();
        $this->trainer();

        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $this->actingAs($this->eigenaar)
            ->get('/clients')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('clients/Players')
                ->where('counts.players', 2)
                ->where('counts.guardians', 1)
            );
    }

    public function test_een_trainer_staat_niet_bij_de_klanten(): void
    {
        $this->trainer('Piet Trainer');

        // Personeel hoort bij het bedrijf, niet bij de klanten.
        $this->actingAs($this->eigenaar)->get('/clients')->assertDontSee('Piet Trainer');
        $this->actingAs($this->eigenaar)->get('/clients/guardians')->assertDontSee('Piet Trainer');
        $this->actingAs($this->eigenaar)->get('/staff')->assertSee('Piet Trainer');
    }

    public function test_de_oude_adressen_wijzen_naar_klanten(): void
    {
        $this->actingAs($this->eigenaar)->get('/players')->assertRedirect('/clients');
        $this->actingAs($this->eigenaar)->get('/users')->assertRedirect('/clients');
    }

    public function test_de_spelerslijst_laat_zien_wie_een_eigen_inlog_heeft(): void
    {
        $metLogin = User::factory()->for($this->school)->create(['email' => 'daan@voorbeeld.nl']);
        $metLogin->assignRole(Role::Speler->value);

        Player::factory()->for($this->school)->create(['first_name' => 'Daan', 'last_name' => 'Visser', 'user_id' => $metLogin->id]);
        Player::factory()->for($this->school)->create(['first_name' => 'Sem', 'last_name' => 'de Vries']);

        $this->actingAs($this->eigenaar)
            ->get('/clients')
            ->assertInertia(function ($page) {
                $spelers = collect($page->toArray()['props']['players'])->keyBy('name');

                $page->count('players', 2);

                $this->assertFalse($spelers['Sem de Vries']['has_login']);
                $this->assertTrue($spelers['Daan Visser']['has_login']);
                $this->assertSame('daan@voorbeeld.nl', $spelers['Daan Visser']['email']);
            });
    }

    public function test_het_personeelsoverzicht_toont_de_eigenaar_erbij(): void
    {
        $this->trainer();

        $this->actingAs($this->eigenaar)
            ->get('/staff')
            ->assertInertia(fn ($page) => $page
                ->component('staff/Index')
                // De eigenaar geeft bij kleine scholen zelf ook training.
                ->count('trainers', 2)
            );
    }

    public function test_het_ouderoverzicht_toont_de_gekoppelde_kinderen(): void
    {
        $ouder = User::factory()->for($this->school)->create(['name' => 'Marieke']);
        $ouder->assignRole(Role::Ouder->value);

        $kind = Player::factory()->for($this->school)->create(['first_name' => 'Sem', 'last_name' => 'de Vries']);
        $ouder->children()->attach($kind->id, ['relationship' => 'moeder']);

        $this->actingAs($this->eigenaar)
            ->get('/clients/guardians')
            ->assertInertia(fn ($page) => $page
                ->count('guardians', 1)
                ->where('guardians.0.children.0.name', 'Sem de Vries')
                ->where('guardians.0.children.0.relationship', 'moeder')
            );
    }

    public function test_gebruikers_van_een_andere_school_staan_er_niet_bij(): void
    {
        $andereSchool = School::factory()->create();
        Player::factory()->count(3)->for($andereSchool)->create();

        $vreemdeTrainer = User::factory()->for($andereSchool)->create();
        $vreemdeTrainer->assignRole(Role::Trainer->value);

        Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->get('/clients')
            ->assertInertia(fn ($page) => $page
                ->where('counts.players', 1)
                ->count('players', 1)
            );

        $this->actingAs($this->eigenaar)
            ->get('/staff')
            ->assertInertia(fn ($page) => $page->count('trainers', 1));
    }

    public function test_een_ouder_komt_niet_bij_het_klantenoverzicht(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $this->actingAs($ouder)->get('/clients')->assertForbidden();
        $this->actingAs($ouder)->get('/staff')->assertForbidden();
    }

    // --- Trainers uitnodigen ---

    public function test_een_eigenaar_kan_een_trainer_uitnodigen(): void
    {
        Notification::fake();

        $this->actingAs($this->eigenaar)
            ->post('/staff/trainers', ['name' => 'Nieuwe Trainer', 'email' => 'trainer@voorbeeld.nl'])
            ->assertRedirect();

        $trainer = User::where('email', 'trainer@voorbeeld.nl')->firstOrFail();

        $this->assertSame($this->school->id, $trainer->school_id);
        $this->assertTrue($trainer->isTrainer());

        // Geen wachtwoord dat iemand anders kent: hij kiest er zelf een.
        Notification::assertSentTo($trainer, ResetPassword::class);
    }

    public function test_een_trainer_mag_zelf_geen_trainers_uitnodigen(): void
    {
        $this->actingAs($this->trainer())
            ->post('/staff/trainers', ['name' => 'X', 'email' => 'x@voorbeeld.nl'])
            ->assertForbidden();
    }

    public function test_een_bestaand_e_mailadres_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/staff/trainers', ['name' => 'Dubbel', 'email' => $this->eigenaar->email])
            ->assertSessionHasErrors('email');
    }

    // --- Een trainer verwijderen ---

    public function test_een_trainer_verwijderen_laat_zijn_rapporten_bestaan(): void
    {
        $trainer = $this->trainer();
        $speler = Player::factory()->for($this->school)->keeper()->create();

        $rapport = Report::factory()->for($this->school)->create([
            'player_id' => $speler->id,
            'trainer_id' => $trainer->id,
        ]);

        $this->actingAs($this->eigenaar)->delete('/staff/trainers/'.$trainer->id)->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $trainer->id]);

        // De historie hoort bij de speler, niet bij de trainer.
        $this->assertDatabaseHas('reports', ['id' => $rapport->id]);
        $this->assertNull($rapport->refresh()->trainer_id);
    }

    public function test_de_eigenaar_kan_zichzelf_niet_verwijderen(): void
    {
        $this->actingAs($this->eigenaar)
            ->delete('/staff/trainers/'.$this->eigenaar->id)
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $this->eigenaar->id]);
    }

    // --- Trainers aan trainingen ---

    public function test_een_training_kan_meerdere_trainers_hebben(): void
    {
        $groep = Group::factory()->for($this->school)->create();
        $een = $this->trainer('Trainer Een');
        $twee = $this->trainer('Trainer Twee');

        $this->actingAs($this->eigenaar)->post('/trainings', [
            'group_id' => $groep->id,
            'date' => now()->addWeek()->toDateString(),
            'starts_at' => '18:00',
            'ends_at' => '19:30',
            'trainers' => [$een->id, $twee->id],
        ])->assertRedirect();

        $training = Training::firstOrFail();

        $this->assertCount(2, $training->trainers);
        $this->assertDatabaseHas('training_user', [
            'school_id' => $this->school->id,
            'training_id' => $training->id,
            'user_id' => $een->id,
        ]);
    }

    public function test_een_trainer_van_een_andere_school_wordt_geweigerd(): void
    {
        $groep = Group::factory()->for($this->school)->create();

        $andereSchool = School::factory()->create();
        $vreemdeTrainer = User::factory()->for($andereSchool)->create();

        $this->actingAs($this->eigenaar)
            ->post('/trainings', [
                'group_id' => $groep->id,
                'date' => now()->addWeek()->toDateString(),
                'starts_at' => '18:00',
                'ends_at' => '19:30',
                'trainers' => [$vreemdeTrainer->id],
            ])
            ->assertSessionHasErrors('trainers.0');

        $this->assertDatabaseCount('trainings', 0);
    }

    public function test_de_trainers_staan_op_de_training_en_in_het_overzicht(): void
    {
        $groep = Group::factory()->for($this->school)->create();
        $trainer = $this->trainer('Piet Trainer');

        $training = Training::factory()->for($this->school)->for($groep)->upcoming()->create();
        $training->trainers()->attach($trainer->id);

        $this->actingAs($this->eigenaar)
            ->get('/trainings/'.$training->id)
            ->assertInertia(fn ($page) => $page->where('training.trainers.0.name', 'Piet Trainer'));

        $this->actingAs($this->eigenaar)
            ->get('/trainings')
            ->assertInertia(fn ($page) => $page->where('upcoming.0.trainers.0', 'Piet Trainer'));
    }

    public function test_een_gekoppelde_trainer_beperkt_niemand(): void
    {
        $groep = Group::factory()->for($this->school)->create();
        $vaste = $this->trainer('Vaste Trainer');
        $invaller = $this->trainer('Invaller');

        $training = Training::factory()->for($this->school)->for($groep)->upcoming()->create();
        $training->trainers()->attach($vaste->id);

        $speler = Player::factory()->for($this->school)->keeper()->create();
        $speler->groups()->attach($groep->id);

        // De invaller staat er niet bij, maar mag wel gewoon afvinken.
        $this->actingAs($invaller)
            ->patch("/trainings/{$training->id}/attendance/{$speler->id}", ['status' => 'present'])
            ->assertRedirect();

        $this->assertDatabaseHas('attendances', ['training_id' => $training->id, 'status' => 'present']);
    }
}
