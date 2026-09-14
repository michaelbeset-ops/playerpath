<?php

namespace Tests\Feature\Mail;

use App\Actions\Onboarding\SendInvitation;
use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Notifications\Uitnodiging;
use App\Support\Mail\MailBrand;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

/**
 * Aan een eigenaar schrijft PlayerPath, niet de school. Aan een ouder of
 * trainer blijft het de school.
 */
class OwnerMailTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->school = School::factory()->create([
            'name' => 'Keepersschool Rob',
            'contact_email' => 'info@rob.nl',
            'contact_phone' => '06 11 22 33 44',
        ]);

        app(Tenancy::class)->set($this->school);
    }

    public function test_de_uitnodiging_aan_een_eigenaar_komt_van_playerpath(): void
    {
        $uitnodiging = app(SendInvitation::class)->handle($this->school, 'Rob', 'rob@rob.nl', Role::Eigenaar->value, send: false);

        $mail = (new Uitnodiging($uitnodiging))->toMail(new AnonymousNotifiable);

        $this->assertSame(config('app.name'), $mail->from[1]);
        $this->assertSame([], $mail->replyTo);
        $this->assertStringContainsString('PlayerPath', $mail->subject);

        $html = (string) $mail->render();

        $this->assertStringContainsString(MailBrand::PLATFORM_AFZENDER, $html);
        $this->assertStringContainsString('Keepersschool Rob', $html);
        $this->assertStringNotContainsString('info@rob.nl', $html);
        $this->assertStringNotContainsString('06 11 22 33 44', $html);
        $this->assertStringNotContainsString('vraag Keepersschool Rob', $html);
    }

    public function test_een_ouder_krijgt_de_uitnodiging_nog_steeds_van_de_school(): void
    {
        $uitnodiging = app(SendInvitation::class)->handle($this->school, 'Anna', 'anna@voorbeeld.nl', Role::Ouder->value, send: false);

        $mail = (new Uitnodiging($uitnodiging))->toMail(new AnonymousNotifiable);

        $this->assertSame('Keepersschool Rob', $mail->from[1]);
        $this->assertSame('info@rob.nl', $mail->replyTo[0][0]);

        $html = (string) $mail->render();

        $this->assertStringNotContainsString(MailBrand::PLATFORM_AFZENDER, $html);
        $this->assertStringContainsString('vraag Keepersschool Rob', $html);
    }

    public function test_de_wachtwoordmail_aan_een_eigenaar_is_door_playerpath_ondertekend(): void
    {
        $eigenaar = User::factory()->for($this->school)->create();
        $eigenaar->assignRole(Role::Eigenaar->value);

        $ouder = User::factory()->for($this->school)->create();
        $ouder->assignRole(Role::Ouder->value);

        $aanEigenaar = (new ResetPassword('token'))->toMail($eigenaar);
        $aanOuder = (new ResetPassword('token'))->toMail($ouder);

        $this->assertStringContainsString(MailBrand::PLATFORM_AFZENDER, $aanEigenaar->salutation);
        $this->assertStringContainsString('Keepersschool Rob', implode(' ', $aanEigenaar->introLines));

        $this->assertStringContainsString('Keepersschool Rob', $aanOuder->salutation);
        $this->assertSame('Keepersschool Rob', $aanOuder->from[1]);
    }
}
