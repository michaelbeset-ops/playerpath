<?php

namespace Tests\Feature\Mail;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Player;
use App\Models\School;
use App\Models\User;
use App\Notifications\BetalingHerinnering;
use App\Notifications\InschrijvingOntvangen;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De gedeelde mailschil: elke mail draagt de school en niet PlayerPath.
 *
 * Dit is geen cosmetica. Een ouder heeft zijn kind bij Keepersschool Rob
 * aangemeld; herkent hij de afzender niet, dan opent hij de mail niet, en dan
 * is een betaalherinnering of een afgelasting waardeloos.
 */
class MailShellTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $ouder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create([
            'name' => 'Keepersschool Rob',
            'brand_color' => '#1D4ED8',
            'contact_email' => 'info@keepersschoolrob.nl',
            'contact_phone' => '06 12 34 56 78',
        ]);

        app(Tenancy::class)->set($this->school);

        $this->ouder = User::factory()->for($this->school)->create();
        $this->ouder->assignRole(Role::Ouder->value);
    }

    public function test_de_mail_draagt_de_school_en_niet_playerpath(): void
    {
        $mail = (new BetalingHerinnering($this->rekening(), 5))->toMail($this->ouder);

        // Afzendernaam en antwoordadres zijn van de school; het verzendadres
        // blijft van het platform, want daar staan de SPF-records op.
        $this->assertSame('Keepersschool Rob', $mail->from[1]);
        $this->assertSame(config('mail.from.address'), $mail->from[0]);
        $this->assertSame(['info@keepersschoolrob.nl', 'Keepersschool Rob'], $mail->replyTo[0]);

        $html = (string) $mail->render();

        $this->assertStringContainsString('Keepersschool Rob', $html);
        $this->assertStringContainsString('info@keepersschoolrob.nl', $html);
        // De merkkleur staat op de knop; het standaardgroen niet meer.
        $this->assertStringContainsString('#1D4ED8', $html);
        $this->assertStringNotContainsString('#12813D', $html);
        // Alleen onderaan, als kleine regel: dit is haar mail, niet die van ons.
        $this->assertStringContainsString('Verstuurd met PlayerPath', $html);

        // Geen Engelse resten uit het standaardsjabloon van Laravel.
        $this->assertStringNotContainsString('All rights reserved', $html);
        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringNotContainsString("If you're having trouble", $html);
        $this->assertStringContainsString('Werkt de knop', $html);

        // De voorbeeldregel in de inbox is de eerste zin, zonder opmaaktekens.
        $this->assertStringContainsString('Het gaat om € 120,00 voor', $html);
    }

    /** Zonder eigen kleur of logo blijft het merkgroen van PlayerPath staan. */
    public function test_een_school_zonder_huisstijl_valt_terug_op_playerpath(): void
    {
        $this->school->update(['brand_color' => null, 'contact_email' => null]);

        $mail = (new BetalingHerinnering($this->rekening(), 5))->toMail($this->ouder->refresh());

        $this->assertSame([], $mail->replyTo);
        $this->assertStringContainsString('#12813D', (string) $mail->render());
    }

    /**
     * Eén knop per mail.
     *
     * Een tweede ->action() overschrijft de eerste. Bij een nieuwe ouder met
     * een openstaande rekening verdween daardoor precies de betaalknop waar
     * de mail voor bedoeld was.
     */
    public function test_een_nieuw_account_neemt_de_betaalknop_niet_over(): void
    {
        $order = Order::factory()->for($this->school)->create(['user_id' => $this->ouder->id, 'total_cents' => 12000]);
        $rekening = $this->rekening(['order_id' => $order->id]);

        $inschrijving = Enrollment::factory()->for($this->school)->create(['order_id' => $order->id]);
        $inschrijving->forceFill(['status' => EnrollmentStatus::AwaitingPayment])->save();

        $mail = (new InschrijvingOntvangen([$inschrijving], $order, newAccount: true))->toMail($this->ouder);

        $this->assertSame('Nu betalen', $mail->actionText);
        $this->assertStringContainsString('/betalen/'.$rekening->id, (string) $mail->actionUrl);
        // Het inloggen staat als zin ná de knop, niet als tweede knop ervoor.
        $this->assertStringContainsString('Er is ook een account voor je aangemaakt', implode(' ', $mail->outroLines));
    }

    /**
     * Wachtwoord vergeten en e-mailadres bevestigen komen uit Laravel zelf en
     * gaan dus niet langs SendsFromSchool. Juist die eerste mail moet de school
     * dragen: een schooleigenaar zet zijn wachtwoord via wachtwoord-vergeten,
     * en een ouder die zijn wachtwoord kwijt is komt hier terecht.
     */
    public function test_de_mails_van_laravel_dragen_de_school_ook(): void
    {
        $reset = (new ResetPassword('een-token'))->toMail($this->ouder);

        $this->assertSame('Keepersschool Rob', $reset->from[1]);
        $this->assertSame('Kies een nieuw wachtwoord', $reset->subject);
        $this->assertStringContainsString('/reset-password/een-token', (string) $reset->actionUrl);

        $html = (string) $reset->render();
        $this->assertStringContainsString('Keepersschool Rob', $html);
        $this->assertStringContainsString('#1D4ED8', $html);
        $this->assertStringNotContainsString('Hallo!', $html);

        $verify = (new VerifyEmail)->toMail($this->ouder);

        $this->assertSame('Keepersschool Rob', $verify->from[1]);
        $this->assertSame('Bevestig je e-mailadres', $verify->subject);
        $this->assertStringContainsString('Keepersschool Rob', (string) $verify->render());
    }

    /**
     * Een mail zonder school komt van PlayerPath zelf — de platformbeheerder
     * heeft geen school — en draagt dan ons logo, met een vaste breedte. Een
     * school zonder eigen logo krijgt dat logo juist níét, maar haar naam.
     */
    public function test_zonder_school_staat_het_logo_van_playerpath_erboven(): void
    {
        $beheerder = User::factory()->create(['school_id' => null]);

        $html = (string) (new ResetPassword('een-token'))->toMail($beheerder)->render();

        $this->assertStringContainsString('/brand/logo.png', $html);
        $this->assertStringContainsString('width="180"', $html);

        $schoolmail = (string) (new ResetPassword('een-token'))->toMail($this->ouder)->render();

        $this->assertStringNotContainsString('/brand/logo.png', $schoolmail);
        $this->assertStringContainsString('Keepersschool Rob', $schoolmail);
    }

    /** @param  array<string, mixed>  $velden */
    protected function rekening(array $velden = []): Payment
    {
        $speler = Player::factory()->for($this->school)->create();

        return Payment::factory()->for($this->school)->create(array_merge([
            'player_id' => $speler->id,
            'amount_cents' => 12000,
            'description' => 'Keeperstraining najaarsblok',
            'method' => PaymentMethod::Ideal,
            'status' => PaymentStatus::Open,
            'due_on' => now()->subDays(5)->toDateString(),
        ], $velden));
    }
}
