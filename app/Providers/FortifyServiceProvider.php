<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Support\Mail\MailBrand;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Fortify levert de auth-routes en -logica; de schermen zijn Inertia/Vue-pagina's.
     */
    public function boot(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->authMails();

        Fortify::loginView(fn () => Inertia::render('auth/Login', [
            'canResetPassword' => true,
            'status' => session('status'),
        ]));

        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/ForgotPassword', [
            'status' => session('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->input('email'),
            'token' => $request->route('token'),
        ]));

        Fortify::verifyEmailView(fn () => Inertia::render('auth/VerifyEmail', [
            'status' => session('status'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));

        // Maximaal 5 inlogpogingen per minuut per e-mailadres + IP.
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower($request->input(Fortify::username())).'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }

    /**
     * De twee mails die niet van ons zijn: wachtwoord vergeten en e-mailadres
     * bevestigen.
     *
     * Ze komen uit Laravel zelf en gaan dus niet langs `SendsFromSchool`. Zonder
     * dit blok draagt precies de eerste mail die een schooleigenaar krijgt de
     * naam PlayerPath - hij zet zijn wachtwoord immers via wachtwoord-vergeten
     * (zie DEPLOY.md), en een ouder komt hier terecht zodra hij zijn wachtwoord
     * kwijt is. Dan ken je de afzender niet, en dan kom je je account niet meer
     * in.
     *
     * De teksten stonden als losse woorden in lang/nl.json ("Hallo!",
     * "Wachtwoord opnieuw instellen"). Hier staan ze als hele zinnen, met de
     * kop die zegt wat er te doen is in plaats van een begroeting.
     */
    protected function authMails(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $minuten = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

            // "2 dagen" leest beter dan "2880 minuten".
            $geldig = match (true) {
                $minuten >= 1440 && $minuten % 1440 === 0 => ($minuten / 1440).' '.($minuten === 1440 ? 'dag' : 'dagen'),
                $minuten >= 60 && $minuten % 60 === 0 => ($minuten / 60).' uur',
                default => $minuten.' minuten',
            };

            // Neutraal: deze mail gaat ook naar iemand die niet zelf op
            // "wachtwoord vergeten" drukte, maar door de school of platformbeheer
            // een nieuwe link kreeg. "Je hebt gevraagd" klopt dan niet.
            return MailBrand::apply(new MailMessage, $notifiable->school ?? null)
                ->subject('Kies je wachtwoord')
                ->greeting('Kies je wachtwoord')
                ->line('Klik hieronder om een wachtwoord te kiezen voor je account'.($notifiable->school?->name ? ' bij '.$notifiable->school->name : '').'. Daarna log je meteen in.')
                ->action('Wachtwoord kiezen', url(route('password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ], absolute: false)))
                ->line("Deze link is {$geldig} geldig en werkt één keer.")
                ->line('Heb je hier niet om gevraagd? Dan hoef je niets te doen; je wachtwoord blijft zoals het was.')
                ->salutation('Met vriendelijke groet, '.($notifiable->school?->name ?? config('app.name')));
        });

        VerifyEmail::toMailUsing(function (object $notifiable) {
            $url = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
            );

            return MailBrand::apply(new MailMessage, $notifiable->school ?? null)
                ->subject('Bevestig je e-mailadres')
                ->greeting('Bevestig je e-mailadres')
                ->line('Nog één klik en je account is klaar voor gebruik.')
                ->action('E-mailadres bevestigen', $url)
                ->line('Heb je geen account aangemaakt? Dan hoef je niets te doen.')
                ->salutation('Met vriendelijke groet, '.($notifiable->school?->name ?? config('app.name')));
        });
    }
}
