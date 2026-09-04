<?php

namespace Tests\Feature\Players;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GuardianTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->school = School::factory()->create();
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);

        $this->speler = Player::factory()->for($this->school)->keeper()->create();
    }

    public function test_een_bestaande_ouder_kan_gekoppeld_worden(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $this->actingAs($this->eigenaar)
            ->post('/players/'.$this->speler->id.'/guardians', [
                'user_id' => $ouder->id,
                'relationship' => 'moeder',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('guardian_player', [
            'school_id' => $this->school->id,
            'user_id' => $ouder->id,
            'player_id' => $this->speler->id,
            'relationship' => 'moeder',
        ]);
    }

    public function test_een_ouder_van_een_andere_school_kan_niet_gekoppeld_worden(): void
    {
        $andereSchool = School::factory()->create();
        $vreemdeOuder = User::factory()->for($andereSchool)->create();
        $vreemdeOuder->assignRole(Role::Ouder->value);

        $this->actingAs($this->eigenaar)
            ->post('/players/'.$this->speler->id.'/guardians', ['user_id' => $vreemdeOuder->id])
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('guardian_player', 0);
    }

    public function test_een_nieuwe_ouder_krijgt_een_account_en_een_e_mail_om_een_wachtwoord_te_kiezen(): void
    {
        Notification::fake();

        $this->actingAs($this->eigenaar)
            ->post('/players/'.$this->speler->id.'/guardians/invite', [
                'name' => 'Marieke de Vries',
                'email' => 'marieke@voorbeeld.nl',
                'relationship' => 'moeder',
            ])
            ->assertRedirect();

        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();

        $this->assertSame($this->school->id, $ouder->school_id);
        $this->assertTrue($ouder->isOuder());
        $this->assertTrue($this->speler->guardians()->whereKey($ouder->id)->exists());

        Notification::assertSentTo($ouder, ResetPassword::class);
    }

    public function test_een_bestaand_e_mailadres_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/players/'.$this->speler->id.'/guardians/invite', [
                'name' => 'Dubbel',
                'email' => $this->eigenaar->email,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_een_koppeling_kan_verwijderd_worden(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $this->speler->guardians()->attach($ouder->id);

        $this->actingAs($this->eigenaar)
            ->delete('/players/'.$this->speler->id.'/guardians/'.$ouder->id)
            ->assertRedirect();

        $this->assertDatabaseCount('guardian_player', 0);
        // Het account zelf blijft bestaan.
        $this->assertDatabaseHas('users', ['id' => $ouder->id]);
    }

    public function test_een_trainer_mag_geen_ouders_koppelen(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $this->actingAs($trainer)
            ->post('/players/'.$this->speler->id.'/guardians', ['user_id' => $ouder->id])
            ->assertForbidden();
    }

    public function test_de_gekoppelde_ouder_ziet_daarna_de_kaart_van_het_kind(): void
    {
        Notification::fake();

        $this->actingAs($this->eigenaar)->post('/players/'.$this->speler->id.'/guardians/invite', [
            'name' => 'Marieke',
            'email' => 'marieke@voorbeeld.nl',
        ]);

        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();

        $this->actingAs($ouder)->get('/players/'.$this->speler->id.'/card')->assertOk();
    }
}
