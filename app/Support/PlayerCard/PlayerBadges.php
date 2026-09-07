<?php

namespace App\Support\PlayerCard;

use App\Enums\AttendanceStatus;
use App\Enums\GoalStatus;
use App\Models\Player;
use App\Support\Rating\RatingEngine;

/**
 * Mijlpalen en niveau voor de spelerskaart.
 *
 * Alles wordt **afgeleid** uit rapporten en aanwezigheid; er is geen tabel met
 * badges. Daardoor kan een badge niet los komen te staan van de werkelijkheid,
 * en kost een nieuwe badge alleen een regel hier.
 *
 * De drempels zijn bewust haalbaar: een kaart die nooit iets oplevert voelt
 * dood, en dat is precies wat dit product niet moet zijn.
 */
class PlayerBadges
{
    /** @return array{key: string, label: string, description: string} */
    /**
     * Het level van een speler.
     *
     * Sinds de rekenkern op XP, niet meer op rating: het level beloont inzet,
     * de rating zegt hoe goed. Zie Support\Rating\RatingEngine. Deze methode
     * blijft bestaan als ingang voor de kaart, zodat het niveau overal uit
     * dezelfde bron komt.
     *
     * @return array{key: string, label: string, description: string, xp: int, next: array|null, progress: int}
     */
    public function level(Player $player): array
    {
        $state = app(RatingEngine::class)->levelState($player);

        return [
            'key' => $state['key'],
            'label' => $state['label'],
            'description' => $state['next'] === null
                ? 'Het hoogste level'
                : "Nog {$state['next']['remaining']} XP tot {$state['next']['label']}",
            'xp' => $state['xp'],
            'next' => $state['next'],
            'progress' => $state['progress'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string, earned: bool}>
     */
    public function for(Player $player, PlayerProgress $progress): array
    {
        $voortgang = $progress->for($player);
        $ratings = $player->category_ratings ?? [];

        $aantalRapporten = count($voortgang['points']);
        $groei = $voortgang['overall']['delta'];

        $aanwezig = $player->attendances()
            ->where('status', AttendanceStatus::Present->value)
            ->count();

        $badges = [
            [
                'key' => 'eerste_rapport',
                'label' => 'Op de kaart',
                'description' => 'Je eerste rapport is binnen',
                'earned' => $aantalRapporten >= 1,
            ],
            [
                'key' => 'vijf_rapporten',
                'label' => 'Vaste waarde',
                'description' => 'Vijf rapporten of meer',
                'earned' => $aantalRapporten >= 5,
            ],
            [
                'key' => 'groei',
                'label' => 'In de lift',
                'description' => 'Vijf punten gegroeid sinds je eerste rapport',
                'earned' => $groei !== null && $groei >= 5,
            ],
            [
                'key' => 'sterke_groei',
                'label' => 'Grote sprong',
                'description' => 'Tien punten gegroeid sinds je eerste rapport',
                'earned' => $groei !== null && $groei >= 10,
            ],
            [
                'key' => 'uitblinker',
                'label' => 'Uitblinker',
                'description' => 'Een categorie op 85 of hoger',
                'earned' => $ratings !== [] && max($ratings) >= 85,
            ],
            [
                'key' => 'compleet',
                'label' => 'Compleet',
                'description' => 'Alle categorieën op 70 of hoger',
                'earned' => $ratings !== [] && min($ratings) >= 70,
            ],
            [
                'key' => 'doel_gehaald',
                'label' => 'Doelgericht',
                'description' => 'Een ontwikkelingsdoel gehaald',
                'earned' => $player->goals()->where('status', GoalStatus::Achieved->value)->exists(),
            ],
            [
                'key' => 'aanwezig_vijf',
                'label' => 'Altijd op tijd',
                'description' => 'Vijf trainingen aanwezig',
                'earned' => $aanwezig >= 5,
            ],
            [
                'key' => 'aanwezig_tien',
                'label' => 'Onmisbaar',
                'description' => 'Tien trainingen aanwezig',
                'earned' => $aanwezig >= 10,
            ],
        ];

        return $badges;
    }
}
