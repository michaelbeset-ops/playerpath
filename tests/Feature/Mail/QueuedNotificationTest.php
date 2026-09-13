<?php

namespace Tests\Feature\Mail;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Invitation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Notifications\InschrijvingOntvangen;
use App\Notifications\Uitnodiging;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Een mail wordt opgesteld in de queue-worker, niet in het verzoek.
 *
 * Daar is niemand ingelogd, dus de terugval in AppServiceProvider levert geen
 * school op en de global scope staat fail-closed dicht: elke query binnen
 * toMail() of toArray() geeft dan niets terug. Dat is voor de veiligheid
 * precies goed en voor de inhoud precies fout - je krijgt geen foutmelding,
 * je krijgt een mail waar de helft uit weg is.
 *
 * Support\Tenancy\WithSchool is daarom voor de wachtrij wat SetCurrentSchool
 * voor een webverzoek is. Deze tests doen na wat de worker doet: de school
 * vergeten en dan pas de melding laten verwerken.
 */
class QueuedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $ouder;

    protected Player $speler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create(['name' => 'Keepersschool Rob']);
        app(Tenancy::class)->set($this->school);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);

        $this->speler = Player::factory()->for($this->school)->create(['first_name' => 'Sem']);
    }

    public function test_de_betaalknop_overleeft_de_wachtrij(): void
    {
        $order = Order::factory()->for($this->school)->create(['user_id' => $this->ouder->id, 'total_cents' => 12000]);

        $rekening = Payment::factory()->for($this->school)->create([
            'player_id' => $this->speler->id,
            'order_id' => $order->id,
            'amount_cents' => 12000,
            'description' => 'Keeperstraining najaarsblok',
            'method' => PaymentMethod::Ideal,
            'status' => PaymentStatus::Open,
            'due_on' => now()->toDateString(),
        ]);

        $inschrijving = Enrollment::factory()->for($this->school)->create(['order_id' => $order->id]);
        $inschrijving->forceFill(['status' => EnrollmentStatus::AwaitingPayment])->save();

        // Zoals in de worker: geen actieve school meer.
        app(Tenancy::class)->forget();

        $this->ouder->notify(new InschrijvingOntvangen([$inschrijving], $order, newAccount: false));

        $html = $this->laatsteMail();

        $this->assertStringContainsString('/betalen/'.$rekening->id, $html);
        $this->assertStringContainsString('Nu betalen', $html);
        $this->assertStringContainsString('120,00', $html);
        $this->assertStringNotContainsString('Er valt niets te betalen', $html);

        // En de melding in de app blijft ook staan; die wordt in dezelfde job
        // weggeschreven.
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_de_uitnodiging_noemt_het_kind_bij_naam_vanuit_de_wachtrij(): void
    {
        $uitnodiging = new Invitation([
            'name' => 'Anna de Vries',
            'email' => 'anna@voorbeeld.nl',
            'role' => Role::Ouder->value,
            'player_ids' => [$this->speler->id],
            'expires_at' => now()->addDays(14),
        ]);
        $uitnodiging->forceFill(['token' => Invitation::nieuwToken()])->save();

        app(Tenancy::class)->forget();

        NotificationFacade::route('mail', 'anna@voorbeeld.nl')->notify(new Uitnodiging($uitnodiging));

        $html = $this->laatsteMail();

        $this->assertStringContainsString('voortgang van Sem', $html);
        $this->assertStringContainsString('Keepersschool Rob', $html);
    }

    /** De scope hoort na afloop weer dicht te staan, ook als er iets misgaat. */
    public function test_de_wachtrij_laat_de_scope_niet_openstaan(): void
    {
        app(Tenancy::class)->forget();

        $this->ouder->notify(new InschrijvingOntvangen(
            [Enrollment::factory()->for($this->school)->create()], null, newAccount: true,
        ));

        $this->assertFalse(app(Tenancy::class)->hasSchool());
    }

    protected function laatsteMail(): string
    {
        $berichten = app('mailer')->getSymfonyTransport()->messages();

        $this->assertNotEmpty($berichten, 'Er is geen mail verstuurd.');

        $bericht = $berichten[count($berichten) - 1]->getOriginalMessage();

        return $bericht instanceof Email ? (string) $bericht->getHtmlBody() : $bericht->toString();
    }
}
