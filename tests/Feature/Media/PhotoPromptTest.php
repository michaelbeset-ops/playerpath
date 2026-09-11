<?php

namespace Tests\Feature\Media;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * De foto vroeg in het leven van een ouder- of speleraccount.
 *
 * Wie de foto van een kind mag zetten is hier de kern: de eigenaar, de ouders
 * van dít kind, en het kind zelf als het een eigen inlog heeft. Niemand
 * anders — een foto van een kind is niet iets wat je bij een ander neerzet.
 */
class PhotoPromptTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);
    }

    protected function ouderMet(Player ...$kinderen): User
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        foreach ($kinderen as $kind) {
            $kind->guardians()->attach($ouder->id, ['relationship' => 'moeder']);
        }

        return $ouder;
    }

    public function test_een_ouder_ziet_na_het_activeren_het_fotoscherm_voor_zijn_kind(): void
    {
        $kind = Player::factory()->for($this->school)->create(['first_name' => 'Sem']);
        $ouder = $this->ouderMet($kind);

        $this->actingAs($ouder)
            ->get('/welkom/foto')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('onboarding/PhotoPrompt')
                ->count('players', 1)
                ->where('players.0.first_name', 'Sem')
                ->where('self', false)
            );

        // De ouder zet de foto van zijn eigen kind.
        $this->actingAs($ouder)
            ->post("/players/{$kind->id}/photo", ['photo' => UploadedFile::fake()->image('sem.jpg', 800, 800)])
            ->assertRedirect();

        $this->assertNotNull($kind->fresh()->photo_path);

        // Alles heeft een foto: dan is het scherm er niet meer.
        $this->actingAs($ouder)->get('/welkom/foto')->assertRedirect('/dashboard');
    }

    public function test_een_ouder_komt_niet_aan_de_foto_van_andermans_kind(): void
    {
        $eigen = Player::factory()->for($this->school)->create();
        $ander = Player::factory()->for($this->school)->create();
        $ouder = $this->ouderMet($eigen);

        $this->actingAs($ouder)
            ->post("/players/{$ander->id}/photo", ['photo' => UploadedFile::fake()->image('x.jpg')])
            ->assertForbidden();

        $this->actingAs($ouder)
            ->delete("/players/{$ander->id}/photo")
            ->assertForbidden();
    }

    public function test_een_kind_met_eigen_inlog_zet_zijn_eigen_foto(): void
    {
        $account = User::factory()->for($this->school)->create();
        $account->assignRole(Role::Speler->value);
        $kind = Player::factory()->for($this->school)->create();
        $kind->forceFill(['user_id' => $account->id])->save();

        $this->actingAs($account)
            ->get('/welkom/foto')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->count('players', 1)->where('self', true));

        $this->actingAs($account)
            ->post("/players/{$kind->id}/photo", ['photo' => UploadedFile::fake()->image('ik.jpg', 600, 600)])
            ->assertRedirect();

        $this->assertNotNull($kind->fresh()->photo_path);

        // De kaart biedt het kind zelf de knop, en de eigenaar ook.
        $this->actingAs($account)
            ->get("/players/{$kind->id}/card")
            ->assertInertia(fn ($page) => $page->where('canPhoto', true));
    }

    public function test_zonder_kind_zonder_foto_is_er_geen_fotoscherm(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->get('/welkom/foto')->assertRedirect('/dashboard');
    }

    public function test_het_gezinsdashboard_herinnert_aan_de_foto(): void
    {
        $kind = Player::factory()->for($this->school)->create();
        $ouder = $this->ouderMet($kind);

        $this->actingAs($ouder)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('children.0.photo', null));

        // En de trainer krijgt de knop op de kaart niet: de foto van een kind
        // is niet van hem.
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)
            ->get("/players/{$kind->id}/card")
            ->assertInertia(fn ($page) => $page->where('canPhoto', false));
    }
}
