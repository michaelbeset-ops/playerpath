<?php

namespace Tests\Feature\Onboarding;

use App\Actions\Onboarding\RemoveDemoData;
use App\Actions\Onboarding\SeedDemoData;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Group;
use App\Models\Location;
use App\Models\Player;
use App\Models\Product;
use App\Models\Report;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Onboarding\OnboardingState;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Voorbeelddata: een nieuwe school begint niet met een leeg scherm.
 *
 * Wat hier echt toe doet is het paar: hij staat er meteen, en hij gaat er met
 * één knop weer helemaal uit. Zonder dat tweede is voorbeelddata een verzonnen
 * kind dat over een half jaar nog in een echt ledenbestand staat.
 */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);
    }

    protected function zet(): void
    {
        app(SeedDemoData::class)->handle($this->school->refresh(), $this->eigenaar);
        app(Tenancy::class)->set($this->school);
    }

    public function test_een_nieuwe_school_ziet_meteen_iets_dat_werkt(): void
    {
        $this->zet();

        $this->assertSame(4, Player::demo()->count());
        $this->assertSame(2, Training::demo()->count());
        $this->assertSame(1, Group::demo()->count());
        $this->assertSame(1, Product::demo()->count());
        $this->assertSame(1, Announcement::demo()->count());
        $this->assertSame(1, Location::demo()->count());

        // Drie rapporten per speler: dan staat de kaart vol en is er groei.
        $this->assertSame(12, Report::demo()->count());
    }

    /** Een kaart zonder cijfer laat niet zien wat het product doet. */
    public function test_de_spelerskaarten_zijn_doorgerekend(): void
    {
        $this->zet();

        foreach (Player::demo()->get() as $speler) {
            $this->assertNotNull($speler->overall_rating, $speler->full_name.' heeft geen kaartcijfer');
            $this->assertNotEmpty($speler->category_ratings);
        }
    }

    public function test_twee_keer_neerzetten_levert_geen_acht_spelers_op(): void
    {
        $this->zet();
        $this->zet();

        $this->assertSame(4, Player::demo()->count());
    }

    public function test_alles_gaat_er_in_een_keer_weer_uit(): void
    {
        $this->zet();

        // En iets van de school zelf, dat moet blijven staan.
        $echt = Player::factory()->for($this->school)->create();

        $geteld = app(RemoveDemoData::class)->handle($this->school);
        app(Tenancy::class)->set($this->school);

        $this->assertSame(4, $geteld['players']);
        $this->assertSame(0, Player::demo()->count());
        $this->assertSame(0, Report::demo()->count());
        $this->assertSame(0, Training::demo()->count());
        $this->assertSame(0, Group::demo()->count());
        $this->assertSame(0, Product::demo()->count());
        $this->assertSame(0, Announcement::demo()->count());
        $this->assertSame(0, Location::demo()->count());

        // Wat de school zelf toevoegde blijft staan.
        $this->assertTrue(Player::whereKey($echt->id)->exists());
    }

    public function test_de_eigenaar_ruimt_op_via_het_scherm(): void
    {
        $this->zet();

        $this->actingAs($this->eigenaar->fresh())
            ->delete('/onboarding/voorbeelddata')
            ->assertRedirect('/dashboard');

        app(Tenancy::class)->set($this->school);

        $this->assertSame(0, Player::demo()->count());
        $this->assertFalse(OnboardingState::for($this->school->refresh())->hasDemoData());
    }

    public function test_een_trainer_ruimt_de_voorbeelddata_niet_op(): void
    {
        $this->zet();

        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->delete('/onboarding/voorbeelddata')->assertForbidden();

        app(Tenancy::class)->set($this->school);
        $this->assertSame(4, Player::demo()->count());
    }

    /**
     * De belangrijkste regel van alles: "voeg je eerste speler toe" gaat over
     * jóuw eerste speler. Zou het voorbeeld meetellen, dan is de school af
     * zonder dat er iets is gebeurd.
     */
    public function test_de_startlijst_telt_voorbeelddata_niet_mee(): void
    {
        $this->zet();
        OnboardingState::mark($this->school->fresh(), 'tour_seen_at');

        $this->actingAs($this->eigenaar->fresh())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('checklist.steps', function ($stappen) {
                $perSleutel = collect($stappen)->keyBy('key');

                $this->assertFalse($perSleutel['player']['done'], 'een voorbeeldspeler telde mee');
                $this->assertFalse($perSleutel['location']['done'], 'een voorbeeldlocatie telde mee');
                $this->assertFalse($perSleutel['group']['done'], 'een voorbeeldgroep telde mee');
                $this->assertFalse($perSleutel['product']['done']);
                $this->assertFalse($perSleutel['training']['done']);
                $this->assertFalse($perSleutel['report']['done']);

                return true;
            }));
    }

    public function test_de_balk_boven_de_voorbeelddata_staat_er_voor_de_eigenaar(): void
    {
        $this->zet();

        $this->actingAs($this->eigenaar->fresh())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('onboarding.demo', true));

        // En verdwijnt zodra hij is opgeruimd.
        app(RemoveDemoData::class)->handle($this->school);
        app(RemoveDemoData::class)->finish($this->school);

        $this->actingAs($this->eigenaar->fresh())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('onboarding.demo', false));
    }
}
