<?php

namespace Tests\Feature\Auth;

use App\Models\School;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * De wachtwoordlink is twee dagen geldig, niet een uur.
 *
 * Hij gaat ook naar iemand die door de school of platformbeheer een nieuwe
 * link kreeg en de mail pas 's avonds opent. Een link van een uur was dan
 * altijd al verlopen.
 */
class ResetLinkValidityTest extends TestCase
{
    use RefreshDatabase;

    public function test_de_link_werkt_na_bijna_twee_dagen_nog_en_daarna_niet_meer(): void
    {
        Notification::fake();

        $school = School::factory()->create(['name' => 'Keepersschool Rob']);
        // Zoals platformbeheer hem vroeger aanmaakte: nog nooit bevestigd.
        $user = User::factory()->for($school)->create(['email' => 'rob@rob.nl', 'email_verified_at' => null]);

        $token = $this->token($user);

        // Bijna twee dagen later: werkt nog.
        $this->travel(47)->hours();

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'rob@rob.nl',
            'password' => 'Nieuw-Wachtwoord-2026!',
            'password_confirmation' => 'Nieuw-Wachtwoord-2026!',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Nieuw-Wachtwoord-2026!', $user->fresh()->password));

        // Via de link uit zijn mail een wachtwoord gekozen: dan is het adres
        // bevestigd, en staat hij in platformbeheer niet meer op "nog niet geactiveerd".
        $this->assertNotNull($user->fresh()->email_verified_at);

        // Een nieuwe link, en die meer dan twee dagen laten liggen: verlopen.
        $this->travelBack();
        $tweede = $this->token($user->fresh());
        $this->travel(49)->hours();

        $this->post('/reset-password', [
            'token' => $tweede,
            'email' => 'rob@rob.nl',
            'password' => 'Nog-Een-Wachtwoord-2026!',
            'password_confirmation' => 'Nog-Een-Wachtwoord-2026!',
        ])->assertSessionHasErrors('email');
    }

    public function test_de_mail_zegt_twee_dagen_en_niet_dat_je_erom_vroeg(): void
    {
        $school = School::factory()->create(['name' => 'Keepersschool Rob']);
        $user = User::factory()->for($school)->create();

        $mail = (new ResetPassword('voorbeeld'))->toMail($user);
        $tekst = implode(' ', array_merge($mail->introLines, $mail->outroLines));

        $this->assertStringContainsString('2 dagen', $tekst);
        $this->assertStringContainsString('Keepersschool Rob', $tekst);
        $this->assertStringNotContainsString('Je hebt gevraagd', $tekst);
    }

    protected function token(User $user): string
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $melding) use (&$token) {
            $token = $melding->token;

            return true;
        });

        return $token;
    }
}
