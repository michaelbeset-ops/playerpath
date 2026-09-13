<?php

namespace App\Actions\Communication;

use App\Models\Announcement;
use App\Models\Group;
use App\Models\Training;
use App\Models\User;
use App\Notifications\NieuweMededeling;
use App\Support\Communication\AnnouncementAudience;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Een mededeling vastleggen en versturen.
 *
 * Eén plek, want dit gebeurt vanuit twee kanten: de eigenaar die zelf iets
 * schrijft, en een training die wordt afgezegd.
 *
 * Versturen gebeurt ná de transactie. Een mislukte opslag mag nooit alsnog
 * honderd mails opleveren - en die krijg je niet terug.
 */
class SendAnnouncement
{
    public function __construct(protected AnnouncementAudience $audience) {}

    public function handle(
        User $auteur,
        string $titel,
        string $tekst,
        ?Group $group = null,
        ?Training $training = null,
    ): Announcement {
        $ontvangers = $this->audience->forGroup($group);

        $announcement = DB::transaction(function () use ($auteur, $titel, $tekst, $group, $training, $ontvangers) {
            $announcement = Announcement::create([
                'author_id' => $auteur->id,
                'group_id' => $group?->id,
                'training_id' => $training?->id,
                'title' => $titel,
                'body' => $tekst,
            ]);

            // Vastleggen wie het toen kreeg: wie later vertrekt heeft het
            // bericht wél gehad, en dat hoort de geschiedenis te laten zien.
            $announcement->forceFill(['recipients_count' => $ontvangers->count()])->save();

            return $announcement;
        });

        if ($ontvangers->isNotEmpty()) {
            Notification::send($ontvangers, new NieuweMededeling($announcement));
        }

        return $announcement;
    }
}
