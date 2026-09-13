<?php

namespace Tests\Feature\Offerings;

use App\Actions\Offerings\ScheduleOffering;
use App\Enums\EnrollmentStatus;
use App\Enums\ParticipationStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Participation;
use App\Models\Payment;
use App\Models\PaymentOption;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\User;
use App\Notifications\InschrijvingOntvangen;
use App\Notifications\PlekVrijgekomen;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Vol aanbod, de wachtlijst en het doorschuiven.
 *
 * De kern: op de wachtlijst staat niets open. Een rekening sturen voor een plek
 * die er niet is, is het soort fout waar een school een half jaar over hoort.
 */
class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Product $blok;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create(['slug' => 'keepersschool-rob']);
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);

        $this->blok = Product::factory()->for($this->school)->blok(capaciteit: 1)->create([
            'name' => 'Keepersblok',
            'amount_cents' => 12000,
        ]);

        // De groep die bij een blok hoort; in de app maakt het aanbodscherm hem.
        app(ScheduleOffering::class)->group($this->blok);
        $this->blok->refresh();
    }

    protected function deelnemer(ParticipationStatus $status): Participation
    {
        return Participation::create([
            'product_id' => $this->blok->id,
            'player_id' => Player::factory()->for($this->school)->create()->id,
            'status' => $status,
        ]);
    }

    public function test_het_beheer_toont_bezetting_en_wachtlijst(): void
    {
        $this->deelnemer(ParticipationStatus::Confirmed);
        $this->deelnemer(ParticipationStatus::Waitlist);
        $this->deelnemer(ParticipationStatus::Cancelled);

        $this->actingAs($this->eigenaar)
            ->get('/aanbod/'.$this->blok->id.'/deelnemers')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('offerings/Participants')
                ->where('product.taken', 1)
                ->where('product.capacity', 1)
                ->where('product.is_full', true)
                ->count('confirmed', 1)
                ->count('waitlist', 1)
                ->count('cancelled', 1)
            );
    }

    public function test_doorschuiven_geeft_een_plek_en_maakt_de_rekening(): void
    {
        Notification::fake();

        $wachtend = $this->deelnemer(ParticipationStatus::Waitlist);

        $this->actingAs($this->eigenaar)
            ->post("/aanbod/{$this->blok->id}/deelnemers/{$wachtend->id}/plek")
            ->assertRedirect();

        $this->assertSame(ParticipationStatus::Confirmed, $wachtend->refresh()->status);

        // Hier ontstaat pas het geld.
        $this->assertDatabaseHas('purchases', ['player_id' => $wachtend->player_id, 'amount_cents' => 12000]);
        $this->assertDatabaseHas('payments', ['player_id' => $wachtend->player_id, 'amount_cents' => 12000, 'status' => 'open']);

        // En hij staat in de groep, dus op de aanwezigheidslijst.
        $this->assertTrue($wachtend->player->fresh()->groups->contains('id', $this->blok->group->id));
    }

    public function test_doorschuiven_kan_niet_als_het_vol_is(): void
    {
        $this->deelnemer(ParticipationStatus::Confirmed);
        $wachtend = $this->deelnemer(ParticipationStatus::Waitlist);

        // Zonder deze grens zet een school er per ongeluk een dertiende bij.
        $this->actingAs($this->eigenaar)
            ->post("/aanbod/{$this->blok->id}/deelnemers/{$wachtend->id}/plek")
            ->assertStatus(422);

        $this->assertSame(ParticipationStatus::Waitlist, $wachtend->refresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_iemand_van_de_lijst_halen_maakt_weer_plek(): void
    {
        $deelnemer = $this->deelnemer(ParticipationStatus::Confirmed);
        $deelnemer->player->groups()->attach($this->blok->group->id);

        $this->actingAs($this->eigenaar)
            ->delete("/aanbod/{$this->blok->id}/deelnemers/{$deelnemer->id}")
            ->assertRedirect();

        $this->assertSame(ParticipationStatus::Cancelled, $deelnemer->refresh()->status);
        $this->assertFalse($this->blok->fresh()->isFull());

        // En uit de groep, zodat hij niet op de aanwezigheidslijst van de
        // eerstvolgende training blijft staan.
        $this->assertFalse($deelnemer->player->fresh()->groups->contains('id', $this->blok->group->id));
    }

    public function test_goedkeuren_van_een_wachtlijstplek_levert_geen_rekening_op(): void
    {
        Notification::fake();

        $this->deelnemer(ParticipationStatus::Confirmed);

        app(Tenancy::class)->forget();
        $this->post('/inschrijven/keepersschool-rob', [
            'children' => [[
                'first_name' => 'Noud', 'last_name' => 'Jansen', 'date_of_birth' => '2016-09-30', 'position' => 'keeper',
                'product_id' => $this->blok->id, 'payment_option_id' => PaymentOption::withoutSchoolScope()->where('product_id', $this->blok->id)->value('id'),
            ]],
            'guardian_name' => 'Marieke de Vries', 'guardian_email' => 'marieke@voorbeeld.nl', 'password' => 'wachtwoord123',
            'consents' => ['avg'], 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();
        app(Tenancy::class)->set($this->school);

        $inschrijving = Enrollment::firstOrFail();
        $this->assertSame(EnrollmentStatus::Waitlist, $inschrijving->status);

        $nieuw = Player::where('first_name', 'Noud')->firstOrFail();

        // De speler en de ouder ontstaan wel - anders kan de school niemand
        // bereiken - maar betalen voor een plek die er niet is gebeurt nooit.
        $this->assertDatabaseHas('participations', [
            'product_id' => $this->blok->id,
            'player_id' => $nieuw->id,
            'status' => 'waitlist',
        ]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('subscriptions', 0);

        $ouder = User::where('email', 'marieke@voorbeeld.nl')->firstOrFail();

        // En het bericht zegt dat ook, in plaats van "welkom".
        Notification::assertSentTo($ouder, InschrijvingOntvangen::class, function (InschrijvingOntvangen $melding) use ($ouder) {
            $this->assertStringContainsString('wachtlijst', implode(' ', $melding->toMail($ouder)->introLines));

            return true;
        });
    }

    public function test_de_ouder_hoort_het_als_er_een_plek_vrijkomt(): void
    {
        Notification::fake();

        $wachtend = $this->deelnemer(ParticipationStatus::Waitlist);

        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);
        $ouder->children()->attach($wachtend->player_id);

        $this->actingAs($this->eigenaar)->post("/aanbod/{$this->blok->id}/deelnemers/{$wachtend->id}/plek");

        // Iemand die op een wachtlijst staat kijkt niet elke dag in de app -
        // dat is precies waarom hij op een wachtlijst staat.
        Notification::assertSentTo($ouder, PlekVrijgekomen::class);
    }

    public function test_een_trainer_beheert_de_wachtlijst_niet(): void
    {
        $trainer = User::factory()->for($this->school)->create();
        $trainer->assignRole(Role::Trainer->value);

        $wachtend = $this->deelnemer(ParticipationStatus::Waitlist);

        $this->actingAs($trainer)->get('/aanbod/'.$this->blok->id.'/deelnemers')->assertForbidden();
        $this->actingAs($trainer)->post("/aanbod/{$this->blok->id}/deelnemers/{$wachtend->id}/plek")->assertForbidden();
    }

    public function test_een_deelnemer_van_een_ander_aanbod_hoort_hier_niet(): void
    {
        $ander = Product::factory()->for($this->school)->blok()->create();
        $wachtend = $this->deelnemer(ParticipationStatus::Waitlist);

        $this->actingAs($this->eigenaar)
            ->post("/aanbod/{$ander->id}/deelnemers/{$wachtend->id}/plek")
            ->assertNotFound();
    }

    public function test_het_beheer_toont_wie_er_nog_moet_betalen(): void
    {
        Notification::fake();

        $wachtend = $this->deelnemer(ParticipationStatus::Waitlist);

        // Doorschuiven maakt de rekening; die staat dan open.
        $this->actingAs($this->eigenaar)->post("/aanbod/{$this->blok->id}/deelnemers/{$wachtend->id}/plek");

        $this->actingAs($this->eigenaar)
            ->get('/aanbod/'.$this->blok->id.'/deelnemers')
            ->assertInertia(fn ($page) => $page
                ->where('paidCount', 0)
                ->where('confirmed.0.payment_status', 'open')
                ->where('confirmed.0.amount', '€ 120,00')
            );

        // En zodra hij betaald is, ook.
        Payment::first()->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);

        $this->actingAs($this->eigenaar)
            ->get('/aanbod/'.$this->blok->id.'/deelnemers')
            ->assertInertia(fn ($page) => $page
                ->where('paidCount', 1)
                ->where('confirmed.0.payment_status', 'paid')
            );
    }

    public function test_het_betaaloverzicht_filtert_op_aanbod(): void
    {
        Notification::fake();

        $wachtend = $this->deelnemer(ParticipationStatus::Waitlist);
        $this->actingAs($this->eigenaar)->post("/aanbod/{$this->blok->id}/deelnemers/{$wachtend->id}/plek");

        // Een rekening die niet bij dit aanbod hoort.
        Payment::create([
            'player_id' => $wachtend->player_id,
            'amount_cents' => 999,
            'status' => PaymentStatus::Open,
            'description' => 'Iets anders',
            'due_on' => now()->toDateString(),
        ]);

        // "Wie heeft het kamp al betaald" is een vraag over een aanbod, niet
        // over een maand.
        $this->actingAs($this->eigenaar)
            ->get('/payments?period=all&tab=all&product='.$this->blok->id)
            ->assertInertia(fn ($page) => $page
                ->count('payments', 1)
                ->where('payments.0.amount', '€ 120,00')
            );
    }
}
