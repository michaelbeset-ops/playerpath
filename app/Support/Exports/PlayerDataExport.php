<?php

namespace App\Support\Exports;

use App\Models\Attendance;
use App\Models\Goal;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Report;
use App\Models\Subscription;

/**
 * Alles wat de school van één speler bewaart, in één bestand.
 *
 * Dit is het inzageverzoek uit de AVG: een ouder vraagt welke gegevens er over
 * zijn kind zijn vastgelegd, en krijgt daar een leesbaar antwoord op.
 *
 * Deze export staat bewust niet in ExportRegistry. Het register is voor
 * schoolbrede overzichten die je uit een lijst kiest; deze hoort bij precies
 * één speler en wordt daarom rechtstreeks aangemaakt.
 */
final class PlayerDataExport implements WorkbookExport
{
    public function __construct(private readonly Player $player) {}

    public function key(): string
    {
        return 'speler-gegevens';
    }

    public function title(): string
    {
        return 'Gegevens '.$this->player->full_name;
    }

    public function description(): string
    {
        return 'Alle gegevens die de school over deze speler bewaart.';
    }

    public function supportsDateRange(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Gegeven', 'Waarde'];
    }

    /**
     * Het hoofdtabblad is het profiel zelf; dat is ook wat een CSV krijgt.
     *
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return iterable<int, list<scalar|null>>
     */
    public function rows(array $filters): iterable
    {
        $speler = $this->player;

        return [
            ['Voornaam', $speler->first_name],
            ['Achternaam', $speler->last_name],
            ['Geboortedatum', $speler->date_of_birth->format('d-m-Y')],
            ['Leeftijd', $speler->age],
            ['Positie', $speler->position->label()],
            ['Actief', $speler->is_active ? 'Ja' : 'Nee'],
            ['Niet-actief sinds', $speler->deactivated_at?->format('d-m-Y')],
            ['Eigen inlogaccount', $speler->user?->email],
            ['Groepen', $speler->groups->pluck('name')->implode(', ')],
            ['Kaartcijfer', $speler->overall_rating],
            ['Kaart bijgewerkt op', $speler->rated_at?->format('d-m-Y')],
            ['Kaart gedeeld', $speler->shared_at ? 'Ja, sinds '.$speler->shared_at->format('d-m-Y') : 'Nee'],
            ['Aangemaakt op', $speler->created_at?->format('d-m-Y')],
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return list<Sheet>
     */
    public function sheets(array $filters): array
    {
        return [
            new Sheet('Speler', $this->headings(), $this->rows($filters)),
            new Sheet('Ouders', ['Naam', 'E-mail', 'Relatie'], $this->ouders()),
            new Sheet('Rapporten', ['Datum', 'Trainer', 'Toelichting'], $this->rapporten()),
            new Sheet('Rapportcijfers', ['Datum', 'Categorie', 'Cijfer'], $this->cijfers()),
            new Sheet('Aanwezigheid', ['Datum', 'Groep', 'Locatie', 'Aanmelding', 'Aanwezigheid'], $this->aanwezigheid()),
            new Sheet('Doelen', ['Categorie', 'Start', 'Streef', 'Gesteld op', 'Einddatum', 'Status', 'Behaald op', 'Toelichting'], $this->doelen()),
            new Sheet('Abonnementen', ['Tarief', 'Bedrag (EUR)', 'Interval', 'Status', 'Start', 'Einde'], $this->abonnementen()),
            new Sheet('Betalingen', ['Omschrijving', 'Bedrag (EUR)', 'Status', 'Vervaldatum', 'Betaald op'], $this->betalingen()),
        ];
    }

    /** @return iterable<int, list<scalar|null>> */
    private function ouders(): iterable
    {
        foreach ($this->player->guardians as $ouder) {
            yield [$ouder->name, $ouder->email, $ouder->pivot->relationship];
        }
    }

    /** @return iterable<int, list<scalar|null>> */
    private function rapporten(): iterable
    {
        foreach ($this->player->reports()->with('trainer')->orderBy('reported_on')->get() as $rapport) {
            /** @var Report $rapport */
            yield [
                $rapport->reported_on->format('d-m-Y'),
                $rapport->trainer?->name,
                $rapport->note,
            ];
        }
    }

    /** @return iterable<int, list<scalar|null>> */
    private function cijfers(): iterable
    {
        $rapporten = $this->player->reports()->with('scores')->orderBy('reported_on')->get();

        foreach ($rapporten as $rapport) {
            foreach ($rapport->scores as $score) {
                yield [
                    $rapport->reported_on->format('d-m-Y'),
                    $score->category->label(),
                    $score->score,
                ];
            }
        }
    }

    /** @return iterable<int, list<scalar|null>> */
    private function aanwezigheid(): iterable
    {
        $rijen = Attendance::query()
            ->where('player_id', $this->player->id)
            ->with('training.group')
            ->get()
            ->sortBy(fn (Attendance $a) => $a->training?->starts_at);

        foreach ($rijen as $aanwezigheid) {
            yield [
                $aanwezigheid->training?->starts_at->format('d-m-Y H:i'),
                $aanwezigheid->training?->group?->name,
                $aanwezigheid->training?->location,
                $aanwezigheid->registration?->label(),
                $aanwezigheid->status?->label(),
            ];
        }
    }

    /** @return iterable<int, list<scalar|null>> */
    private function doelen(): iterable
    {
        foreach (Goal::query()->where('player_id', $this->player->id)->orderBy('starts_on')->get() as $doel) {
            yield [
                $doel->label(),
                $doel->start_rating,
                $doel->target_rating,
                $doel->starts_on->format('d-m-Y'),
                $doel->due_on->format('d-m-Y'),
                $doel->status->label(),
                $doel->achieved_at?->format('d-m-Y'),
                $doel->note,
            ];
        }
    }

    /** @return iterable<int, list<scalar|null>> */
    private function abonnementen(): iterable
    {
        $rijen = Subscription::query()
            ->where('player_id', $this->player->id)
            ->with('product')
            ->orderBy('starts_on')
            ->get();

        foreach ($rijen as $abonnement) {
            yield [
                $abonnement->product?->name,
                // Centen worden hier pas een getal in euro's, zodat Excel kan rekenen.
                $abonnement->amount_cents / 100,
                $abonnement->interval->label(),
                $abonnement->status->label(),
                $abonnement->starts_on->format('d-m-Y'),
                $abonnement->ends_on?->format('d-m-Y'),
            ];
        }
    }

    /** @return iterable<int, list<scalar|null>> */
    private function betalingen(): iterable
    {
        foreach (Payment::query()->where('player_id', $this->player->id)->orderBy('due_on')->get() as $betaling) {
            yield [
                $betaling->description,
                $betaling->amount_cents / 100,
                $betaling->status->label(),
                $betaling->due_on->format('d-m-Y'),
                $betaling->paid_at?->format('d-m-Y'),
            ];
        }
    }
}
