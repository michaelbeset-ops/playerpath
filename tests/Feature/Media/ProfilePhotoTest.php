<?php

namespace Tests\Feature\Media;

use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Media\ProfilePhoto;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Profielfoto's van spelers en accounts.
 *
 * Wat hier echt toe doet: wie de foto van een kind mag zetten, dat de oude
 * foto verdwijnt, en dat de bestandsnaam niet te raden is aan de hand van een
 * id — die foto komt namelijk ook op een deelbare spelerskaart.
 */
class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);
    }

    protected function foto(string $naam = 'pasfoto.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($naam, 900, 1200);
    }

    public function test_de_eigenaar_zet_een_foto_op_een_speler(): void
    {
        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)
            ->post("/players/{$speler->id}/photo", ['photo' => $this->foto()])
            ->assertRedirect();

        $pad = $speler->fresh()->photo_path;

        $this->assertNotNull($pad);
        Storage::disk('public')->assertExists($pad);

        // Niet te raden aan de hand van het id van het kind: veertig
        // willekeurige tekens, niet het nummer uit de URL.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.jpg$/', basename($pad));
    }

    public function test_de_foto_wordt_vierkant(): void
    {
        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)->post("/players/{$speler->id}/photo", ['photo' => $this->foto()]);

        $inhoud = Storage::disk('public')->get($speler->fresh()->photo_path);
        $maten = getimagesizefromstring($inhoud);

        $this->assertSame(ProfilePhoto::ZIJDE, $maten[0]);
        $this->assertSame(ProfilePhoto::ZIJDE, $maten[1]);
    }

    public function test_een_nieuwe_foto_ruimt_de_oude_op(): void
    {
        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)->post("/players/{$speler->id}/photo", ['photo' => $this->foto('een.jpg')]);
        $eerste = $speler->fresh()->photo_path;

        $this->actingAs($this->eigenaar)->post("/players/{$speler->id}/photo", ['photo' => $this->foto('twee.jpg')]);
        $tweede = $speler->fresh()->photo_path;

        $this->assertNotSame($eerste, $tweede);
        Storage::disk('public')->assertMissing($eerste);
        Storage::disk('public')->assertExists($tweede);
    }

    public function test_verwijderen_haalt_het_bestand_ook_echt_weg(): void
    {
        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($this->eigenaar)->post("/players/{$speler->id}/photo", ['photo' => $this->foto()]);
        $pad = $speler->fresh()->photo_path;

        $this->actingAs($this->eigenaar)->delete("/players/{$speler->id}/photo")->assertRedirect();

        $this->assertNull($speler->fresh()->photo_path);
        Storage::disk('public')->assertMissing($pad);
    }

    public function test_een_trainer_komt_niet_aan_de_foto_van_een_kind(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $speler = Player::factory()->for($this->school)->create();

        $this->actingAs($trainer)
            ->post("/players/{$speler->id}/photo", ['photo' => $this->foto()])
            ->assertForbidden();
    }

    public function test_een_speler_van_een_andere_school_is_onbereikbaar(): void
    {
        $vreemde = Player::factory()->for(School::factory()->create())->create();

        $this->actingAs($this->eigenaar)
            ->post("/players/{$vreemde->id}/photo", ['photo' => $this->foto()])
            ->assertNotFound();
    }

    public function test_iedereen_mag_zijn_eigen_foto_zetten(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $this->actingAs($ouder)
            ->post("/users/{$ouder->id}/photo", ['photo' => $this->foto()])
            ->assertRedirect();

        $this->assertNotNull($ouder->fresh()->photo_path);
    }

    public function test_een_ouder_komt_niet_aan_de_foto_van_een_ander(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $andere = User::factory()->for($this->school)->create();
        $andere->assignRole(Role::Ouder->value);

        $this->actingAs($ouder)
            ->post("/users/{$andere->id}/photo", ['photo' => $this->foto()])
            ->assertForbidden();
    }

    public function test_de_eigenaar_mag_de_foto_van_een_ouder_zetten(): void
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $this->actingAs($this->eigenaar)
            ->post("/users/{$ouder->id}/photo", ['photo' => $this->foto()])
            ->assertRedirect();

        $this->assertNotNull($ouder->fresh()->photo_path);
    }

    public function test_een_account_van_een_andere_school_is_onbereikbaar(): void
    {
        $vreemde = User::factory()->for(School::factory()->create())->create();

        $this->actingAs($this->eigenaar)
            ->post("/users/{$vreemde->id}/photo", ['photo' => $this->foto()])
            ->assertForbidden();
    }

    public function test_een_svg_wordt_geweigerd(): void
    {
        $speler = Player::factory()->for($this->school)->create();

        // Geen svg: dat is uitvoerbare opmaak, en die komt op een pagina die
        // gedeeld kan worden.
        $this->actingAs($this->eigenaar)
            ->post("/players/{$speler->id}/photo", ['photo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])
            ->assertSessionHasErrors('photo');
    }

    public function test_de_foto_staat_op_de_gedeelde_kaart_maar_de_achternaam_niet(): void
    {
        $speler = Player::factory()->for($this->school)->create([
            'first_name' => 'Sem',
            'last_name' => 'de Vries',
        ]);

        $this->actingAs($this->eigenaar)->post("/players/{$speler->id}/photo", ['photo' => $this->foto()]);
        $this->actingAs($this->eigenaar)->post("/players/{$speler->id}/share");

        $token = $speler->fresh()->share_token;

        $this->get("/kaart/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('card.name', 'Sem d.')
                ->where('card.photo', fn ($foto) => is_string($foto) && $foto !== '')
            );
    }
}
