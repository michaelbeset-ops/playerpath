<?php

namespace App\Support\Exports;

use App\Models\Player;
use Illuminate\Support\Carbon;

class PlayersExport implements Export, FormattedExport
{
    public function key(): string
    {
        return 'players';
    }

    public function title(): string
    {
        return 'Spelers';
    }

    public function description(): string
    {
        return 'Alle spelers met positie, leeftijd, groepen, ouders en de huidige rating.';
    }

    public function supportsDateRange(): bool
    {
        return false;
    }

    public function headings(): array
    {
        return [
            'Voornaam', 'Achternaam', 'Geboortedatum', 'Leeftijd', 'Positie', 'Actief',
            'Groepen', 'Ouders', 'E-mail ouders', 'Overall rating', 'Aantal rapporten', 'Laatste rapport',
        ];
    }

    public function types(): array
    {
        return ['text', 'text', 'date', 'int', 'text', 'text', 'text', 'text', 'text', 'int', 'int', 'date'];
    }

    public function totals(): array
    {
        return [];
    }

    public function rows(array $filters): iterable
    {
        $spelers = Player::query()
            ->with(['groups', 'guardians'])
            ->withCount('reports')
            ->withMax('reports', 'reported_on')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        foreach ($spelers as $speler) {
            yield [
                $speler->first_name,
                $speler->last_name,
                $speler->date_of_birth->format('d-m-Y'),
                $speler->age,
                $speler->position->label(),
                $speler->is_active ? 'Ja' : 'Nee',
                $speler->groups->pluck('name')->implode(', '),
                $speler->guardians->pluck('name')->implode(', '),
                $speler->guardians->pluck('email')->implode(', '),
                $speler->overall_rating,
                $speler->reports_count,
                $speler->reports_max_reported_on
                    ? Carbon::parse($speler->reports_max_reported_on)->format('d-m-Y')
                    : null,
            ];
        }
    }
}
