<?php

namespace Tests\Feature\Onboarding;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Notifications\Uitnodiging;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Ouder koppelen, speler koppelen, of allebei: een speler met een eigen
 * e-mailadres krijgt een eigen inlog op zijn eigen profiel.
 */
class InvitePlayerTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->speler = Player::factory()->for($this->school)->create(['first_name' => 'Tobias']);
    }

    protected function nodigUit(string $rol, string $ontvanger, ?User $door = null)
    {
        return $this->actingAs($door ?? $this->eigenaar)->post('/uitnodigingen', [
            'role' => $rol,
            'recipients' => $ontvanger,
            'player_ids' => [$this->speler->id],
        ]);
    }

    public function test_ouder_en_speler_kunnen_allebei_worden_uitgenodigd(): void
    {
        $this->nodigUit('ouder', 'Marieke <marieke@voorbeeld.nl>')->assertSessionHasNoErrors();
        $this->nodigUit('speler', 'Tobias <tobias@voorbeeld.nl>')->assertSessionHasNoErrors();

        $uitnodiging = Invitation::where('email', 'tobias@voorbeeld.nl')->firstOrFail();
        $this->assertSame('speler', $uitnodiging->role);
        $this->assertSame([$this->speler->id], $uitnodiging->player_ids);

        Notification::assertSentOnDemand(Uitnodiging::class);

        $this->actingAs($this->eigenaar)
            ->get("/players/{$this->speler->id}")
            ->assertInertia(fn ($page) => $page
                ->where('account', null)
                ->count('invitations', 1)
                ->count('playerInvitations', 1)
            );

        // Activeren: het account hoort bij dit spelersprofiel.
        auth()->logout();
        $this->post('/uitnodiging/'.$uitnodiging->token, [
            'password' => 'Keeper-2026!',
            'password_confirmation' => 'Keeper-2026!',
        ])->assertRedirect();

        $gebruiker = User::where('email', 'tobias@voorbeeld.nl')->firstOrFail();
        $this->assertTrue($gebruiker->hasRole(Role::Speler->value));
        $this->assertSame($gebruiker->id, $this->speler->refresh()->user_id);

        // De ouder activeert daarna ook, en beide zijn gekoppeld.
        auth()->logout();
        $ouder = Invitation::where('email', 'marieke@voorbeeld.nl')->firstOrFail();
        $this->post('/uitnodiging/'.$ouder->token, [
            'password' => 'Ouder-2026!',
            'password_confirmation' => 'Ouder-2026!',
        ])->assertRedirect();

        $this->assertSame($gebruiker->id, $this->speler->refresh()->user_id);
        $this->assertTrue($this->speler->guardians()->where('email', 'marieke@voorbeeld.nl')->exists());

        $this->actingAs($this->eigenaar)
            ->get("/players/{$this->speler->id}")
            ->assertInertia(fn ($page) => $page->where('account.email', 'tobias@voorbeeld.nl')->where('account.kind', false));

        // Een tweede eigen account kan niet.
        $this->nodigUit('speler', 'nogeen@voorbeeld.nl')->assertSessionHasErrors('recipients');
    }

    public function test_een_speler_nodig_je_een_voor_een_uit_en_alleen_als_eigenaar(): void
    {
        $this->nodigUit('speler', "a@voorbeeld.nl\nb@voorbeeld.nl")->assertSessionHasErrors('recipients');

        $this->actingAs($this->eigenaar)->post('/uitnodigingen', [
            'role' => 'speler',
            'recipients' => 'tobias@voorbeeld.nl',
        ])->assertSessionHasErrors('recipients');

        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);
        $this->nodigUit('speler', 'tobias@voorbeeld.nl', $trainer)->assertForbidden();

        $this->assertSame(0, Invitation::count());
    }
}
