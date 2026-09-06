<?php

namespace Tests\Feature\Communication;

use App\Enums\Feature;
use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Notifications\Verjaardag;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * De automatische verjaardagsfelicitatie.
 *
 * Twee dingen die hier echt toe doen: hij staat standaard uit, en hij gaat
 * hooguit één keer per verjaardag de deur uit. Een school die ongevraagd mails
 * verstuurt of iemand twee keer feliciteert, is een school die haar klanten
 * laat merken dat het systeem niet klopt.
 */
class BirthdayGreetingTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create(['birthday_greeting' => true]);
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->travelTo('2026-06-10 08:00:00');
    }

    protected function jarigeSpeler(string $geboren = '2014-06-10'): Player
    {
        return Player::factory()->for($this->school)->create([
            'first_name' => 'Sem',
            'last_name' => 'de Vries',
            'date_of_birth' => $geboren,
        ]);
    }

    protected function ouderVan(Player $speler): User
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $ouder->children()->attach($speler->id);

        return $ouder;
    }

    public function test_de_ouders_krijgen_de_felicitatie(): void
    {
        $speler = $this->jarigeSpeler();
        $ouder = $this->ouderVan($speler);

        $this->artisan('players:birthday')->assertSuccessful();

        Notification::assertSentTo($ouder, Verjaardag::class, function (Verjaardag $melding) {
            // Twaalf, niet elf: hij wórdt vandaag twaalf.
            return $melding->leeftijd === 12;
        });
    }

    public function test_een_speler_met_eigen_inlog_krijgt_hem_zelf(): void
    {
        $speler = $this->jarigeSpeler();
        $ouder = $this->ouderVan($speler);

        $account = User::factory()->for($this->school)->create();
        $account->assignRole(Role::Speler->value);
        $speler->update(['user_id' => $account->id]);

        $this->artisan('players:birthday');

        // Niet allebei: dan krijgen een kind van acht en zijn moeder allebei
        // "gefeliciteerd, jij bent jarig".
        Notification::assertSentTo($account, Verjaardag::class);
        Notification::assertNotSentTo($ouder, Verjaardag::class);
    }

    public function test_twee_keer_draaien_feliciteert_niet_twee_keer(): void
    {
        $speler = $this->jarigeSpeler();
        $this->ouderVan($speler);

        $this->artisan('players:birthday');
        $this->artisan('players:birthday');

        Notification::assertSentTimes(Verjaardag::class, 1);
        $this->assertSame('2026-06-10', $speler->fresh()->greeted_on->toDateString());
    }

    public function test_volgend_jaar_wel_weer(): void
    {
        $speler = $this->jarigeSpeler();
        $this->ouderVan($speler);

        $this->artisan('players:birthday');

        $this->travelTo('2027-06-10 08:00:00');
        $this->artisan('players:birthday');

        Notification::assertSentTimes(Verjaardag::class, 2);
    }

    public function test_uitgezet_stuurt_niets(): void
    {
        $this->school->update(['birthday_greeting' => false]);

        $speler = $this->jarigeSpeler();
        $this->ouderVan($speler);

        $this->artisan('players:birthday');

        Notification::assertNothingSent();
    }

    public function test_zonder_de_functie_mededelingen_stuurt_de_school_niets(): void
    {
        $this->school->update(['features' => [Feature::Mededelingen->value => false]]);

        $speler = $this->jarigeSpeler();
        $this->ouderVan($speler);

        $this->artisan('players:birthday');

        Notification::assertNothingSent();
    }

    public function test_een_gestopte_speler_krijgt_geen_felicitatie(): void
    {
        $speler = $this->jarigeSpeler();
        $this->ouderVan($speler);
        $speler->update(['is_active' => false]);

        $this->artisan('players:birthday');

        Notification::assertNothingSent();
    }

    public function test_wie_vandaag_niet_jarig_is_krijgt_niets(): void
    {
        $speler = $this->jarigeSpeler('2014-06-11');
        $this->ouderVan($speler);

        $this->artisan('players:birthday');

        Notification::assertNothingSent();
    }

    public function test_een_proefdraai_verstuurt_niets(): void
    {
        $speler = $this->jarigeSpeler();
        $this->ouderVan($speler);

        $this->artisan('players:birthday', ['--dry-run' => true])->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($speler->fresh()->greeted_on);
    }

    public function test_de_eigenaar_zet_hem_aan_en_geeft_een_eigen_zin_mee(): void
    {
        $this->school->update(['birthday_greeting' => false]);

        $this->actingAs($this->eigenaar)
            ->patch('/announcements/verjaardagen', ['enabled' => true, 'message' => 'Tot zaterdag op het veld!'])
            ->assertRedirect();

        $school = $this->school->fresh();
        $this->assertTrue($school->birthday_greeting);
        $this->assertSame('Tot zaterdag op het veld!', $school->birthday_message);
    }

    public function test_een_lege_zin_valt_terug_op_de_standaardtekst(): void
    {
        $this->actingAs($this->eigenaar)
            ->patch('/announcements/verjaardagen', ['enabled' => true, 'message' => '   ']);

        $this->assertNull($this->school->fresh()->birthday_message);
    }

    public function test_een_trainer_komt_er_niet_bij(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->get('/announcements/verjaardagen')->assertForbidden();
        $this->actingAs($trainer)->patch('/announcements/verjaardagen', ['enabled' => true])->assertForbidden();
    }

    public function test_het_scherm_toont_wie_er_aankomt(): void
    {
        $this->jarigeSpeler();

        $this->actingAs($this->eigenaar)
            ->get('/announcements/verjaardagen')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('enabled', true)
                ->count('upcoming', 1)
                ->where('upcoming.0.turns', 12)
            );
    }
}
