<?php

namespace Tests\Feature\Rating;

use App\Actions\Seasons\CloseSeason;
use App\Enums\Role;
use App\Models\Player;
use App\Models\PlayerCardSeason;
use App\Models\School;
use App\Models\User;
use App\Notifications\SeizoenAfgesloten;
use App\Support\Rating\RatingEngine;
use App\Support\Rating\SchoolSeason;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Seizoenen: de punten en het level horen bij een seizoen, de cijfers niet.
 */
class SeasonTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Player $keeper;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->keeper = Player::factory()->for($this->school)->keeper()->create();
    }

    public function test_de_eigenaar_stelt_een_seizoen_in_met_een_duur_in_weken(): void
    {
        $this->actingAs($this->eigenaar)
            ->post('/seizoen', ['name' => 'Najaar 2026', 'starts_on' => '2026-09-01', 'weeks' => 12, 'ends_on' => '2026-11-23'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $seizoen = SchoolSeason::for($this->school->fresh());

        $this->assertTrue($seizoen->isSet());
        $this->assertSame('Najaar 2026', $seizoen->name);
        $this->assertSame(12, $seizoen->totalWeeks());
        $this->assertSame('2026-09-01', $seizoen->xpFrom?->toDateString());

        // De kaart draagt de naam van het seizoen.
        $this->actingAs($this->eigenaar)
            ->get("/players/{$this->keeper->id}/card")
            ->assertInertia(fn ($page) => $page->where('card.season_label', 'Najaar 2026')->where('card.season_ends', '23-11-2026'));

        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);
        $this->actingAs($trainer)->get('/seizoen')->assertForbidden();
    }

    public function test_alleen_punten_uit_het_seizoen_tellen_mee(): void
    {
        $engine = app(RatingEngine::class);
        $engine->award($this->keeper, 'attendance', 100, 'Vorig seizoen', null, now()->subMonths(4));
        $engine->award($this->keeper, 'attendance', 30, 'Dit seizoen', null, now()->subDay());

        $this->assertSame(130, $this->keeper->fresh()->xp);

        SchoolSeason::save($this->school, ['name' => 'Najaar', 'starts_on' => now()->subWeek()->toDateString(), 'ends_on' => now()->addWeeks(10)->toDateString(), 'weeks' => 11, 'xp_from' => now()->subWeek()->toDateString()]);
        app(Tenancy::class)->set($this->school->fresh());

        $engine->recalculate($this->keeper->fresh());

        $this->assertSame(30, $this->keeper->fresh()->xp);
    }

    public function test_afsluiten_bewaart_de_eindkaart_en_laat_de_punten_opnieuw_beginnen(): void
    {
        $engine = app(RatingEngine::class);
        $engine->award($this->keeper, 'attendance', 260, 'Trouw gekomen', null, now()->subDay());
        $this->keeper->forceFill(['overall_rating' => 74, 'category_ratings' => ['reflexen' => 74.0]])->save();

        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $this->keeper->guardians()->attach($ouder->id, ['relationship' => 'vader']);

        SchoolSeason::save($this->school, ['name' => 'Najaar 2026', 'starts_on' => now()->subWeeks(12)->toDateString(), 'ends_on' => now()->subDay()->toDateString(), 'weeks' => 12, 'xp_from' => now()->subWeeks(12)->toDateString()]);
        $school = $this->school->fresh();

        $this->assertTrue(SchoolSeason::for($school)->hasEnded());

        $this->artisan('seasons:close')->assertSuccessful();

        $kaart = PlayerCardSeason::where('player_id', $this->keeper->id)->firstOrFail();
        $this->assertSame('Najaar 2026', $kaart->season);
        $this->assertSame(74, $kaart->overall_rating);
        $this->assertSame(260, $kaart->xp);
        $this->assertSame('zilver', $kaart->level);

        // De punten beginnen opnieuw; de rating blijft.
        $this->assertSame(0, $this->keeper->fresh()->xp);
        $this->assertSame(74, $this->keeper->fresh()->overall_rating);
        $this->assertFalse(SchoolSeason::for($school->fresh())->isSet());

        Notification::assertSentTo($ouder, SeizoenAfgesloten::class);

        // Nog eens draaien doet niets meer.
        $this->assertSame(0, app(CloseSeason::class)->handle($school->fresh()));
        $this->assertSame(1, PlayerCardSeason::count());
    }

    public function test_de_levels_liggen_op_brons_zilver_goud_en_special(): void
    {
        $engine = app(RatingEngine::class);
        $settings = $engine->settingsFor($this->keeper);

        $this->assertSame('brons', $engine->level(250, null, $settings)['key']);
        $this->assertSame('zilver', $engine->level(251, null, $settings)['key']);
        $this->assertSame('goud', $engine->level(501, null, $settings)['key']);
        $this->assertSame('elite', $engine->level(751, null, $settings)['key']);
        $this->assertSame('Special', $engine->level(751, null, $settings)['label']);
        $this->assertSame(35, $engine->nextLevel(466, $settings)['remaining']);
    }
}
