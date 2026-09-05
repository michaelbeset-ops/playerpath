<?php

namespace App\Support\PlayerCard;

use App\Enums\AttendanceStatus;
use App\Models\Player;

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
    public function level(?int $overall): array
    {
        return match (true) {
            $overall === null => ['key' => 'geen', 'label' => 'Nog geen niveau', 'description' => 'Vanaf je eerste rapport'],
            $overall >= 85 => ['key' => 'elite', 'label' => 'Elite', 'description' => '85 of hoger'],
            $overall >= 75 => ['key' => 'goud', 'label' => 'Goud', 'description' => '75 tot 85'],
            $overall >= 60 => ['key' => 'zilver', 'label' => 'Zilver', 'description' => '60 tot 75'],
            default => ['key' => 'brons', 'label' => 'Brons', 'description' => 'Tot 60'],
        };
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
