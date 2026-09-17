<?php

namespace App\Actions\Enrollments;

use App\Actions\Offerings\JoinOffering;
use App\Enums\EnrollmentStatus;
use App\Enums\ParticipationStatus;
use App\Enums\PlayerPosition;
use App\Enums\ProductAudience;
use App\Enums\Role;
use App\Models\Consent;
use App\Models\ConsentDocument;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\PaymentOption;
use App\Models\Player;
use App\Models\Product;
use App\Models\User;
use App\Notifications\InschrijvingOntvangen;
use App\Notifications\NieuweInschrijving;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Enrollment\OrderWriter;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Een aanmelding vanaf het openbare formulier vastleggen.
 *
 * In één transactie: het ouderaccount (of het bestaande), per kind een speler
 * en een inschrijving, één order met de regels uit OrderBuilder, en de
 * gegeven toestemmingen met de documentversie van dit moment.
 *
 * De status volgt de instellingen van de school:
 *
 * - vol aanbod → **wachtlijst**, zonder order: op de wachtlijst staat niets open;
 * - handmatig goedkeuren → **wacht op goedkeuring**; pas daarna kan er betaald worden;
 * - automatisch → **wacht op betaling**, of meteen **bevestigd** als er niets te betalen is.
 *
 * De speler ontstaat hier al, óók bij wachten: anders kan de school niemand
 * bereiken, en de ouder kan meteen inloggen en zijn kind zien. In een groep
 * komt het kind pas bij de bevestiging (ConfirmEnrollment).
 */
class SubmitEnrollment
{
    public function __construct(
        protected OrderWriter $orders,
        protected ConfirmEnrollment $bevestig,
        protected JoinOffering $deelname,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, password?: string|null}  $ouderGegevens
     * @param  list<array{
     *     player_id?: int|null, first_name: string, last_name: string, date_of_birth: string, position: string,
     *     product_id: int, payment_option_id: int, details?: array<string, mixed>
     * }>  $kinderen
     * @param  list<string>  $toestemmingen  de sleutels die zijn aangevinkt
     * @return array{order: Order|null, enrollments: list<Enrollment>, guardian: User, new_account: bool}
     */
    public function handle(
        EnrollmentSettings $settings,
        ?User $ingelogd,
        array $ouderGegevens,
        array $kinderen,
        array $toestemmingen,
        ?string $code = null,
        ?string $note = null,
        ?string $ip = null,
    ): array {
        $nieuwAccount = false;

        $uitkomst = DB::transaction(function () use ($settings, $ingelogd, $ouderGegevens, $kinderen, $toestemmingen, $code, $note, $ip, &$nieuwAccount) {
            $ouder = $ingelogd ?? $this->ouder($ouderGegevens, $nieuwAccount);

            $documenten = ConsentDocument::query()->get()->keyBy('key');
            $regels = [];
            $inschrijvingen = [];
            $opWachtlijst = false;

            foreach ($kinderen as $kind) {
                $aanbod = Product::findOrFail($kind['product_id']);
                $optie = PaymentOption::where('product_id', $aanbod->id)->findOrFail($kind['payment_option_id']);
                $vol = $aanbod->isFull();

                $speler = $this->speler($ouder, $kind, $aanbod);

                $inschrijving = Enrollment::create([
                    'first_name' => $speler->first_name,
                    'last_name' => $speler->last_name,
                    'date_of_birth' => $speler->date_of_birth,
                    'position' => $speler->position,
                    'guardian_name' => $ouder->name,
                    'guardian_email' => $ouder->email,
                    'guardian_phone' => $ouderGegevens['phone'] ?? null,
                    'relationship' => $ouderGegevens['relationship'] ?? null,
                    'guardian_user_id' => $ouder->id,
                    'product_id' => $aanbod->id,
                    'payment_option_id' => $optie->id,
                    'payment_method' => $ouderGegevens['payment_method'] ?? null,
                    'waitlist' => $vol,
                    'note' => $note,
                    'details' => $kind['details'] ?? null,
                ]);

                $inschrijving->forceFill(['player_id' => $speler->id])->save();

                if ($vol) {
                    $opWachtlijst = true;
                    $inschrijving->transitionTo(EnrollmentStatus::Waitlist);
                    // De plek op de wachtlijst zelf, zodat het aanbodbeheer hem ziet.
                    $this->deelname->handle($aanbod, $speler, status: ParticipationStatus::Waitlist, enrollmentId: $inschrijving->id);
                } else {
                    $regels[] = ['product' => $aanbod, 'option' => $optie, 'child_name' => $speler->first_name, 'player_id' => $speler->id, 'enrollment' => $inschrijving];
                }

                $inschrijvingen[] = $inschrijving;

                // Toestemmingen: per kind, met de versie van nu.
                foreach ($toestemmingen as $sleutel) {
                    // Nog nooit door de school aangepast: dan is de standaardtekst
                    // versie 1, en die leggen we nu vast zodat de toestemming ergens
                    // aan hangt.
                    $document = $documenten->get($sleutel);

                    if ($document === null && isset(ConsentDocument::SOORTEN[$sleutel])) {
                        $standaard = ConsentDocument::SOORTEN[$sleutel];
                        $document = ConsentDocument::put($sleutel, $standaard['title'], $standaard['body'], $sleutel === 'avg');
                        $documenten->put($sleutel, $document);
                    }

                    if ($document === null) {
                        continue;
                    }

                    Consent::create([
                        'user_id' => $ouder->id,
                        'player_id' => $speler->id,
                        'consent_document_id' => $document->id,
                        'version' => $document->version,
                        'accepted_at' => now(),
                        'ip_address' => $ip,
                    ]);
                }
            }

            $order = null;

            if ($regels !== []) {
                $order = $this->orders->write($settings, $regels, $ouder, $code, $note);

                if ($settings->approvesManually()) {
                    foreach ($regels as $regel) {
                        $regel['enrollment']->transitionTo(EnrollmentStatus::AwaitingApproval);
                    }
                } else {
                    // Automatisch: de betaling bevestigt, of er valt niets te betalen.
                    foreach ($regels as $regel) {
                        $this->bevestig->openOrConfirm($regel['enrollment'], $order);
                    }
                }
            }

            return ['order' => $order, 'enrollments' => $inschrijvingen, 'guardian' => $ouder, 'waitlist' => $opWachtlijst];
        });

        // Pas na de transactie: een mislukte opslag mag nooit alsnog mails opleveren.
        $eigenaren = User::where('school_id', $uitkomst['guardian']->school_id)->role(Role::Eigenaar->value)->get();
        Notification::send($eigenaren, new NieuweInschrijving($uitkomst['enrollments'][0]));

        $uitkomst['guardian']->notify(new InschrijvingOntvangen($uitkomst['enrollments'], $uitkomst['order'], $nieuwAccount));

        return [...$uitkomst, 'new_account' => $nieuwAccount];
    }

    /** @param  array{name: string, email: string, password?: string|null}  $gegevens */
    protected function ouder(array $gegevens, bool &$nieuwAccount): User
    {
        $bestaand = User::where('email', $gegevens['email'])->first();

        if ($bestaand !== null) {
            // Zonder inloggen mag je geen bestaand account gebruiken: anders
            // schrijft iemand een kind in op andermans naam.
            // De tekst zegt niet letterlijk dat het adres bekend is.
            throw new RuntimeException('Log eerst in met dit e-mailadres, of gebruik een ander adres.');
        }

        $ouder = User::create([
            'school_id' => app(Tenancy::class)->id(),
            'name' => $gegevens['name'],
            'email' => $gegevens['email'],
            'password' => $gegevens['password'],
            'email_verified_at' => now(),
        ]);
        $ouder->assignRole(Role::Ouder->value);

        $nieuwAccount = true;

        return $ouder;
    }

    /**
     * Een bestaand kind van deze ouder, of een nieuw profiel.
     *
     * Vraagt de school niet naar de positie (veld uit), dan komt er null
     * binnen. Een speler heeft er wel een nodig; die volgt dan uit de
     * doelgroep van het aanbod, en anders is het keeper - zoals het formulier
     * vroeger voorkoos. De school kan hem op de spelerspagina aanpassen.
     *
     * @param  array{player_id?: int|null, first_name: string, last_name: string, date_of_birth: string, position?: string|null}  $kind
     */
    protected function speler(User $ouder, array $kind, Product $aanbod): Player
    {
        if (! empty($kind['player_id'])) {
            $speler = $ouder->children()->findOrFail($kind['player_id']);

            return $speler;
        }

        $speler = Player::create([
            'first_name' => $kind['first_name'],
            'last_name' => $kind['last_name'],
            'date_of_birth' => $kind['date_of_birth'],
            'position' => ! empty($kind['position'])
                ? $kind['position']
                : ($aanbod->audience === ProductAudience::Field ? PlayerPosition::Field : PlayerPosition::Keeper)->value,
            'is_active' => true,
        ]);

        $speler->guardians()->syncWithoutDetaching([$ouder->id => ['relationship' => $kind['relationship'] ?? null]]);

        return $speler;
    }
}
