<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        // Geen MustVerifyEmail: de kolom betekent "geactiveerd" en blijft staan.
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/settings/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_de_enige_eigenaar_kan_zijn_account_niet_verwijderen(): void
    {
        $this->seed(RoleSeeder::class);
        $school = School::factory()->create();
        $eigenaar = User::factory()->for($school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $this->actingAs($eigenaar)
            ->from('/settings/profile')
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertSessionHasErrors('password')
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($eigenaar->fresh());
        $this->assertAuthenticatedAs($eigenaar);
    }

    public function test_een_eigenaar_naast_een_andere_eigenaar_kan_wel_weg_en_zijn_meldingen_gaan_mee(): void
    {
        $this->seed(RoleSeeder::class);
        $school = School::factory()->create();
        $eigenaar = User::factory()->for($school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);
        $tweede = User::factory()->for($school)->create();
        $tweede->assignRole(Role::Eigenaar->value);

        $eigenaar->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'test',
            'data' => [],
        ]);

        $this->actingAs($eigenaar)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertNull($eigenaar->fresh());
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $eigenaar->id, 'notifiable_type' => User::class]);
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh());
    }
}
