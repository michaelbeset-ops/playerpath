<?php

namespace Tests\Feature\Onboarding;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * Een ledenlijst uit Excel: keepers, trainingsdagen als groep, trainers
 * zonder inlog. Nog eens draaien mag niets dubbel maken.
 */
class ImportPlayersTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected string $bestand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo('2026-09-17 10:00:00');

        $this->school = School::factory()->create(['slug' => 'keepersschool-test']);

        $this->bestand = tempnam(sys_get_temp_dir(), 'import').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($this->bestand);
        $writer->addRow(Row::fromValues(['Keepers', 'Team', 'Dag', '', 'Trainers']));
        $writer->addRow(Row::fromValues(['Abe Schotanus', 'O12-1', 'Vrijdag 1', '', 'Aad Kattevilder']));
        $writer->addRow(Row::fromValues(['Chelsea  Hep', 'MO14-1', 'zondag  2', '', 'Jim van Rijn']));
        $writer->addRow(Row::fromValues(['Daley van der Hoeven', '', 'Zondag 2', '', '']));
        $writer->addRow(Row::fromValues(['', '', '', '', 'Tom Ebens']));
        $writer->close();
    }

    protected function tearDown(): void
    {
        @unlink($this->bestand);

        parent::tearDown();
    }

    public function test_spelers_groepen_en_trainers_komen_erin(): void
    {
        Notification::fake();

        $this->artisan('playerpath:importeer-spelers', ['school' => 'keepersschool-test', 'bestand' => $this->bestand])
            ->expectsOutputToContain('Nieuwe spelers: 3')
            ->expectsOutputToContain('Daley van der Hoeven')
            ->assertSuccessful();

        app(Tenancy::class)->set($this->school);

        $abe = Player::where('first_name', 'Abe')->firstOrFail();
        $this->assertSame('Schotanus', $abe->last_name);
        $this->assertSame('O12', $abe->age_category ?? \App\Support\Rating\AgeCategory::forBirthDate($abe->date_of_birth));
        $this->assertSame('Hep', Player::where('first_name', 'Chelsea')->value('last_name'));

        $zondag = Group::where('name', 'Zondag 2')->firstOrFail();
        $this->assertSame(2, $zondag->players()->count());
        $this->assertSame(1, Group::where('name', 'Vrijdag 1')->firstOrFail()->players()->count());

        $trainers = User::ofCurrentSchool()->role(Role::Trainer->value)->get();
        $this->assertEqualsCanonicalizing(['Aad Kattevilder', 'Jim van Rijn', 'Tom Ebens'], $trainers->pluck('name')->all());
        $this->assertTrue($trainers->every(fn (User $t) => $t->email === null && $t->email_verified_at === null));

        Notification::assertNothingSent();
    }

    public function test_nog_eens_draaien_maakt_niets_dubbel(): void
    {
        $this->artisan('playerpath:importeer-spelers', ['school' => 'keepersschool-test', 'bestand' => $this->bestand])->assertSuccessful();
        $this->artisan('playerpath:importeer-spelers', ['school' => 'keepersschool-test', 'bestand' => $this->bestand])
            ->expectsOutputToContain('Nieuwe spelers: 0 (al aanwezig: 3)')
            ->assertSuccessful();

        app(Tenancy::class)->set($this->school);

        $this->assertSame(3, Player::count());
        $this->assertSame(2, Group::count());
        $this->assertSame(3, User::ofCurrentSchool()->role(Role::Trainer->value)->count());
        $this->assertSame(2, Group::where('name', 'Zondag 2')->firstOrFail()->players()->count());
    }

    public function test_een_proef_slaat_niets_op(): void
    {
        $this->artisan('playerpath:importeer-spelers', ['school' => 'keepersschool-test', 'bestand' => $this->bestand, '--proef' => true])
            ->expectsOutputToContain('PROEF')
            ->assertSuccessful();

        app(Tenancy::class)->set($this->school);

        $this->assertSame(0, Player::count());
        $this->assertSame(0, User::ofCurrentSchool()->count());
    }

    public function test_een_onbekende_school_of_bestand_stopt(): void
    {
        $this->artisan('playerpath:importeer-spelers', ['school' => 'bestaat-niet', 'bestand' => $this->bestand])
            ->expectsOutputToContain('keepersschool-test')
            ->assertFailed();

        $this->artisan('playerpath:importeer-spelers', ['school' => 'keepersschool-test', 'bestand' => '/nergens.xlsx'])
            ->assertFailed();
    }

    public function test_een_account_zonder_adres_krijgt_geen_wachtwoordmail(): void
    {
        $this->artisan('playerpath:importeer-spelers', ['school' => 'keepersschool-test', 'bestand' => $this->bestand])->assertSuccessful();

        $trainer = User::whereNull('email')->firstOrFail();
        $beheerder = User::factory()->create(['school_id' => null]);
        $beheerder->assignRole(Role::Platformbeheerder->value);

        $this->actingAs($beheerder)
            ->post("/beheer/scholen/{$this->school->id}/gebruikers/{$trainer->id}/wachtwoord")
            ->assertSessionHas('error');
    }
}
