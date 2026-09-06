<?php

namespace App\Http\Controllers\Trainings;

use App\Actions\Products\ConsumeCredit;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Training;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * De trainer vinkt af wie er was.
 */
class AttendanceController extends Controller
{
    public function __construct(protected ConsumeCredit $credits) {}

    public function update(Request $request, Training $training, Player $player): RedirectResponse
    {
        $this->authorize('recordAttendance', $training);

        // De speler moet in de groep van deze training zitten, anders vink je
        // iemand af die er niet hoort.
        abort_unless($training->group->players()->whereKey($player->id)->exists(), 404);

        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
        ], [], ['status' => 'De aanwezigheid']);

        $status = $validated['status'] ?? null;

        $aanwezigheid = $training->attendances()->updateOrCreate(
            ['player_id' => $player->id],
            ['status' => $status],
        );

        // Een beurt gaat van de rittenkaart zodra iemand aanwezig gemeld wordt,
        // en komt terug als de trainer zich vergist. Heeft de speler geen kaart,
        // dan gebeurt hier niets: afvinken mag nooit stuklopen op de
        // administratie.
        $this->credits->sync($aanwezigheid, $status === null ? null : AttendanceStatus::from($status));

        return back();
    }
}
