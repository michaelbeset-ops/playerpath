<?php

namespace Tests\Feature\Communication;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Notifications\NieuweMededeling;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $ouder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        Player::factory()->for($this->school)->create()->guardians()->attach($this->ouder->id);
    }

    public function test_standaard_wil_iedereen_alles(): void
    {
        $this->assertTrue($this->ouder->wantsEmail('rapport'));
        $this->assertTrue($this->ouder->wantsEmail('mededeling'));

        // Ook een soort die nog niet bestond toen de voorkeur werd opgeslagen.
        $this->ouder->forceFill(['notification_preferences' => ['rapport' => false]])->save();
        $this->assertTrue($this->ouder->refresh()->wantsEmail('mededeling'));
    }

    public function test_een_ouder_zet_mail_uit_maar_houdt_de_melding_in_de_app(): void
    {
        $this->actingAs($this->ouder)
            ->patch('/settings/notifications', [
                'preferences' => ['rapport' => true, 'doel' => true, 'mededeling' => false, 'verjaardag' => true, 'samenvatting' => true, 'betaling' => true],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->ouder->refresh()->wantsEmail('mededeling'));

        // De melding komt nog steeds binnen, alleen niet per mail.
        $kanalen = (new NieuweMededeling(new Announcement))->via($this->ouder);

        $this->assertSame(['database'], $kanalen);
    }

    public function test_opslaan_behoudt_de_hoofdschakelaar_voor_mail(): void
    {
        $this->ouder->forceFill(['notification_preferences' => ['mail' => false]])->save();

        $this->actingAs($this->ouder)
            ->patch('/settings/notifications', [
                'preferences' => ['rapport' => true, 'doel' => true, 'mededeling' => true, 'verjaardag' => true, 'samenvatting' => true, 'betaling' => true],
            ])
            ->assertSessionHasNoErrors();

        $voorkeuren = $this->ouder->refresh()->notification_preferences;
        $this->assertFalse($voorkeuren['mail']);
        $this->assertTrue($voorkeuren['mededeling']);
        $this->assertFalse($this->ouder->wantsEmail('mededeling'));
    }

    public function test_een_kind_account_wordt_van_de_instellingen_weggestuurd(): void
    {
        $this->ouder->forceFill(['email' => 'speler-1-abcdefgh@'.\App\Actions\Players\EnsurePlayerAccount::DOMEIN])->save();

        foreach (['/settings/profile', '/settings/password', '/settings/notifications'] as $adres) {
            $this->actingAs($this->ouder)->get($adres)->assertRedirect('/dashboard');
        }

        $this->actingAs($this->ouder)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertNotNull($this->ouder->fresh());
    }

    public function test_met_mail_aan_gaan_beide_kanalen_mee(): void
    {
        $kanalen = (new NieuweMededeling(new Announcement))->via($this->ouder);

        $this->assertSame(['mail', 'database'], $kanalen);
    }

    public function test_uitgezette_mail_bereikt_de_ouder_niet_bij_een_echte_mededeling(): void
    {
        Notification::fake();

        $this->ouder->forceFill(['notification_preferences' => ['mededeling' => false]])->save();

        $this->actingAs($this->eigenaar)->post('/announcements', ['title' => 'Nieuws', 'body' => 'Tekst.']);

        Notification::assertSentTo(
            $this->ouder,
            NieuweMededeling::class,
            fn (NieuweMededeling $melding, array $kanalen) => $kanalen === ['database'],
        );
    }

    public function test_het_voorkeurenscherm_toont_de_huidige_stand(): void
    {
        $this->ouder->forceFill(['notification_preferences' => ['betaling' => false]])->save();

        $this->actingAs($this->ouder)
            ->get('/settings/notifications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('kinds', fn ($soorten) => collect($soorten)->firstWhere('key', 'betaling')['enabled'] === false)
                ->where('kinds', fn ($soorten) => collect($soorten)->firstWhere('key', 'rapport')['enabled'] === true)
            );
    }

    public function test_een_onbekende_voorkeur_wordt_geweigerd(): void
    {
        $this->actingAs($this->ouder)
            ->patch('/settings/notifications', ['preferences' => ['rapport' => true]])
            ->assertSessionHasErrors('preferences.doel');
    }
}
