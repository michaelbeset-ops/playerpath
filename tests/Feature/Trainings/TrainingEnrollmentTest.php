<?php

namespace Tests\Feature\Trainings;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\TrainingEnrollmentStatus;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\Training;
use App\Models\User;
use App\Notifications\PlekVrijTraining;
use App\Notifications\TrainingAanmeldingBeoordeeld;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Los inschrijven op een training, naast de groep.
 *
 * De regels staan op de training (leeftijd, positie, limiet, prijs,
 * betaalwijze, goedkeuring) en worden server-side gecontroleerd: het scherm
 * verbergt alleen wat toch niet kan.
 */
class TrainingEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected User $trainer;

    protected User $ouder;

    protected Player $keeper;

    protected Player $veldspeler;

    protected Group $groep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create();
        app(Tenancy::class)->set($this->school);

        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        $this->trainer = User::factory()->for($this->school)->create();
        $this->trainer->assignRole(Role::Trainer->value);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        // Een keeper van tien (O12) en een veldspeler van vijftien (O16).
        $this->keeper = Player::factory()->for($this->school)->keeper()->create([
            'first_name' => 'Sem', 'date_of_birth' => now()->subYears(10)->startOfYear(), 'age_category' => 'O12',
        ]);
        $this->veldspeler = Player::factory()->for($this->school)->veldspeler()->create([
            'first_name' => 'Liam', 'date_of_birth' => now()->subYears(15)->startOfYear(), 'age_category' => 'O16',
        ]);

        foreach ([$this->keeper, $this->veldspeler] as $kind) {
            $this->ouder->children()->attach($kind->id, ['relationship' => 'vader', 'school_id' => $this->school->id]);
        }

        $this->groep = Group::factory()->for($this->school)->create(['name' => 'Keepers ochtend']);
    }

    /** @param  array<string, mixed>  $regels */
    protected function training(array $regels = []): Training
    {
        $start = now()->addDays(3)->setTime(18, 0);

        return Training::factory()->for($this->school)->for($this->groep)->create([
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'open_enrollment' => true,
            'age_categories' => ['O12'],
            'audience' => 'keeper',
            'capacity' => 2,
            'price_cents' => 750,
            'payment_methods' => ['online', 'cash'],
            'requires_approval' => false,
            ...$regels,
        ]);
    }

    public function test_de_eigenaar_plant_een_training_met_toegangsregels(): void
    {
        $this->actingAs($this->eigenaar)->post('/trainings', [
            'group_id' => $this->groep->id,
            'date' => now()->addWeek()->toDateString(),
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'open_enrollment' => true,
            'age_categories' => ['O10', 'O12'],
            'audience' => 'keeper',
            'capacity' => 8,
            'price' => '12,50',
            'payment_methods' => ['cash'],
            'requires_approval' => true,
        ])->assertRedirect('/trainings');

        $training = Training::latest('id')->first();

        $this->assertTrue($training->open_enrollment);
        $this->assertSame(['O10', 'O12'], $training->age_categories);
        $this->assertSame(1250, $training->price_cents);
        $this->assertSame(['cash'], $training->payment_methods);
        $this->assertTrue($training->requires_approval);
        $this->assertSame(8, $training->capacity);
    }

    /** Leeftijd én positie: server-side, niet alleen verborgen. */
    public function test_alleen_een_kind_dat_past_kan_inschrijven(): void
    {
        $training = $this->training();

        // De veldspeler van vijftien past niet: verkeerde leeftijd én positie.
        $this->actingAs($this->ouder)
            ->from('/trainings/'.$training->id.'/inschrijven')
            ->post('/trainings/'.$training->id.'/inschrijven', ['player_id' => $this->veldspeler->id, 'payment_method' => 'cash'])
            ->assertSessionHasErrors('player_id');

        $this->assertSame(0, $training->enrollments()->count());

        // Het scherm zegt hetzelfde.
        $this->actingAs($this->ouder)
            ->get('/trainings/'.$training->id.'/inschrijven')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('children.0.first_name', 'Liam')
                ->where('children.0.eligible', false)
                ->where('children.1.first_name', 'Sem')
                ->where('children.1.eligible', true)
                // Zonder provider bestaat online niet.
                ->count('methods', 1)
                ->where('methods.0.key', 'cash')
            );
    }

    public function test_contant_inschrijven_zet_het_kind_op_de_lijst_met_een_open_rekening(): void
    {
        $training = $this->training();

        $this->actingAs($this->ouder)
            ->post('/trainings/'.$training->id.'/inschrijven', ['player_id' => $this->keeper->id, 'payment_method' => 'cash'])
            ->assertRedirect('/trainings')
            ->assertSessionHas('status');

        $aanmelding = $training->enrollments()->first();
        $this->assertSame(TrainingEnrollmentStatus::Confirmed, $aanmelding->status);

        $betaling = $aanmelding->payment;
        $this->assertSame(750, $betaling->amount_cents);
        $this->assertSame(PaymentStatus::Open, $betaling->status);
        $this->assertSame(PaymentMethod::Cash, $betaling->method);
        // Contant bij de training is geen achterstand, ook niet na de dag zelf.
        $betaling->due_on = now()->subWeek();
        $this->assertFalse($betaling->isOverdue());

        // Bij Komend van de ouder, en op de aanwezigheidslijst van de trainer.
        $this->actingAs($this->ouder)
            ->get('/trainings')
            ->assertInertia(fn ($page) => $page
                ->where('isParticipant', true)
                ->count('upcoming', 1)
                ->where('upcoming.0.children.1.status', 'confirmed')
                ->where('upcoming.0.children.1.cash_due', true)
            );

        $this->actingAs($this->trainer)
            ->get('/trainings/'.$training->id)
            ->assertInertia(fn ($page) => $page
                ->count('players', 1)
                ->where('players.0.loose', true)
                ->where('players.0.cash_due', true)
                ->where('training.spots_taken', 1)
            );

        // De trainer vinkt af dat het contant binnen is.
        $this->actingAs($this->trainer)
            ->post('/trainings/'.$training->id.'/aanmeldingen/'.$aanmelding->id.'/contant')
            ->assertSessionHas('status');

        $this->assertSame(PaymentStatus::Paid, $betaling->refresh()->status);
        $this->assertSame(PaymentMethod::Cash, $betaling->method);
    }

    public function test_de_ouder_ziet_de_training_bij_inschrijven_en_in_de_agenda(): void
    {
        $training = $this->training();

        $this->actingAs($this->ouder)
            ->get('/trainings')
            ->assertInertia(fn ($page) => $page
                ->count('upcoming', 0)
                ->count('enrollable', 1)
                ->where('enrollable.0.children.1.enrollable', true)
                ->where('enrollable.0.children.0.enrollable', false)
                ->where('enrollable.0.price', '€ 7,50')
                ->where('enrollable.0.spots_left', 2)
            );

        $this->actingAs($this->ouder)
            ->get('/calendar?view=month&date='.$training->starts_at->toDateString())
            ->assertInertia(fn ($page) => $page
                ->count('trainings', 1)
                ->where('trainings.0.enrollable', true)
            );
    }

    public function test_vol_is_vol_en_dan_een_wachtlijst_die_bericht_krijgt(): void
    {
        Notification::fake();

        $training = $this->training(['capacity' => 1, 'price_cents' => 0]);

        $ander = Player::factory()->for($this->school)->keeper()->create(['first_name' => 'Noud', 'age_category' => 'O12']);
        $andereOuder = User::factory()->for($this->school)->create();
        $andereOuder->assignRole(Role::Ouder->value);
        $andereOuder->children()->attach($ander->id, ['relationship' => 'moeder', 'school_id' => $this->school->id]);

        // De eerste krijgt de plek, de tweede komt op de wachtlijst.
        $this->actingAs($andereOuder)->post('/trainings/'.$training->id.'/inschrijven', ['player_id' => $ander->id]);
        $this->actingAs($this->ouder)->post('/trainings/'.$training->id.'/inschrijven', ['player_id' => $this->keeper->id]);

        $this->assertTrue($training->refresh()->isFull());
        $this->assertSame(TrainingEnrollmentStatus::Waitlisted, $training->enrollments()->where('player_id', $this->keeper->id)->first()->status);

        $this->actingAs($this->eigenaar)
            ->get('/trainings/'.$training->id)
            ->assertInertia(fn ($page) => $page
                ->where('training.spots_taken', 1)
                ->where('training.is_full', true)
                ->count('enrollments', 1)
                ->where('enrollments.0.status', 'waitlisted')
            );

        // De eerste meldt zich af: de wachtlijst hoort dat er plek is.
        $this->actingAs($andereOuder)
            ->delete('/trainings/'.$training->id.'/inschrijven/'.$ander->id)
            ->assertSessionHas('status');

        Notification::assertSentTo($this->ouder, PlekVrijTraining::class);
        $this->assertFalse($training->refresh()->isFull());

        // En kan nu alsnog inschrijven.
        $this->actingAs($this->ouder)->post('/trainings/'.$training->id.'/inschrijven', ['player_id' => $this->keeper->id]);
        $this->assertSame(TrainingEnrollmentStatus::Confirmed, $training->enrollments()->where('player_id', $this->keeper->id)->first()->status);
    }

    public function test_met_goedkeuring_is_een_aanmelding_eerst_een_aanvraag_en_komt_de_rekening_na_het_ja(): void
    {
        Notification::fake();

        $training = $this->training(['requires_approval' => true]);

        $this->actingAs($this->ouder)
            ->post('/trainings/'.$training->id.'/inschrijven', ['player_id' => $this->keeper->id, 'payment_method' => 'cash'])
            ->assertRedirect('/trainings');

        $aanmelding = $training->enrollments()->first();
        $this->assertSame(TrainingEnrollmentStatus::Requested, $aanmelding->status);
        $this->assertNull($aanmelding->payment);

        // In het aandacht-blok van de eigenaar.
        $this->actingAs($this->eigenaar)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('attention', fn ($items) => collect($items)->contains('key', 'training_requests')));

        // Een ouder mag dit niet.
        $this->actingAs($this->ouder)->post('/trainings/'.$training->id.'/aanmeldingen/'.$aanmelding->id.'/goedkeuren')->assertForbidden();

        $this->actingAs($this->trainer)
            ->post('/trainings/'.$training->id.'/aanmeldingen/'.$aanmelding->id.'/goedkeuren')
            ->assertSessionHas('status');

        $this->assertSame(TrainingEnrollmentStatus::Confirmed, $aanmelding->refresh()->status);
        $this->assertSame(750, $aanmelding->payment->amount_cents);
        Notification::assertSentTo($this->ouder, TrainingAanmeldingBeoordeeld::class, fn ($n) => $n->approved === true);
    }

    public function test_afwijzen_stuurt_het_bericht_van_de_school_mee(): void
    {
        Notification::fake();

        $training = $this->training(['requires_approval' => true]);
        $this->actingAs($this->ouder)->post('/trainings/'.$training->id.'/inschrijven', ['player_id' => $this->keeper->id, 'payment_method' => 'cash']);
        $aanmelding = $training->enrollments()->first();

        $this->actingAs($this->eigenaar)
            ->post('/trainings/'.$training->id.'/aanmeldingen/'.$aanmelding->id.'/afwijzen', ['message' => 'Deze training is voor de selectie.'])
            ->assertSessionHas('status');

        $this->assertSame(TrainingEnrollmentStatus::Declined, $aanmelding->refresh()->status);
        Notification::assertSentTo($this->ouder, TrainingAanmeldingBeoordeeld::class, fn ($n) => $n->approved === false && $n->message === 'Deze training is voor de selectie.');
    }

    public function test_een_dichte_training_of_een_verkeerde_betaalwijze_wordt_geweigerd(): void
    {
        $dicht = $this->training(['open_enrollment' => false]);

        $this->actingAs($this->ouder)->get('/trainings/'.$dicht->id.'/inschrijven')->assertForbidden();

        $alleenContant = $this->training(['payment_methods' => ['cash']]);

        $this->actingAs($this->ouder)
            ->from('/trainings')
            ->post('/trainings/'.$alleenContant->id.'/inschrijven', ['player_id' => $this->keeper->id, 'payment_method' => 'online'])
            ->assertSessionHasErrors('payment_method');
    }

    /**
     * Eenmaal ingeschreven is de knop overal weg - ook op de detailpagina, waar
     * hij bleef staan - en weigert de server een tweede aanmelding.
     */
    public function test_een_ingeschreven_kind_krijgt_nergens_meer_een_inschrijfknop(): void
    {
        $training = $this->training();

        $this->actingAs($this->ouder)
            ->get('/trainings/'.$training->id)
            ->assertInertia(fn ($page) => $page->whereNot('enrollUrl', null));

        $this->actingAs($this->ouder)
            ->post('/trainings/'.$training->id.'/inschrijven', ['player_id' => $this->keeper->id, 'payment_method' => 'cash'])
            ->assertSessionHas('status');

        // Detailpagina: status in plaats van knop.
        $this->actingAs($this->ouder)
            ->get('/trainings/'.$training->id)
            ->assertInertia(fn ($page) => $page
                ->where('enrollUrl', null)
                ->where('players.0.loose', true)
            );

        // Agenda en overzicht: niet meer als "inschrijven" gemarkeerd.
        $this->actingAs($this->ouder)
            ->get('/calendar?view=month&date='.$training->starts_at->toDateString())
            ->assertInertia(fn ($page) => $page->where('trainings.0.enrollable', false));

        $this->actingAs($this->ouder)
            ->get('/trainings')
            ->assertInertia(fn ($page) => $page->count('enrollable', 0)->count('upcoming', 1));

        // En de server weigert een tweede keer, ook als iemand de URL intypt.
        $this->actingAs($this->ouder)
            ->post('/trainings/'.$training->id.'/inschrijven', ['player_id' => $this->keeper->id, 'payment_method' => 'cash'])
            ->assertSessionHasErrors();

        $this->assertSame(1, $training->enrollments()->count());
    }

    public function test_wie_al_in_de_groep_zit_schrijft_niet_nog_eens_los_in(): void
    {
        $training = $this->training();
        $this->keeper->groups()->attach($this->groep->id);

        $this->actingAs($this->ouder)
            ->from('/trainings')
            ->post('/trainings/'.$training->id.'/inschrijven', ['player_id' => $this->keeper->id, 'payment_method' => 'cash'])
            ->assertSessionHasErrors('player_id');

        // De groep telt mee in de plekken.
        $this->assertSame(1, $training->refresh()->spotsTaken());
        $this->assertSame(0, Payment::count());
    }
}
