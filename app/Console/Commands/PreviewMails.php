<?php

namespace App\Console\Commands;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\TrainingEnrollmentStatus;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\Goal;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Product;
use App\Models\Report;
use App\Models\School;
use App\Models\Training;
use App\Models\TrainingEnrollment;
use App\Models\User;
use App\Notifications;
use App\Support\Tenancy\Tenancy;
use Faker\Factory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Console\Command;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Elke mail die de app kan versturen, als bestand om naar te kijken.
 *
 * Mail is het enige deel van het product dat je niet in de app kunt
 * controleren: je ziet hem pas als hij verstuurd is, en dan is het te laat.
 * Deze opdracht zet ze allemaal naast elkaar met verzonnen gegevens, zodat een
 * wijziging aan de opmaak in één keer over de hele stapel te bekijken is -
 * ook op een telefoonbreedte, want daar wordt hij gelezen.
 *
 * Alles gebeurt in een transactie die wordt teruggedraaid: er blijft geen
 * verzonnen kind, order of rekening achter in de database.
 */
class PreviewMails extends Command
{
    protected $signature = 'mail:preview {--dir= : Waar de bestanden komen}';

    protected $description = 'Schrijf elke e-mail als HTML weg om de vormgeving te bekijken';

    public function handle(Tenancy $tenancy): int
    {
        // Dit is een ontwikkelhulpmiddel: het bouwt zijn voorbeeldwereld met
        // factories, en die hangen aan fakerphp/faker uit require-dev. Op een
        // productieserver draait composer met --no-dev, dus daar bestaat dat
        // pakket niet. Liever deze zin dan een fatale fout waar je op de
        // verkeerde plek naar gaat zoeken.
        if (! class_exists(Factory::class)) {
            $this->components->error('mail:preview is een ontwikkelhulpmiddel en werkt niet op productie: fakerphp/faker zit in require-dev.');
            $this->line('  Draai het lokaal, of bekijk een echte mail door hem naar jezelf te sturen.');

            return self::FAILURE;
        }

        $map = $this->option('dir') ?: storage_path('app/mail-preview');
        File::ensureDirectoryExists($map);
        File::cleanDirectory($map);

        DB::beginTransaction();

        try {
            $wereld = $this->wereld($tenancy);
            $overzicht = [];

            foreach ($this->mails($wereld) as $naam => $maak) {
                try {
                    /** @var Notification $melding */
                    [$melding, $ontvanger] = $maak();
                    $mail = $melding->toMail($ontvanger);

                    if (! $mail instanceof MailMessage) {
                        continue;
                    }

                    $bestand = $map.DIRECTORY_SEPARATOR.$naam.'.html';
                    File::put($bestand, (string) $mail->render());

                    $overzicht[] = ['naam' => $naam, 'onderwerp' => $mail->subject, 'bestand' => basename($bestand)];
                    $this->line("  <fg=green>✓</> {$naam} - {$mail->subject}");
                } catch (Throwable $e) {
                    $this->line("  <fg=red>✗</> {$naam} - {$e->getMessage()}");
                }
            }

            File::put($map.DIRECTORY_SEPARATOR.'index.html', $this->index($overzicht));
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        $this->info('Klaar: '.$map.DIRECTORY_SEPARATOR.'index.html');

        return self::SUCCESS;
    }

    /**
     * Een school met één gezin erin, genoeg om elke mail te kunnen vullen.
     *
     * @return array<string, mixed>
     */
    protected function wereld(Tenancy $tenancy): array
    {
        $school = School::factory()->create([
            'name' => 'Keepersschool Rob',
            'brand_color' => '#1D4ED8',
            'contact_email' => 'info@keepersschoolrob.nl',
            'contact_phone' => '06 12 34 56 78',
        ]);

        $tenancy->set($school);

        $eigenaar = User::factory()->for($school)->create(['name' => 'Rob Jansen', 'email' => 'rob@voorbeeld.nl']);
        $eigenaar->assignRole(Role::Eigenaar->value);

        $ouder = User::factory()->for($school)->create(['name' => 'Anna de Vries', 'email' => 'anna@voorbeeld.nl']);
        $ouder->assignRole(Role::Ouder->value);

        $speler = Player::factory()->for($school)->keeper()->create(['first_name' => 'Sem', 'last_name' => 'de Vries']);
        $speler->guardians()->attach($ouder->id, ['relationship' => 'moeder', 'school_id' => $school->id]);

        $groep = Group::factory()->for($school)->create(['name' => 'Keepers O12']);
        $speler->groups()->attach($groep->id);

        $training = Training::factory()->for($school)->for($groep)->create([
            'starts_at' => now()->addDays(3)->setTime(18, 0),
            'ends_at' => now()->addDays(3)->setTime(19, 30),
        ]);

        $aanbod = Product::factory()->for($school)->create([
            'name' => 'Keeperstraining najaarsblok',
            'amount_cents' => 12000,
        ]);

        $order = Order::factory()->for($school)->create(['user_id' => $ouder->id, 'total_cents' => 12000]);

        $rekening = Payment::factory()->for($school)->create([
            'player_id' => $speler->id,
            'order_id' => $order->id,
            'amount_cents' => 12000,
            'description' => 'Keeperstraining najaarsblok',
            'method' => PaymentMethod::Ideal,
            'status' => PaymentStatus::Open,
            'due_on' => now()->subDays(5)->toDateString(),
        ]);

        $inschrijving = Enrollment::factory()->for($school)->create([
            'first_name' => 'Sem',
            'last_name' => 'de Vries',
            'guardian_name' => 'Anna de Vries',
            'guardian_email' => 'anna@voorbeeld.nl',
            'product_id' => $aanbod->id,
            'order_id' => $order->id,
            'guardian_user_id' => $ouder->id,
        ]);
        $inschrijving->forceFill(['refund_cents' => 6000, 'cancellation_reason' => 'Verhuisd'])->save();

        $rapport = Report::factory()->for($school)->for($speler)->create(['trainer_id' => $eigenaar->id]);

        $doel = Goal::factory()->for($school)->for($speler)->create();

        $mededeling = Announcement::factory()->for($school)->create([
            'title' => 'Training van woensdag gaat niet door',
            'body' => "Door de storm is het complex gesloten.\n\nDe training van woensdag vervalt; volgende week staan we er weer.",
        ]);

        $losseAanmelding = TrainingEnrollment::create([
            'training_id' => $training->id,
            'player_id' => $speler->id,
            'user_id' => $ouder->id,
            'status' => TrainingEnrollmentStatus::Confirmed->value,
            'payment_method' => PaymentMethod::Ideal->value,
        ]);

        $uitnodiging = new Invitation([
            'name' => 'Anna de Vries',
            'email' => 'anna@voorbeeld.nl',
            'role' => Role::Ouder->value,
            'player_ids' => [$speler->id],
            'expires_at' => now()->addDays(14),
        ]);
        $uitnodiging->forceFill(['token' => Invitation::nieuwToken()])->save();

        return compact(
            'school', 'eigenaar', 'ouder', 'speler', 'groep', 'training', 'aanbod',
            'order', 'rekening', 'inschrijving', 'rapport', 'doel', 'mededeling',
            'losseAanmelding', 'uitnodiging',
        );
    }

    /**
     * @param  array<string, mixed>  $w
     * @return array<string, callable(): array{Notification, object}>
     */
    protected function mails(array $w): array
    {
        $ouder = $w['ouder'];
        $eigenaar = $w['eigenaar'];

        return [
            'uitnodiging-ouder' => fn () => [new Notifications\Uitnodiging($w['uitnodiging']), $ouder],
            'inschrijving-ontvangen' => fn () => [new Notifications\InschrijvingOntvangen([$w['inschrijving']], $w['order'], true), $ouder],
            'inschrijving-goedgekeurd' => fn () => [new Notifications\InschrijvingGoedgekeurd($w['speler'], $w['rekening']), $ouder],
            'inschrijving-geannuleerd-ouder' => fn () => [new Notifications\InschrijvingGeannuleerd($w['inschrijving'], false), $ouder],
            'inschrijving-geannuleerd-school' => fn () => [new Notifications\InschrijvingGeannuleerd($w['inschrijving'], true), $eigenaar],
            'nieuwe-inschrijving' => fn () => [new Notifications\NieuweInschrijving($w['inschrijving']), $eigenaar],
            'verleng-uitnodiging' => fn () => [new Notifications\VerlengUitnodiging($w['inschrijving']), $ouder],
            'uitnodiging-verlopen' => fn () => [new Notifications\UitnodigingVerlopen($w['inschrijving']), $ouder],
            'plek-vrijgekomen' => fn () => [new Notifications\PlekVrijgekomen($w['aanbod'], $w['speler'], url('/betalen/voorbeeld'), now()->addDays(3)), $ouder],
            'plek-vrij-training' => fn () => [new Notifications\PlekVrijTraining($w['training'], $w['speler']), $ouder],
            'training-aanmelding-goedgekeurd' => fn () => [new Notifications\TrainingAanmeldingBeoordeeld($w['losseAanmelding'], true, null, $w['rekening']), $ouder],
            'training-aanmelding-afgewezen' => fn () => [new Notifications\TrainingAanmeldingBeoordeeld($w['losseAanmelding'], false, 'De groep zit vol voor deze leeftijd.', null), $ouder],
            'afmelding-ontvangen' => fn () => [new Notifications\AfmeldingOntvangen($w['training'], $w['speler'], 'Sem is ziek'), $eigenaar],
            'betaling-herinnering' => fn () => [new Notifications\BetalingHerinnering($w['rekening'], 5), $ouder],
            'betaling-mislukt' => fn () => [new Notifications\BetalingMislukt($w['rekening'], 3, 3), $ouder],
            'betaling-ontvangen' => fn () => [new Notifications\BetalingOntvangen($w['rekening']), $ouder],
            'incasso-aankondiging' => fn () => [new Notifications\IncassoAankondiging($w['rekening'], now()->addDays(14)), $ouder],
            'nieuw-rapport' => fn () => [new Notifications\NieuwRapport($w['rapport'], $w['speler'], 78, 3), $ouder],
            'doel-behaald' => fn () => [new Notifications\DoelBehaald($w['doel'], $w['speler']), $ouder],
            'maandelijkse-update' => fn () => [new Notifications\MaandelijkseUpdate($w['speler'], $this->digest()), $ouder],
            'nieuwe-mededeling' => fn () => [new Notifications\NieuweMededeling($w['mededeling']), $ouder],
            'verjaardag' => fn () => [new Notifications\Verjaardag($w['speler'], 12), $ouder],
            // Deze twee komen uit Laravel zelf, maar horen er net zo goed bij:
            // wachtwoord vergeten is de eerste mail die een schooleigenaar ziet.
            'wachtwoord-vergeten' => fn () => [new ResetPassword('voorbeeld-token'), $ouder],
            'email-bevestigen' => fn () => [new VerifyEmail, $ouder],
        ];
    }

    /** @return array<string, mixed> */
    protected function digest(): array
    {
        return [
            'period' => 'september 2026',
            'reports' => 2,
            'attended' => 4,
            'delta' => 3,
            'highlight' => ['label' => 'Reflexen', 'now' => 78],
            'goals' => [['status' => 'working', 'label' => 'Uitkomen', 'target' => 80, 'on_track' => true]],
            'nextTraining' => ['date' => 'woensdag 7 oktober', 'time' => '18:00', 'location' => 'Sportpark De Hoge Bomen'],
        ];
    }

    /** @param  list<array{naam: string, onderwerp: string|null, bestand: string}>  $mails */
    protected function index(array $mails): string
    {
        $rijen = collect($mails)->map(fn (array $m) => sprintf(
            '<li><a href="%s">%s</a><span>%s</span></li>',
            e($m['bestand']),
            e($m['naam']),
            e((string) $m['onderwerp']),
        ))->implode("\n");

        return <<<HTML
        <!doctype html>
        <html lang="nl"><head><meta charset="utf-8"><title>Mailoverzicht</title>
        <style>
        body{font:16px/1.6 Inter,system-ui,sans-serif;background:#f1f3f6;color:#0f172a;margin:0;padding:32px}
        h1{font-size:22px}ul{list-style:none;padding:0;max-width:720px}
        li{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px;margin-bottom:10px}
        a{display:block;font-weight:600;color:#12813d;text-decoration:none}
        span{display:block;color:#5a677d;font-size:14px}
        </style></head><body>
        <h1>Alle e-mails van PlayerPath</h1>
        <ul>
        {$rijen}
        </ul></body></html>
        HTML;
    }
}
