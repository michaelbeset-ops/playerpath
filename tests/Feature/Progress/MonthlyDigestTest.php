<?php

namespace Tests\Feature\Progress;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Notifications\MaandelijkseUpdate;
use App\Support\Progress\MonthlyDigest;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MonthlyDigestTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected User $ouder;

    protected Player $keeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        $this->keeper = Player::factory()->for($this->school)->keeper()->create();
        $this->keeper->guardians()->attach($this->ouder->id);
    }

    protected function rapporteer(int $score): void
    {
        $cijfers = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => $score])
            ->all();

        $this->actingAs($this->trainer)->post("/players/{$this->keeper->id}/reports", ['scores' => $cijfers]);
    }

    public function test_zonder_iets_gebeurd_is_er_geen_samenvatting(): void
    {
        $this->assertNull(app(MonthlyDigest::class)->for($this->keeper));
    }

    public function test_een_speler_met_rapporten_krijgt_wel_een_samenvatting(): void
    {
        $this->rapporteer(6);
        $this->rapporteer(8);

        $digest = app(MonthlyDigest::class)->for($this->keeper->refresh());

        $this->assertNotNull($digest);
        $this->assertSame(2, $digest['reports']);
        $this->assertSame(20, $digest['delta']);
        $this->assertNotNull($digest['highlight']);
        $this->assertSame(80, $digest['highlight']['now']);
    }

    public function test_bij_een_enkel_rapport_is_er_geen_groei_te_melden(): void
    {
        $this->rapporteer(7);

        $digest = app(MonthlyDigest::class)->for($this->keeper->refresh());

        // Uit één meting valt geen ontwikkeling af te lezen; dat verzinnen we niet.
        $this->assertNull($digest['delta']);
        $this->assertNull($digest['highlight']);
        $this->assertSame(1, $digest['reports']);
    }

    public function test_het_commando_bereikt_ouder_en_speler_maar_slaat_lege_spelers_over(): void
    {
        Notification::fake();

        $spelerAccount = User::factory()->for($this->school)->create();
        $spelerAccount->assignRole(Role::Speler->value);
        $this->keeper->update(['user_id' => $spelerAccount->id]);

        $this->rapporteer(6);
        $this->rapporteer(7);

        // Een tweede speler zonder enige activiteit.
        $stil = Player::factory()->for($this->school)->create();
        $stilleOuder = User::factory()->for($this->school)->create();
        $stilleOuder->assignRole(Role::Ouder->value);
        $stil->guardians()->attach($stilleOuder->id);

        $this->artisan('players:digest')->assertSuccessful();

        Notification::assertSentTo($this->ouder, MaandelijkseUpdate::class);
        Notification::assertSentTo($spelerAccount, MaandelijkseUpdate::class);
        Notification::assertNotSentTo($stilleOuder, MaandelijkseUpdate::class);
    }

    public function test_een_proefdraai_verstuurt_niets(): void
    {
        Notification::fake();

        $this->rapporteer(6);

        $this->artisan('players:digest', ['--dry-run' => true])->assertSuccessful();

        // Het rapport zelf stuurt wel een melding; het gaat erom dat de
        // proefdraai geen samenvatting verstuurt.
        Notification::assertNotSentTo($this->ouder, MaandelijkseUpdate::class);
    }

    public function test_de_ouder_kan_deze_mail_uitzetten_maar_houdt_de_melding(): void
    {
        $this->ouder->forceFill(['notification_preferences' => ['samenvatting' => false]])->save();

        $kanalen = (new MaandelijkseUpdate($this->keeper, ['period' => 'x', 'reports' => 1, 'attended' => 0, 'highlight' => null, 'delta' => null, 'goals' => [], 'nextTraining' => null]))
            ->via($this->ouder);

        $this->assertSame(['database'], $kanalen);
    }

    public function test_scholen_blijven_gescheiden(): void
    {
        Notification::fake();

        $andere = School::factory()->create();
        $andereOuder = User::factory()->for($andere)->create();
        $andereOuder->assignRole(Role::Ouder->value);

        app(Tenancy::class)->forSchool($andere, function () use ($andere, $andereOuder) {
            $speler = Player::factory()->for($andere)->create();
            $speler->guardians()->attach($andereOuder->id);
        });

        $this->rapporteer(6);

        $this->artisan('players:digest');

        Notification::assertSentTo($this->ouder, MaandelijkseUpdate::class);
        Notification::assertNotSentTo($andereOuder, MaandelijkseUpdate::class);
    }
}
