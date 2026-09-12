<?php

namespace Tests\Feature\Players;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wat de kaart leuker maakt: pijltjes per categorie sinds het vorige
 * rapport, en een achterkant met de laatste rapporten en het doel.
 *
 * De publieke kaart krijgt de achterkant zonder trainer, toelichting en
 * doel: dat is wat een trainer over een kind opschreef, niet voor internet.
 */
class CardExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $trainer;

    protected Player $keeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->trainer = User::factory()->for($this->school)->create(['name' => 'Rob']);
        $this->trainer->assignRole(Role::Trainer->value);

        $this->keeper = Player::factory()->for($this->school)->keeper()->create();
    }

    protected function rapporteer(float $cijfer, string $noot = ''): void
    {
        $scores = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => $cijfer])
            ->all();

        $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/reports", ['scores' => $scores, 'note' => $noot])
            ->assertSessionHasNoErrors();
    }

    public function test_de_kaart_toont_per_categorie_wat_het_laatste_rapport_veranderde(): void
    {
        // Eén rapport: er is niets om mee te vergelijken, dus geen pijltje.
        $this->rapporteer(6);

        $this->actingAs($this->trainer)
            ->get("/players/{$this->keeper->id}/card")
            ->assertInertia(fn ($page) => $page->where('card.categories.0.delta', null));

        // Tweede rapport een 8: de kaart is het gemiddelde (70), de vorige stand 60.
        $this->rapporteer(8, 'Sterk in de lucht.');

        $this->actingAs($this->trainer)
            ->get("/players/{$this->keeper->id}/card")
            ->assertInertia(fn ($page) => $page
                ->where('card.categories.0.rating', 70)
                ->where('card.categories.0.delta', 10)
                ->count('card.recent_reports', 2)
                ->where('card.recent_reports.0.overall', 80)
                ->where('card.recent_reports.0.trainer', 'Rob')
                ->where('card.recent_reports.0.note', 'Sterk in de lucht.')
                ->where('card.goal', null)
            );
    }

    public function test_de_publieke_kaart_heeft_een_achterkant_zonder_trainer_en_toelichting(): void
    {
        $this->rapporteer(7, 'Privé-opmerking van de trainer.');

        $eigenaar = User::factory()->for($this->school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);
        $this->actingAs($eigenaar)->post("/players/{$this->keeper->id}/share");

        $this->get('/kaart/'.$this->keeper->fresh()->share_token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->count('card.recent_reports', 1)
                ->where('card.recent_reports.0.overall', 70)
                ->where('card.recent_reports.0.trainer', null)
                ->where('card.recent_reports.0.note', null)
                ->where('card.goal', null)
            );
    }

    public function test_het_lopende_doel_staat_op_de_achterkant(): void
    {
        $this->rapporteer(6);

        $this->actingAs($this->trainer)
            ->post("/players/{$this->keeper->id}/goals", ['category' => 'reflexen', 'target' => '7,5', 'due_on' => now()->addMonths(2)->toDateString()])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->trainer)
            ->get("/players/{$this->keeper->id}/card")
            ->assertInertia(fn ($page) => $page
                ->where('card.goal.label', 'Reflexen')
                ->where('card.goal.target_grade', '7,5')
                ->where('card.goal.current_grade', '6,0')
                ->where('card.goal.track_label', 'Op koers')
            );
    }
}
