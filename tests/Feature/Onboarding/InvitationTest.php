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
 * Trainers en ouders uitnodigen, en de uitnodiging inwisselen.
 *
 * Wat hier echt toe doet: bulk werkt, een ouder krijgt zijn kind gekoppeld
 * zodra hij activeert, en een link die verlopen of gebruikt is doet niets meer.
 */
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create(['name' => 'Keepersschool Voorbeeld']);
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);
    }

    public function test_een_hele_lijst_in_een_keer(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/uitnodigingen', [
                'role' => 'trainer',
                'recipients' => "Sanne Bakker <sanne@voorbeeld.nl>\nKarim el Idrissi, karim@voorbeeld.nl\nmarieke@voorbeeld.nl\n\n",
            ])
            ->assertRedirect();

        $this->assertSame(3, Invitation::count());

        $sanne = Invitation::where('email', 'sanne@voorbeeld.nl')->first();
        $this->assertSame('Sanne Bakker', $sanne->name);

        // Een kaal adres levert een bruikbare naam op: beter een naam die niet
        // klopt dan een uitnodiging die niet verstuurd wordt.
        $this->assertSame('Marieke', Invitation::where('email', 'marieke@voorbeeld.nl')->value('name'));

        Notification::assertSentTimes(Uitnodiging::class, 3);
    }

    public function test_een_adres_met_een_account_wordt_overgeslagen(): void
    {
        User::factory()->for($this->school)->create(['email' => 'bestaat@voorbeeld.nl']);

        $this->actingAs($this->eigenaar)->post('/uitnodigingen', [
            'role' => 'trainer',
            'recipients' => "Bestaat Al <bestaat@voorbeeld.nl>\nNieuw <nieuw@voorbeeld.nl>",
        ]);

        $this->assertSame(1, Invitation::count());
        $this->assertSame('nieuw@voorbeeld.nl', Invitation::first()->email);
    }

    /** Een adres van een andere school wordt niet genoemd: anders kun je zo testen wie waar klant is. */
    public function test_een_adres_van_een_andere_school_wordt_niet_genoemd(): void
    {
        User::factory()->for(School::factory()->create())->create(['email' => 'elders@voorbeeld.nl']);
        User::factory()->for($this->school)->create(['email' => 'eigen@voorbeeld.nl']);

        $this->actingAs($this->eigenaar)
            ->post('/uitnodigingen', [
                'role' => 'trainer',
                'recipients' => "elders@voorbeeld.nl\neigen@voorbeeld.nl",
            ])
            ->assertSessionHas('status', fn (string $tekst) => str_contains($tekst, 'eigen@voorbeeld.nl')
                && ! str_contains($tekst, 'elders@voorbeeld.nl')
                && str_contains($tekst, 'Eén adres kon niet worden uitgenodigd.'));

        $this->assertSame(0, Invitation::count());
    }

    public function test_een_ouder_krijgt_zijn_kind_gekoppeld_bij_activatie(): void
    {
        $kind = Player::factory()->for($this->school)->create(['first_name' => 'Sem']);

        $this->actingAs($this->eigenaar)->post('/uitnodigingen', [
            'role' => 'ouder',
            'recipients' => 'Marieke de Vries <marieke@voorbeeld.nl>',
            'player_ids' => [$kind->id],
            'relationship' => 'moeder',
        ]);

        $uitnodiging = Invitation::first();

        $this->get('/uitnodiging/'.$uitnodiging->token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('auth/AcceptInvitation')
                ->where('invitation.school', 'Keepersschool Voorbeeld')
                ->where('invitation.children', ['Sem'])
            );

        // Een ouder gaat eerst langs de foto van zijn kind; daar mag hij
        // "later" zeggen, maar de vraag komt op het moment dat hij er tijd
        // voor heeft.
        $this->post('/uitnodiging/'.$uitnodiging->token, [
            'password' => 'EenGoedWachtwoord!1',
            'password_confirmation' => 'EenGoedWachtwoord!1',
        ])->assertRedirect('/welkom/foto');

        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();

        $this->assertTrue($ouder->isOuder());
        // Meteen ingelogd: hem na het kiezen van een wachtwoord alsnog een
        // inlogscherm voorschotelen is waar mensen afhaken.
        $this->assertAuthenticatedAs($ouder);

        app(Tenancy::class)->set($this->school);
        $this->assertTrue($kind->guardians()->whereKey($ouder->id)->exists());
        $this->assertSame('moeder', $kind->guardians()->first()->pivot->relationship);
    }

    public function test_een_gebruikte_link_doet_daarna_niets_meer(): void
    {
        $this->actingAs($this->eigenaar)->post('/uitnodigingen', [
            'role' => 'trainer',
            'recipients' => 'Sanne <sanne@voorbeeld.nl>',
        ]);

        $token = Invitation::first()->token;

        $this->post('/uitnodiging/'.$token, [
            'password' => 'EenGoedWachtwoord!1',
            'password_confirmation' => 'EenGoedWachtwoord!1',
        ]);

        $this->post('/logout');

        $this->get('/uitnodiging/'.$token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('invitation', null));
    }

    public function test_een_verlopen_link_doet_niets_meer(): void
    {
        $this->actingAs($this->eigenaar)->post('/uitnodigingen', [
            'role' => 'trainer',
            'recipients' => 'Sanne <sanne@voorbeeld.nl>',
        ]);

        $uitnodiging = Invitation::first();
        $uitnodiging->forceFill(['expires_at' => now()->subDay()])->save();

        $this->get('/uitnodiging/'.$uitnodiging->token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('invitation', null));

        $this->post('/uitnodiging/'.$uitnodiging->token, [
            'password' => 'EenGoedWachtwoord!1',
            'password_confirmation' => 'EenGoedWachtwoord!1',
        ])->assertRedirect('/login');
    }

    /** Opnieuw versturen maakt een nieuw token: twee geldige links is er één te veel. */
    public function test_opnieuw_versturen_maakt_de_oude_link_ongeldig(): void
    {
        $this->actingAs($this->eigenaar)->post('/uitnodigingen', [
            'role' => 'trainer',
            'recipients' => 'Sanne <sanne@voorbeeld.nl>',
        ]);

        $uitnodiging = Invitation::first();
        $oud = $uitnodiging->token;

        $this->actingAs($this->eigenaar)->post('/uitnodigingen/'.$uitnodiging->id.'/opnieuw')->assertRedirect();

        $uitnodiging->refresh();

        $this->assertNotSame($oud, $uitnodiging->token);
        $this->assertSame(2, $uitnodiging->sent_count);
        Notification::assertSentTimes(Uitnodiging::class, 2);

        $this->get('/uitnodiging/'.$oud)->assertInertia(fn ($page) => $page->where('invitation', null));
    }

    public function test_een_school_nodigt_niet_uit_bij_een_andere_school(): void
    {
        $andere = School::factory()->create();
        $vreemde = User::factory()->for($andere)->create();
        $vreemde->assignRole(Role::Eigenaar->value);

        $this->actingAs($this->eigenaar)->post('/uitnodigingen', [
            'role' => 'trainer',
            'recipients' => 'Sanne <sanne@voorbeeld.nl>',
        ]);

        $uitnodiging = Invitation::first();

        // Twee sloten op dezelfde deur: de global scope maakt een uitnodiging
        // van een andere school onvindbaar (404), en de controller weigert hem
        // daarnaast expliciet op school_id (403). Welke van de twee als eerste
        // dichtzit hangt af van het moment waarop de school bekend is; dát het
        // dicht zit niet.
        $this->assertContains(
            $this->actingAs($vreemde)->post('/uitnodigingen/'.$uitnodiging->id.'/opnieuw')->getStatusCode(),
            [403, 404],
        );

        $this->assertContains(
            $this->actingAs($vreemde)->delete('/uitnodigingen/'.$uitnodiging->id)->getStatusCode(),
            [403, 404],
        );

        $this->assertSame(1, Invitation::withoutSchoolScope()->count());
    }

    public function test_een_trainer_nodigt_niemand_uit(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->post('/uitnodigingen', [
            'role' => 'trainer',
            'recipients' => 'Sanne <sanne@voorbeeld.nl>',
        ])->assertForbidden();
    }

    public function test_een_lijst_zonder_geldig_adres_levert_een_melding_op(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/uitnodigingen', ['role' => 'trainer', 'recipients' => 'Sanne Bakker'])
            ->assertSessionHasErrors('recipients');

        $this->assertSame(0, Invitation::count());
    }
}
