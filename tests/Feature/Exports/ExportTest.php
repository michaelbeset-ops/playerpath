<?php

namespace Tests\Feature\Exports;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Support\Exports\AttendanceExport;
use App\Support\Exports\ExportRegistry;
use App\Support\Exports\PlayersExport;
use App\Support\Exports\TrainingsExport;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->school = School::factory()->create();
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);
    }

    public function test_de_pagina_toont_de_beschikbare_overzichten(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/exports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('exports/Index')
                ->count('exports', 3)
                ->where('exports.0.key', 'players')
                ->where('exports.1.key', 'trainings')
                ->where('exports.2.key', 'attendance')
            );
    }

    public function test_alleen_de_eigenaar_mag_exporteren(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $this->actingAs($trainer)->get('/exports')->assertForbidden();
        $this->actingAs($trainer)->get('/exports/players')->assertForbidden();
    }

    public function test_een_onbekend_overzicht_geeft_404(): void
    {
        $this->actingAs($this->eigenaar)->get('/exports/bestaat-niet')->assertNotFound();
    }

    public function test_de_spelersexport_bevat_alleen_de_eigen_school(): void
    {
        Player::factory()->for($this->school)->create(['first_name' => 'Sem', 'last_name' => 'de Vries']);

        $andereSchool = School::factory()->create();
        Player::factory()->for($andereSchool)->create(['first_name' => 'Vreemde', 'last_name' => 'Speler']);

        $rijen = iterator_to_array(app(PlayersExport::class)->rows([]));

        $this->assertCount(1, $rijen);
        $this->assertSame('Sem', $rijen[0][0]);
        $this->assertSame('de Vries', $rijen[0][1]);
    }

    public function test_de_csv_download_gebruikt_puntkomma_en_een_bom(): void
    {
        Player::factory()->for($this->school)->create(['first_name' => 'Sem', 'last_name' => 'de Vries']);

        $response = $this->actingAs($this->eigenaar)->get('/exports/players?format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('spelers-', $response->headers->get('content-disposition'));

        $inhoud = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $inhoud, 'Excel heeft een BOM nodig om UTF-8 te herkennen.');
        $this->assertStringContainsString('Voornaam;Achternaam;', $inhoud);
        // Waarden met een spatie krijgen aanhalingstekens; dat is gewoon geldige CSV.
        $this->assertStringContainsString('Sem;"de Vries";', $inhoud);
    }

    public function test_de_excel_download_levert_een_xlsx(): void
    {
        Player::factory()->for($this->school)->create();

        $response = $this->actingAs($this->eigenaar)->get('/exports/players?format=xlsx');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Een xlsx is een zip: begint met "PK".
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_de_trainingenexport_respecteert_de_periode(): void
    {
        $groep = Group::factory()->for($this->school)->create();

        foreach (['2026-09-10', '2026-09-20', '2026-10-05'] as $dag) {
            $start = now()->parse($dag)->setTime(18, 0);
            Training::factory()->for($this->school)->for($groep)->create([
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes(90),
            ]);
        }

        $rijen = iterator_to_array(app(TrainingsExport::class)->rows(['from' => '2026-09-01', 'to' => '2026-09-30']));

        $this->assertCount(2, $rijen);
        $this->assertSame('10-09-2026', $rijen[0][0]);
    }

    public function test_de_trainingenexport_telt_de_opkomst(): void
    {
        $groep = Group::factory()->for($this->school)->create();
        $training = Training::factory()->for($this->school)->for($groep)->past()->create();

        $spelers = Player::factory()->count(3)->for($this->school)->create();
        $spelers->each(fn (Player $s) => $s->groups()->attach($groep->id));

        Attendance::factory()->for($this->school)->create(['training_id' => $training->id, 'player_id' => $spelers[0]->id, 'status' => 'present']);
        Attendance::factory()->for($this->school)->create(['training_id' => $training->id, 'player_id' => $spelers[1]->id, 'status' => 'absent']);

        $rij = iterator_to_array(app(TrainingsExport::class)->rows([]))[0];

        // Verwacht 3, aanwezig 1, afwezig 1, niet afgevinkt 1.
        $this->assertSame([3, 1, 1, 1], array_slice($rij, 6, 4));
    }

    public function test_de_aanwezigheidsexport_geeft_een_regel_per_speler_per_training(): void
    {
        $groep = Group::factory()->for($this->school)->create(['name' => 'Keepers']);
        $training = Training::factory()->for($this->school)->for($groep)->past()->create();
        $speler = Player::factory()->for($this->school)->keeper()->create(['first_name' => 'Sem', 'last_name' => 'de Vries']);

        Attendance::factory()->for($this->school)->create([
            'training_id' => $training->id,
            'player_id' => $speler->id,
            'registration' => 'attending',
            'status' => 'present',
        ]);

        $rijen = iterator_to_array(app(AttendanceExport::class)->rows([]));

        $this->assertCount(1, $rijen);
        $this->assertSame('Keepers', $rijen[0][2]);
        $this->assertSame('Sem de Vries', $rijen[0][3]);
        $this->assertSame('Aangemeld', $rijen[0][5]);
        $this->assertSame('Aanwezig', $rijen[0][6]);
    }

    public function test_een_verkeerde_periode_wordt_geweigerd(): void
    {
        $this->actingAs($this->eigenaar)
            ->get('/exports/trainings?from=2026-09-30&to=2026-09-01')
            ->assertSessionHasErrors('to');
    }

    public function test_het_register_is_uitbreidbaar(): void
    {
        $registry = app(ExportRegistry::class);

        $this->assertTrue($registry->has('players'));
        $this->assertFalse($registry->has('payments'), 'Het betalingsoverzicht komt pas als Mollie is aangesloten.');
        $this->assertSame('Trainingen', $registry->find('trainings')->title());
    }
}
