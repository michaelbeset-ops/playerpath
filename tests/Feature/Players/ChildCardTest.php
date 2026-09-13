<?php

namespace Tests\Feature\Players;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De kind-link: het kind komt zonder wachtwoord op zijn eigen account.
 */
class ChildCardTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);

        $this->speler = Player::factory()->for($this->school)->keeper()->create([
            'first_name' => 'Sem',
            'last_name' => 'de Vries',
        ]);
    }

    public function test_de_kind_link_staat_standaard_uit(): void
    {
        $this->assertNull($this->speler->child_token);
        $this->assertFalse($this->speler->hasChildLink());

        $this->actingAs($this->eigenaar)
            ->get("/players/{$this->speler->id}/card")
            ->assertInertia(fn ($page) => $page->where('childLink.can', true)->where('childLink.url', null));
    }

    public function test_de_link_logt_het_kind_in_op_een_eigen_speleraccount(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $this->speler->guardians()->attach($ouder->id);

        $this->actingAs($ouder)->post("/players/{$this->speler->id}/kind-link")->assertRedirect()->assertSessionHas('status');

        $token = $this->speler->refresh()->child_token;
        $this->assertNotNull($token);
        $this->assertSame(48, strlen($token));
        $this->assertNull($this->speler->user_id, 'Het account ontstaat pas als de link geopend wordt.');

        // De link openen, zonder ingelogd te zijn: ingelogd als het kind, en
        // meteen de vraag om de app op het beginscherm te zetten.
        $this->app['auth']->forgetGuards();
        $this->get("/kind/{$token}")->assertRedirect('/dashboard')->assertSessionHas('kindWelkom', true);

        $kind = $this->speler->refresh()->user;
        $this->assertNotNull($kind);
        $this->assertAuthenticatedAs($kind);
        $this->assertTrue($kind->isSpeler());
        $this->assertSame('Sem de Vries', $kind->name);
        $this->assertSame($this->school->id, $kind->school_id);
        $this->assertFalse($kind->wantsEmail('rapport'), 'Een kind zonder mailbox krijgt geen mail.');

        // Het dashboard is dat van een speler, met zijn eigen kaart.
        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('view', 'speler'));

        // Nog eens openen maakt geen tweede account.
        $this->app['auth']->forgetGuards();
        $this->get("/kind/{$token}")->assertRedirect('/dashboard');
        $this->assertSame($kind->id, $this->speler->refresh()->user_id);
        $this->assertSame(1, User::where('school_id', $this->school->id)->role(Role::Speler->value)->count());
    }

    public function test_een_kind_met_een_eigen_inlog_houdt_dat_account(): void
    {
        $bestaand = User::factory()->for($this->school)->create();
        $bestaand->assignRole(Role::Speler->value);
        $this->speler->update(['user_id' => $bestaand->id]);

        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/kind-link");
        $token = $this->speler->refresh()->child_token;

        $this->app['auth']->forgetGuards();
        $this->get("/kind/{$token}")->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($bestaand);
        $this->assertSame($bestaand->id, $this->speler->refresh()->user_id);
    }

    public function test_een_ingelogde_ouder_die_de_link_opent_wordt_het_kind(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $this->speler->guardians()->attach($ouder->id);

        $this->actingAs($ouder)->post("/players/{$this->speler->id}/kind-link");
        $token = $this->speler->refresh()->child_token;

        $this->actingAs($ouder)->get("/kind/{$token}")->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($this->speler->refresh()->user);
    }

    public function test_een_trainer_of_een_andere_ouder_mag_geen_kind_link_maken(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);
        $this->actingAs($trainer)->post("/players/{$this->speler->id}/kind-link")->assertForbidden();

        $andereOuder = User::factory()->for($this->school)->create();
        $andereOuder->assignRole(Role::Ouder->value);
        $this->actingAs($andereOuder)->post("/players/{$this->speler->id}/kind-link")->assertForbidden();

        $this->assertNull($this->speler->refresh()->child_token);
    }

    public function test_opnieuw_maken_geeft_een_nieuw_token_en_uitzetten_maakt_de_link_dood(): void
    {
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/kind-link");
        $eerste = $this->speler->refresh()->child_token;

        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/kind-link");
        $tweede = $this->speler->refresh()->child_token;

        $this->assertNotSame($eerste, $tweede);

        $this->app['auth']->forgetGuards();
        $this->get("/kind/{$eerste}")->assertNotFound();
        $this->assertGuest();

        $this->actingAs($this->eigenaar)->delete("/players/{$this->speler->id}/kind-link")->assertRedirect();

        $this->assertNull($this->speler->refresh()->child_token);
        $this->app['auth']->forgetGuards();
        $this->get("/kind/{$tweede}")->assertNotFound();
    }

    public function test_een_speler_die_stopt_houdt_geen_open_links(): void
    {
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/kind-link");
        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/share");
        $speler = $this->speler->refresh();
        $kind = $speler->child_token;
        $deel = $speler->share_token;

        $speler->update(['is_active' => false]);

        $this->assertNull($speler->refresh()->child_token);
        $this->assertNull($speler->share_token);

        $this->app['auth']->forgetGuards();
        $this->get("/kind/{$kind}")->assertNotFound();
        $this->get("/kaart/{$deel}")->assertNotFound();
    }

    public function test_een_onbekend_token_geeft_niets(): void
    {
        $this->get('/kind/'.str_repeat('a', 48))->assertNotFound();
        $this->assertGuest();
    }
}
