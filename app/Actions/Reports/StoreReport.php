<?php

namespace App\Actions\Reports;

use App\Actions\Goals\EvaluateGoals;
use App\Models\Player;
use App\Models\Report;
use App\Models\User;
use App\Notifications\NieuwRapport;
use App\Support\PlayerCard\CalculatePlayerCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Slaat een rapport op en rekent de spelerskaart meteen door.
 *
 * Die twee horen in één transactie: een opgeslagen rapport zonder bijgewerkte
 * kaart zou betekenen dat de trainer zijn werk niet terugziet, en dat is
 * precies waar dit product op draait.
 *
 * De melding aan ouders gaat er pas ná de transactie uit. Anders zou een
 * mislukte opslag alsnog een e-mail opleveren over een rapport dat niet bestaat.
 */
class StoreReport
{
    public function __construct(protected CalculatePlayerCard $calculator, protected EvaluateGoals $goals) {}

    /**
     * @param  array<string, float>  $scores  categorie => cijfer (1-10, met een decimaal)
     */
    public function handle(Player $player, User $trainer, array $scores, ?string $note, ?string $reportedOn = null): Report
    {
        $vorigeRating = $player->overall_rating;

        $report = DB::transaction(function () use ($player, $trainer, $scores, $note, $reportedOn) {
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

        $this->meldOuders($player->refresh(), $report, $vorigeRating);

        // Doelen beoordelen na de herberekening; een gehaald doel wordt gevierd.
        $this->goals->handle($player);

        return $report;
    }

    /** De ouders én de speler zelf, als die een eigen account heeft. */
    protected function meldOuders(Player $player, Report $report, ?int $vorigeRating): void
    {
        $ontvangers = $player->guardians()->get()->all();

        if ($player->user_id !== null && $player->user) {
            $ontvangers[] = $player->user;
        }

        if ($ontvangers === []) {
            return;
        }

        $groei = ($vorigeRating !== null && $player->overall_rating !== null)
            ? $player->overall_rating - $vorigeRating
            : null;

        Notification::send(
            $ontvangers,
            new NieuwRapport($report, $player, $player->overall_rating, $groei)
        );
    }
}
