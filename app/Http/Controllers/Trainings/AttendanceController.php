<?php

namespace App\Http\Controllers\Trainings;

use App\Actions\Trainings\RecordAttendance;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\Training;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * De trainer vinkt af wie er was. Wat eraan hangt (rittenkaart, XP,
 * inzetpunten) staat in RecordAttendance.
 */
class AttendanceController extends Controller
{
    public function __construct(protected RecordAttendance $record) {}

    public function update(Request $request, Training $training, Player $player): RedirectResponse
    {
        $this->authorize('recordAttendance', $training);

        // De speler moet in de groep van deze training zitten, anders vink je
        // iemand af die er niet hoort.
        abort_unless($training->expectedPlayers()->contains('id', $player->id), 404);

        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
        ], [], ['status' => 'De aanwezigheid']);

        $status = $validated['status'] ?? null;

        $this->record->handle($training, $player, $status === null ? null : AttendanceStatus::from($status));

        return back();
    }
}
