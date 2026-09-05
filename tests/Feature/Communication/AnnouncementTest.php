<?php

namespace Tests\Feature\Communication;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Notifications\NieuweMededeling;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $trainer;

    protected Group $groep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = $this->gebruiker(Role::Eigenaar);
        $this->trainer = $this->gebruiker(Role::Trainer);

        $this->groep = Group::factory()->for($this->school)->create();
    }

    protected function gebruiker(Role $rol): User
    {
        $user = User::factory()->for($this->school)->create();
        $user->assignRole($rol->value);

        return $user;
    }

    /** Een speler met een ouder, optioneel in de groep en met een eigen inlog. */
    protected function gezin(bool $inGroep = true, bool $eigenInlog = false): array
    {
        $ouder = $this->gebruiker(Role::Ouder);
        $speler = Player::factory()->for($this->school)->create();
        $speler->guardians()->attach($ouder->id);

        if ($inGroep) {
            $speler->groups()->attach($this->groep->id);
        }

        if ($eigenInlog) {
            $spelerAccount = $this->gebruiker(Role::Speler);
            $speler->update(['user_id' => $spelerAccount->id]);

            return [$ouder, $speler, $spelerAccount];
        }

        return [$ouder, $speler, null];
    }

    public function test_een_bericht_aan_de_hele_school_bereikt_alle_ouders(): void
    {
        Notification::fake();

        [$ouderA] = $this->gezin();
        [$ouderB] = $this->gezin(inGroep: false);

        $this->actingAs($this->eigenaar)
            ->post('/announcements', ['title' => 'Zomerstop', 'body' => 'Geen trainingen in juli.'])
            ->assertRedirect();

        Notification::assertSentTo($ouderA, NieuweMededeling::class);
        Notification::assertSentTo($ouderB, NieuweMededeling::class);

        $this->assertSame(2, Announcement::firstOrFail()->recipients_count);
    }

    public function test_een_bericht_aan_een_groep_gaat_alleen_daarheen(): void
    {
        Notification::fake();

        [$binnen] = $this->gezin();
        [$buiten] = $this->gezin(inGroep: false);

        $this->actingAs($this->trainer)
            ->post('/announcements', ['title' => 'Andere zaal', 'body' => 'We staan in zaal 2.', 'group_id' => $this->groep->id]);

        Notification::assertSentTo($binnen, NieuweMededeling::class);
        Notification::assertNotSentTo($buiten, NieuweMededeling::class);
    }

    public function test_een_speler_met_eigen_inlog_krijgt_het_bericht_ook(): void
    {
        Notification::fake();

        [$ouder, , $spelerAccount] = $this->gezin(eigenInlog: true);

        $this->actingAs($this->eigenaar)->post('/announcements', ['title' => 'Nieuws', 'body' => 'Er is nieuws.']);

        Notification::assertSentTo($ouder, NieuweMededeling::class);
        Notification::assertSentTo($spelerAccount, NieuweMededeling::class);
    }

    public function test_een_ouder_met_twee_kinderen_krijgt_een_bericht(): void
    {
        Notification::fake();

        $ouder = $this->gebruiker(Role::Ouder);

        foreach (range(1, 2) as $i) {
            $speler = Player::factory()->for($this->school)->create();
            $speler->guardians()->attach($ouder->id);
            $speler->groups()->attach($this->groep->id);
        }

        $this->actingAs($this->eigenaar)
            ->post('/announcements', ['title' => 'Bericht', 'body' => 'Tekst.', 'group_id' => $this->groep->id]);

        Notification::assertSentToTimes($ouder, NieuweMededeling::class, 1);
        $this->assertSame(1, Announcement::firstOrFail()->recipients_count);
    }

    public function test_een_gestopte_speler_krijgt_niets_meer(): void
    {
        Notification::fake();

        [$ouder, $speler] = $this->gezin();
        $speler->update(['is_active' => false]);

        $this->actingAs($this->eigenaar)->post('/announcements', ['title' => 'Nieuws', 'body' => 'Tekst.']);

        Notification::assertNotSentTo($ouder, NieuweMededeling::class);
    }

    public function test_een_ouder_mag_zelf_geen_bericht_sturen(): void
    {
        $ouder = $this->gebruiker(Role::Ouder);

        $this->actingAs($ouder)->get('/announcements')->assertForbidden();
        $this->actingAs($ouder)->post('/announcements', ['title' => 'Hoi', 'body' => 'Tekst.'])->assertForbidden();
    }

    public function test_een_groep_van_een_andere_school_wordt_geweigerd(): void
    {
        $andere = School::factory()->create();
        $vreemdeGroep = Group::factory()->for($andere)->create();

        $this->actingAs($this->eigenaar)
            ->post('/announcements', ['title' => 'Hoi', 'body' => 'Tekst.', 'group_id' => $vreemdeGroep->id])
            ->assertSessionHasErrors('group_id');
    }

    public function test_een_training_afzeggen_bericht_de_hele_groep(): void
    {
        Notification::fake();

        [$ouder] = $this->gezin();
        [$buiten] = $this->gezin(inGroep: false);

        $training = Training::factory()->for($this->school)->create([
            'group_id' => $this->groep->id,
            'starts_at' => now()->addDays(2)->setTime(18, 0),
            'ends_at' => now()->addDays(2)->setTime(19, 30),
        ]);

        $this->actingAs($this->trainer)
            ->post("/trainings/{$training->id}/afzeggen", ['reason' => 'Het veld staat onder water.'])
            ->assertRedirect();

        $this->assertNotNull($training->refresh()->cancelled_at);
        $this->assertSame('Het veld staat onder water.', $training->cancellation_reason);

        Notification::assertSentTo($ouder, NieuweMededeling::class);
        Notification::assertNotSentTo($buiten, NieuweMededeling::class);

        // De reden staat letterlijk in het bericht dat de ouder krijgt.
        $bericht = Announcement::firstOrFail();
        $this->assertStringContainsString('Het veld staat onder water.', $bericht->body);
        $this->assertSame($training->id, $bericht->training_id);
    }

    public function test_een_reden_is_verplicht_bij_afzeggen(): void
    {
        $training = Training::factory()->for($this->school)->create(['group_id' => $this->groep->id]);

        $this->actingAs($this->trainer)
            ->post("/trainings/{$training->id}/afzeggen", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertNull($training->refresh()->cancelled_at);
    }

    public function test_twee_keer_afzeggen_stuurt_geen_tweede_bericht(): void
    {
        Notification::fake();

        [$ouder] = $this->gezin();
        $training = Training::factory()->for($this->school)->create(['group_id' => $this->groep->id]);

        $this->actingAs($this->trainer)->post("/trainings/{$training->id}/afzeggen", ['reason' => 'Ondergelopen.']);
        $this->actingAs($this->trainer)->post("/trainings/{$training->id}/afzeggen", ['reason' => 'Nog steeds.']);

        Notification::assertSentToTimes($ouder, NieuweMededeling::class, 1);
    }

    public function test_terugzetten_stuurt_bewust_geen_bericht(): void
    {
        Notification::fake();

        [$ouder] = $this->gezin();
        $training = Training::factory()->for($this->school)->create(['group_id' => $this->groep->id]);

        $this->actingAs($this->trainer)->post("/trainings/{$training->id}/afzeggen", ['reason' => 'Ondergelopen.']);
        $this->actingAs($this->trainer)->delete("/trainings/{$training->id}/afzeggen");

        $this->assertNull($training->refresh()->cancelled_at);
        Notification::assertSentToTimes($ouder, NieuweMededeling::class, 1);
    }

    public function test_een_ouder_mag_geen_training_afzeggen(): void
    {
        $ouder = $this->gebruiker(Role::Ouder);
        $training = Training::factory()->for($this->school)->create(['group_id' => $this->groep->id]);

        $this->actingAs($ouder)
            ->post("/trainings/{$training->id}/afzeggen", ['reason' => 'Geen zin.'])
            ->assertForbidden();
    }

    public function test_het_menu_toont_mededelingen_aan_staf_maar_niet_aan_ouders(): void
    {
        $ouder = $this->gebruiker(Role::Ouder);
        Player::factory()->for($this->school)->create()->guardians()->attach($ouder->id);

        $this->actingAs($this->trainer)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('nav', fn ($nav) => collect($nav)->contains('href', '/announcements')));

        $this->actingAs($ouder)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('nav', fn ($nav) => ! collect($nav)->contains('href', '/announcements')));
    }
}
