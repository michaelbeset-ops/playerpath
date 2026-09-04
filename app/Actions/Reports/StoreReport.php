<?php

namespace App\Actions\Reports;

use App\Models\Player;
use App\Models\Report;
use App\Models\User;
use App\Support\PlayerCard\CalculatePlayerCard;
use Illuminate\Support\Facades\DB;

/**
 * Slaat een rapport op en rekent de spelerskaart meteen door.
 *
 * Die twee horen in één transactie: een opgeslagen rapport zonder bijgewerkte
 * kaart zou betekenen dat de trainer zijn werk niet terugziet, en dat is
 * precies waar dit product op draait.
 */
class StoreReport
{
    public function __construct(protected CalculatePlayerCard $calculator) {}

    /**
     * @param  array<string, int>  $scores  categorie => cijfer (1-10)
     */
    public function handle(Player $player, User $trainer, array $scores, ?string $note, ?string $reportedOn = null): Report
    {
        return DB::transaction(function () use ($player, $trainer, $scores, $note, $reportedOn) {
            $report = Report::create([
                'player_id' => $player->id,
                'trainer_id' => $trainer->id,
                'reported_on' => $reportedOn ?? now()->toDateString(),
                'note' => $note,
            ]);

            foreach ($scores as $category => $score) {
                $report->scores()->create([
                    'category' => $category,
                    'score' => $score,
                ]);
            }

            $this->calculator->refresh($player->refresh());

            return $report->load('scores');
        });
    }
}
