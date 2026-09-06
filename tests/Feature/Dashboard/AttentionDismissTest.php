<?php

namespace Tests\Feature\Dashboard;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Dashboard\AttentionItems;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het aandacht-blok wegklikken.
 *
 * Wegklikken betekent "dit heb ik gezien", niet "waarschuw me nooit meer".
 * Zodra er iets verandert staat het blok er weer — anders weet een school een
 * half jaar later niet dat er zeven rekeningen openstaan omdat iemand ooit op
 * een kruisje drukte.
 */
class AttentionDismissTest extends TestCase
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
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->speler = Player::factory()->for($this->school)->create();
    }

    protected function mislukteBetaling(): Payment
    {
        return Payment::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'status' => PaymentStatus::Failed,
            'amount_cents' => 2750,
        ]);
    }

    protected function vingerafdruk(): string
    {
        $items = app(AttentionItems::class)->for($this->eigenaar->fresh());

        return app(AttentionItems::class)->signature($items);
    }

    public function test_wegklikken_laat_het_blok_verdwijnen(): void
    {
        $this->mislukteBetaling();

        $this->actingAs($this->eigenaar)
            ->post('/dashboard/aandacht/gezien', ['signature' => $this->vingerafdruk()])
            ->assertRedirect();

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('attentionDismissed', true));
    }

    public function test_het_blok_komt_terug_zodra_er_iets_verandert(): void
    {
        $this->mislukteBetaling();

        $this->actingAs($this->eigenaar)->post('/dashboard/aandacht/gezien', ['signature' => $this->vingerafdruk()]);

        // Nog een mislukte betaling: de tekst verandert, dus het blok komt terug.
        $this->mislukteBetaling();

        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('attentionDismissed', false));
    }

    public function test_dezelfde_signalen_blijven_weg(): void
    {
        $this->mislukteBetaling();

        $this->actingAs($this->eigenaar)->post('/dashboard/aandacht/gezien', ['signature' => $this->vingerafdruk()]);

        // Niets veranderd, dus het blijft weg.
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('attentionDismissed', true));
    }

    public function test_een_verzonnen_vingerafdruk_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/dashboard/aandacht/gezien', ['signature' => 'onzin'])
            ->assertSessionHasErrors('signature');
    }

    public function test_een_ouder_heeft_hier_niets_te_zoeken(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $ouder->children()->attach($this->speler->id);

        $this->actingAs($ouder)
            ->post('/dashboard/aandacht/gezien', ['signature' => str_repeat('a', 32)])
            ->assertNotFound();
    }
}
