<?php

namespace Tests\Feature\Rating;

use App\Enums\PlayerPosition;
use App\Enums\ReportCategory;
use App\Enums\Role;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Notifications\NieuwRapport;
use App\Support\Rating\Grade;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Beoordelen in kleuren (een keuze bij de prestatiekaart): geen getal bij ouders, en onder de
 * motorkap rekent alles gewoon door.
 */
class GradingTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->speler = Player::factory()->for($this->school)->keeper()->create();

        // Deze school beoordeelt in kleuren; de standaard is cijfers.
        $this->school->forceFill(['rating_settings' => ['grading' => 'kleuren']])->save();
    }

    /** @return array<string, mixed> */
    protected function kaartInstellingen(string $grading): array
    {
        return [
            'grading' => $grading,
            'attendance_points' => 10,
            'effort_levels' => [['label' => 'Goed bezig', 'points' => 5], ['label' => 'Hard gewerkt', 'points' => 10], ['label' => 'Uitblinker', 'points' => 15]],
            'attitude_levels' => [['label' => 'Luistert goed', 'points' => 5], ['label' => 'Top houding', 'points' => 10], ['label' => 'Voorbeeld', 'points' => 15]],
            'progress_levels' => [['label' => 'Werkpunt', 'color' => 'rood'], ['label' => 'Op weg', 'color' => 'oranje'], ['label' => 'Goed', 'color' => 'groen'], ['label' => 'Sterk', 'color' => 'blauw']],
        ];
    }

    public function test_cijfers_is_de_standaard_kleuren_een_keuze_en_de_grenzen_kloppen(): void
    {
        $this->assertSame(Grade::CIJFERS, Grade::mode(School::factory()->create()));
        $this->assertSame(Grade::KLEUREN, Grade::mode($this->school->fresh()));

        $this->assertNull(Grade::forRating(null));
        $this->assertSame('rood', Grade::forRating(40)['key']);
        $this->assertSame('oranje', Grade::forRating(55)['key']);
        $this->assertSame('oranje', Grade::forRating(69)['key']);
        $this->assertSame('groen', Grade::forRating(70)['key']);
        $this->assertSame('blauw', Grade::forRating(85)['key']);

        // Elke kleur die de trainer kiest valt op de kaart weer in die kleur.
        foreach (Grade::NIVEAUS as $niveau) {
            $this->assertSame($niveau['key'], Grade::forRating((int) ceil($niveau['score'] * 10))['key']);
        }
    }

    public function test_een_rapport_in_kleuren_levert_een_kaart_in_kleuren_op(): void
    {
        $groen = collect(ReportCategory::forPosition(PlayerPosition::Keeper))
            ->mapWithKeys(fn (ReportCategory $c) => [$c->value => 7.7])
            ->all();

        $this->actingAs($this->eigenaar)->post("/players/{$this->speler->id}/reports", ['scores' => $groen])->assertRedirect();

        $this->actingAs($this->eigenaar)
            ->get("/players/{$this->speler->id}/card")
            ->assertInertia(fn ($page) => $page
                ->where('card.grading', 'kleuren')
                ->where('card.grade.key', 'groen')
                ->where('card.categories.0.grade.label', 'Goed')
                ->where('grading.mode', 'kleuren')
            );
    }

    public function test_de_rapportmail_noemt_in_kleuren_geen_getal(): void
    {
        $rapport = $this->speler->reports()->create(['trainer_id' => $this->eigenaar->id, 'reported_on' => now()]);

        $mail = (new NieuwRapport($rapport, $this->speler, 77, 5))->toMail($this->eigenaar);
        $tekst = implode(' ', $mail->introLines);

        $this->assertStringContainsString('Goed', $tekst);
        $this->assertStringNotContainsString('77', $tekst);

        $data = (new NieuwRapport($rapport, $this->speler, 77, 5))->toArray($this->eigenaar);
        $this->assertNull($data['overall_rating']);
        $this->assertSame('Goed', $data['grade']);
    }

    public function test_de_eigenaar_kiest_cijfers_en_een_trainer_mag_dat_niet(): void
    {
        $this->actingAs($this->eigenaar)->patch('/instellingen/spelerskaart', $this->kaartInstellingen('cijfers'))->assertRedirect()->assertSessionHas('status');

        $this->assertSame(Grade::CIJFERS, Grade::mode($this->school->fresh()));

        $mail = (new NieuwRapport(
            $this->speler->reports()->create(['trainer_id' => $this->eigenaar->id, 'reported_on' => now()]),
            $this->speler->fresh(),
            77,
            null,
        ))->toMail($this->eigenaar);
        $this->assertStringContainsString('77', implode(' ', $mail->introLines));

        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);
        $this->actingAs($trainer)->patch('/instellingen/spelerskaart', $this->kaartInstellingen('kleuren'))->assertForbidden();

        $this->actingAs($this->eigenaar)->patch('/instellingen/spelerskaart', $this->kaartInstellingen('sterren'))->assertSessionHasErrors('grading');
    }
}
