<?php

namespace Tests\Feature\Offerings;

use App\Actions\Offerings\ScheduleOffering;
use App\Enums\EnrollmentStatus;
use App\Enums\ParticipationStatus;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Participation;
use App\Models\PaymentOption;
use App\Models\Player;
use App\Models\Product;
use App\Models\School;
use App\Models\User;
use App\Models\WaitlistInvitation;
use App\Notifications\PlekVrijgekomen;
use App\Notifications\UitnodigingVerlopen;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * De wachtlijst (onderdeel 6): vol aanbod, uitnodigen met een betaallink en
 * een tijdslimiet, en de volgende die doorschuift als die verloopt.
 */
class WaitlistInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $eigenaar;

    protected Product $blok;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00'));

        $this->school = School::factory()->create(['slug' => 'keepersschool-rob']);
        $this->eigenaar = User::factory()->for($this->school)->create();
        $this->eigenaar->assignRole(Role::Eigenaar->value);

        app(Tenancy::class)->set($this->school);

        $this->blok = Product::factory()->for($this->school)->blok(capaciteit: 1)->create(['name' => 'Keepersblok', 'amount_cents' => 12000]);
        app(ScheduleOffering::class)->group($this->blok);
        $this->blok->refresh();

        // De ene plek is bezet.
        Participation::create(['product_id' => $this->blok->id, 'player_id' => Player::factory()->for($this->school)->create()->id, 'status' => ParticipationStatus::Confirmed]);

        app(Tenancy::class)->forget();
    }

    protected function meldAan(string $voornaam, string $email): Enrollment
    {
        $this->post('/inschrijven/keepersschool-rob', [
            'children' => [[
                'first_name' => $voornaam, 'last_name' => 'Test', 'date_of_birth' => '2016-04-12', 'position' => 'keeper',
                'product_id' => $this->blok->id,
                'payment_option_id' => PaymentOption::withoutSchoolScope()->where('product_id', $this->blok->id)->value('id'),
            ]],
            'guardian_name' => 'Ouder '.$voornaam, 'guardian_email' => $email, 'password' => 'wachtwoord123',
            'consents' => ['avg'], 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        return Enrollment::withoutSchoolScope()->where('first_name', $voornaam)->firstOrFail();
    }

    public function test_vol_aanbod_geeft_een_wachtlijstplek_zonder_betaling(): void
    {
        Notification::fake();

        $eerste = $this->meldAan('Noud', 'noud@voorbeeld.nl');

        $this->assertSame(EnrollmentStatus::Waitlist, $eerste->status);
        $this->assertNull($eerste->order_id);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_uitnodigen_maakt_de_rekening_met_een_tijdslimiet(): void
    {
        Notification::fake();
        $eerste = $this->meldAan('Noud', 'noud@voorbeeld.nl');

        app(Tenancy::class)->set($this->school);

        // Vol: uitnodigen kan nog niet.
        $this->actingAs($this->eigenaar)->post('/enrollments/'.$eerste->id.'/approve')->assertSessionHasErrors('enrollment');

        // Er komt een plek vrij.
        Participation::where('status', ParticipationStatus::Confirmed->value)->first()->update(['status' => ParticipationStatus::Cancelled]);

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$eerste->id.'/approve')->assertSessionHasNoErrors();

        $eerste->refresh();

        $this->assertSame(EnrollmentStatus::AwaitingPayment, $eerste->status);
        $this->assertSame(12000, $eerste->order->total_cents);
        $this->assertDatabaseHas('payments', ['order_id' => $eerste->order_id, 'amount_cents' => 12000, 'status' => 'open']);

        $uitnodiging = WaitlistInvitation::firstOrFail();
        $this->assertTrue($uitnodiging->isOpen());
        // Standaard drie dagen.
        $this->assertSame('2026-09-04', $uitnodiging->expires_at->toDateString());

        $ouder = User::where('email', 'noud@voorbeeld.nl')->firstOrFail();
        Notification::assertSentTo($ouder, PlekVrijgekomen::class, function (PlekVrijgekomen $m) use ($ouder) {
            $mail = $m->toMail($ouder);
            $this->assertStringContainsString('/betalen/', (string) $mail->actionUrl);
            $this->assertStringContainsString('4 september', implode(' ', $mail->introLines));

            return true;
        });

        // Betaald op tijd: de plek is van hem.
        $rekening = $eerste->order->payments()->firstOrFail();
        $this->actingAs($this->eigenaar)->patch('/payments/'.$rekening->id, ['status' => 'paid', 'method' => 'cash']);

        $this->assertSame(EnrollmentStatus::Confirmed, $eerste->refresh()->status);
        $this->assertDatabaseHas('participations', ['player_id' => $eerste->player_id, 'status' => 'confirmed']);
        $this->assertTrue($this->blok->fresh()->isFull());
    }

    public function test_een_verlopen_uitnodiging_laat_de_volgende_doorschuiven(): void
    {
        Notification::fake();
        $eerste = $this->meldAan('Noud', 'noud@voorbeeld.nl');
        $tweede = $this->meldAan('Mila', 'mila@voorbeeld.nl');

        app(Tenancy::class)->set($this->school);
        Participation::where('status', ParticipationStatus::Confirmed->value)->first()->update(['status' => ParticipationStatus::Cancelled]);

        $this->actingAs($this->eigenaar)->post('/enrollments/'.$eerste->id.'/approve');
        $this->assertSame(EnrollmentStatus::AwaitingPayment, $eerste->refresh()->status);

        // Binnen de limiet gebeurt er niets.
        $this->travelTo(CarbonImmutable::parse('2026-09-03 12:00'));
        $this->artisan('enrollments:lifecycle');
        $this->assertSame(EnrollmentStatus::AwaitingPayment, $eerste->refresh()->status);
        $this->assertSame(EnrollmentStatus::Waitlist, $tweede->refresh()->status);

        // Erna: de eerste vervalt, de tweede krijgt de uitnodiging.
        $this->travelTo(CarbonImmutable::parse('2026-09-05 06:00'));
        $this->artisan('enrollments:lifecycle');

        $eerste->refresh();
        $tweede->refresh();

        $this->assertSame(EnrollmentStatus::Expired, $eerste->status);
        $this->assertDatabaseHas('payments', ['order_id' => $eerste->order_id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('participations', ['player_id' => $eerste->player_id, 'status' => 'cancelled']);
        Notification::assertSentTo(User::where('email', 'noud@voorbeeld.nl')->firstOrFail(), UitnodigingVerlopen::class);

        $this->assertSame(EnrollmentStatus::AwaitingPayment, $tweede->status);
        $this->assertNotNull($tweede->order_id);
        $this->assertSame(2, WaitlistInvitation::count());
        Notification::assertSentTo(User::where('email', 'mila@voorbeeld.nl')->firstOrFail(), PlekVrijgekomen::class);

        // Twee keer draaien doet niets twee keer.
        $this->artisan('enrollments:lifecycle');
        $this->assertSame(2, WaitlistInvitation::count());
    }

    public function test_doorschuiven_vanaf_het_deelnemersscherm_loopt_via_dezelfde_uitnodiging(): void
    {
        Notification::fake();
        $eerste = $this->meldAan('Noud', 'noud@voorbeeld.nl');

        app(Tenancy::class)->set($this->school);
        Participation::where('status', ParticipationStatus::Confirmed->value)->first()->update(['status' => ParticipationStatus::Cancelled]);

        $deelname = Participation::where('player_id', $eerste->player_id)->firstOrFail();

        $this->actingAs($this->eigenaar)
            ->post("/aanbod/{$this->blok->id}/deelnemers/{$deelname->id}/plek")
            ->assertRedirect();

        $this->assertSame(EnrollmentStatus::AwaitingPayment, $eerste->refresh()->status);
        $this->assertSame(1, WaitlistInvitation::count());
        // Nog niet in de groep: eerst betalen.
        $this->assertSame(ParticipationStatus::Waitlist, $deelname->refresh()->status);
    }
}
