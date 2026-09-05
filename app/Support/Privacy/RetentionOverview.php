<?php

namespace App\Support\Privacy;

use App\Models\Player;
use App\Models\School;
use Illuminate\Support\Collection;

/**
 * Wie mag er weg volgens de bewaartermijn.
 *
 * Twee dingen die je niet moet omdraaien:
 *
 * 1. Dit signaleert alleen. Er wordt hier niets verwijderd en er draait geen
 *    taak die dat stiekem wel doet. Een verkeerd ingestelde termijn merk je
 *    anders pas als de rapporten al weg zijn, en die krijg je niet terug.
 * 2. Zonder ingestelde termijn is de lijst leeg. Een lege instelling betekent
 *    "we hebben er nog niet over besloten", niet "alles mag weg".
 */
class RetentionOverview
{
    /**
     * Oud-leden waarvan de termijn verstreken is, langst geleden eerst.
     *
     * De global scope zorgt dat dit alleen de eigen school is; we filteren
     * hier dus niet zelf op school_id.
     *
     * @return Collection<int, Player>
     */
    public function eligible(School $school): Collection
    {
        if ($school->retention_months === null) {
            return collect();
        }

        return Player::query()
            ->where('is_active', false)
            ->whereNotNull('deactivated_at')
            ->where('deactivated_at', '<=', now()->subMonths($school->retention_months))
            ->withCount(['reports', 'attendances'])
            ->with('guardians:id,name,email')
            ->orderBy('deactivated_at')
            ->get();
    }

    /**
     * Het scherm in één klap: de instelling, de lijst en wat er nog niet aan
     * toe is. Dat laatste voorkomt de indruk dat een leeg overzicht betekent
     * dat er geen oud-leden zijn.
     *
     * @return array<string, mixed>
     */
    public function describe(School $school): array
    {
        $verlopen = $this->eligible($school);

        $inactief = Player::query()->where('is_active', false)->count();

        return [
            'retentionMonths' => $school->retention_months,
            'inactiveCount' => $inactief,
            'eligibleCount' => $verlopen->count(),
            'waitingCount' => $inactief - $verlopen->count(),
            'players' => $verlopen->map(fn (Player $player) => [
                'id' => $player->id,
                'name' => $player->full_name,
                'deactivated_at' => $player->deactivated_at?->format('d-m-Y'),
                'months_inactive' => $player->deactivated_at
                    ? (int) $player->deactivated_at->diffInMonths(now())
                    : null,
                'reports_count' => $player->reports_count,
                'attendances_count' => $player->attendances_count,
                'guardians' => $player->guardians->map(fn ($ouder) => [
                    'name' => $ouder->name,
                    'email' => $ouder->email,
                ])->values(),
            ])->values(),
        ];
    }
}
