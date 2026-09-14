<?php

namespace Tests\Feature\Progress;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Player;
use App\Models\Report;
use App\Models\School;
use App\Models\User;
use App\Notifications\NieuwRapport;
use App\Support\PlayerCard\PlayerBadges;
use App\Support\PlayerCard\PlayerProgress;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProgressAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        app(Tenancy::class)->set($this->school);

        $this->speler = Player::factory()->for($this->school)->keeper()->create();
    }

    /** @return array<string, int> */
    protected function cijfers(int $score): array
    {
        return collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => $score])
            ->all();
    }

    protected function ouderVan(Player $speler): User
    {
        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $speler->guardians()->attach($ouder->id, ['relationship' => 'moeder']);

        return $ouder;
    }

    protected function rapporteer(int $score): void
    {
        $this->actingAs($this->trainer)->post("/players/{$this->speler->id}/reports", ['scores' => $this->cijfers($score)]);
    }

    // --- Voortgang ---

    public function test_de_voortgang_toont_een_punt_per_rapport(): void
    {
        $this->rapporteer(6);
        $this->rapporteer(8);

        $voortgang = app(PlayerProgress::class)->for($this->speler->refresh());

        $this->assertCount(2, $voortgang['points']);
        // Het cijfer van het rapport zelf, niet het afgevlakte kaartgemiddelde.
        $this->assertSame([60, 80], $voortgang['overall']['series']);
        $this->assertSame(20, $voortgang['overall']['delta']);
        $this->assertTrue($voortgang['hasEnoughData']);
    }

    public function test_met_een_rapport_is_er_nog_geen_grafiek(): void
    {
        $this->rapporteer(7);

        $voortgang = app(PlayerProgress::class)->for($this->speler->refresh());

        $this->assertFalse($voortgang['hasEnoughData']);
        $this->assertNull($voortgang['overall']['delta']);
    }

    public function test_de_voortgangspagina_is_bereikbaar_voor_de_ouder(): void
    {
        $ouder = $this->ouderVan($this->speler);
        $this->rapporteer(7);

        $this->actingAs($ouder)
            ->get("/players/{$this->speler->id}/progress")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('players/Progress'));
    }

    public function test_een_ouder_komt_niet_bij_de_voortgang_van_een_ander_kind(): void
    {
        $ouder = $this->ouderVan($this->speler);
        $anderKind = Player::factory()->for($this->school)->create();

        $this->actingAs($ouder)->get("/players/{$anderKind->id}/progress")->assertForbidden();
    }

    public function test_de_spelersdetailpagina_is_niet_voor_ouders(): void
    {
        $ouder = $this->ouderVan($this->speler);

        // Die pagina toont de contactgegevens van alle ouders van dit kind.
        $this->actingAs($ouder)->get("/players/{$this->speler->id}")->assertForbidden();
    }

    // --- Meldingen ---

    public function test_een_ouder_krijgt_een_melding_bij_een_nieuw_rapport(): void
    {
        Notification::fake();

        $ouder = $this->ouderVan($this->speler);

        $this->rapporteer(8);

        Notification::assertSentTo($ouder, NieuwRapport::class, function (NieuwRapport $melding) {
            return $melding->overallRating === 80;
        });
    }

    public function test_de_speler_zelf_krijgt_de_melding_ook(): void
    {
        Notification::fake();

        $spelerUser = User::factory()->for($this->school)->create();
        $spelerUser->assignRole(Role::Speler->value);
        $this->speler->update(['user_id' => $spelerUser->id]);

        $this->rapporteer(8);

        Notification::assertSentTo($spelerUser, NieuwRapport::class);
    }

    public function test_de_trainer_krijgt_geen_melding_van_zijn_eigen_rapport(): void
    {
        Notification::fake();

        $this->ouderVan($this->speler);
        $this->rapporteer(8);

        Notification::assertNotSentTo($this->trainer, NieuwRapport::class);
    }

    public function test_de_melding_gaat_via_de_queue(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new NieuwRapport(new Report, $this->speler, 80, 5),
            'Het opslaan van een rapport mag nooit wachten op een mailserver.'
        );
    }

    public function test_de_melding_komt_in_de_app_terecht(): void
    {
        $ouder = $this->ouderVan($this->speler);

        // Een school die in kleuren beoordeelt.
        $this->school->forceFill(['rating_settings' => ['grading' => 'kleuren']])->save();

        $this->rapporteer(8);

        $this->assertSame(1, $ouder->unreadNotifications()->count());

        // Openen is lezen: de melding staat er nog als "nieuw" bij, maar het
        // belletje bovenin telt vanaf nu nul.
        $this->actingAs($ouder)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('notifications/Index')
                ->count('notifications', 1)
                // In kleuren geen getal in de melding, wel de kleur.
                ->where('notifications.0.overall_rating', null)
                ->where('notifications.0.grade', 'Goed')
                ->where('notifications.0.read', false)
                ->where('unreadNotifications', 0)
            );

        $this->assertSame(0, $ouder->refresh()->unreadNotifications()->count());

        // De tweede keer is hij gewoon gelezen.
        $this->actingAs($ouder)
            ->get('/notifications')
            ->assertInertia(fn ($page) => $page->where('notifications.0.read', true));
    }

    public function test_meldingen_zijn_als_gelezen_te_markeren(): void
    {
        $ouder = $this->ouderVan($this->speler);
        $this->rapporteer(8);

        $this->actingAs($ouder)->post('/notifications/read')->assertRedirect();

        $this->assertSame(0, $ouder->refresh()->unreadNotifications()->count());
    }

    public function test_je_ziet_alleen_je_eigen_meldingen(): void
    {
        $ouder = $this->ouderVan($this->speler);
        $andereOuder = User::factory()->for($this->school)->create();
        $andereOuder->assignRole(Role::Ouder->value);

        $this->rapporteer(8);

        $this->actingAs($andereOuder)
            ->get('/notifications')
            ->assertInertia(fn ($page) => $page->count('notifications', 0));
    }

    // --- Kaart: niveau en mijlpalen ---

    public function test_het_niveau_hoort_bij_de_xp(): void
    {
        // Sinds de rekenkern: het level beloont inzet (XP), de rating zegt hoe
        // goed. Zie Support\Rating\RatingEngine.
        $badges = app(PlayerBadges::class);

        $this->assertSame('brons', $badges->level($this->speler)['key']);

        $this->speler->forceFill(['xp' => 251])->save();
        $this->assertSame('zilver', $badges->level($this->speler->fresh())['key']);

        $this->speler->forceFill(['xp' => 501])->save();
        $this->assertSame('goud', $badges->level($this->speler->fresh())['key']);

        $this->speler->forceFill(['xp' => 751])->save();
        $this->assertSame('elite', $badges->level($this->speler->fresh())['key']);
    }

    public function test_mijlpalen_worden_verdiend_door_te_groeien(): void
    {
        $this->rapporteer(5);
        $this->rapporteer(7);

        $badges = collect(app(PlayerBadges::class)->for($this->speler->refresh(), app(PlayerProgress::class)))
            ->keyBy('key');

        $this->assertTrue($badges['eerste_rapport']['earned']);
        $this->assertTrue($badges['groei']['earned'], 'Van 50 naar 70 is 20 punten groei.');
        // Standaard gelden er vier; "vijf rapporten" hoort daar niet bij.
        $this->assertArrayNotHasKey('vijf_rapporten', $badges->all());
        $this->assertFalse($badges['aanwezig_vijf']['earned']);
    }

    public function test_de_kaart_toont_niveau_en_mijlpalen(): void
    {
        $this->rapporteer(8);

        $this->actingAs($this->trainer)
            ->get("/players/{$this->speler->id}/card")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Een rapport is 5 XP: brons, met zilver in zicht.
                ->where('card.level.key', 'brons')
                ->where('card.level.next.key', 'zilver')
                // Standaard vier mijlpalen; zie BadgeSettings.
                ->has('badges', 4)
            );
    }
}
