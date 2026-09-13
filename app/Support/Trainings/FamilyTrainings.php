<?php

namespace App\Support\Trainings;

use App\Enums\AttendanceStatus;
use App\Enums\PaymentStatus;
use App\Models\Player;
use App\Models\Training;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Collection;

/**
 * De trainingen van een gezin, in drie stapels: komend, inschrijven, geweest.
 *
 * Een ouder komt met drie vragen, en dit zijn precies de drie tabbladen:
 * wanneer moet mijn kind ergens zijn, waar kan het nog bij, en hoe ging het.
 * Alles noemt bij welk kind het hoort; twee kinderen is het gewone geval.
 *
 * Per kind per training is er één woord: `group` (zit in de groep, via een
 * abonnement of de indeling van de school), `confirmed`, `requested` of
 * `waitlisted` (los aangemeld), of niets - en dan kan het misschien nog
 * inschrijven. Of dat kan beslist de training zelf (acceptsPlayer), hier
 * wordt alleen gekeken.
 */
class FamilyTrainings
{
    /** Hoe ver vooruit een ouder inschrijfbare trainingen te zien krijgt. */
    public const WEKEN_VOORUIT = 8;

    public function __construct(protected VisibleTrainings $visible) {}

    /**
     * @return array{children: list<array<string, mixed>>, upcoming: list<array<string, mixed>>, enrollable: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    /**
     * @param  list<int>|null  $kindIds  standaard de eigen kinderen; het ouderscherm
     *                                   voor de eigenaar geeft de voorbeeldspelers mee
     */
    public function for(User $user, ?array $kindIds = null): array
    {
        $kinderen = Player::whereIn('id', $kindIds ?? $user->visiblePlayerIds())->with('groups')->orderBy('first_name')->get();
        $ids = $kinderen->pluck('id')->all();

        // Een ouder ziet via VisibleTrainings al alleen de trainingen van zijn
        // kinderen. Kijkt de eigenaar mee (het voorbeeld voor de rondleiding),
        // dan ziet hij álles; dan begrenzen we hier op wat déze kinderen
        // aangaat, anders staat de open training al bij "komend" en nooit bij
        // "inschrijven".
        $basis = fn () => $kindIds === null
            ? $this->visible->query($user)
            : Training::query()->with('group')->where(fn ($q) => $q
                ->whereIn('group_id', $kinderen->flatMap(fn (Player $k) => $k->groups->pluck('id'))->unique()->all())
                ->orWhereHas('enrollments', fn ($e) => $e->whereIn('player_id', $ids)));

        $komend = $basis()
            ->with(['trainers', 'attendances' => fn ($q) => $q->whereIn('player_id', $ids), 'enrollments' => fn ($q) => $q->whereIn('player_id', $ids)->with('payment')])
            ->upcoming()
            ->limit(50)
            ->get();

        $geweest = $basis()
            ->with(['trainers', 'attendances' => fn ($q) => $q->whereIn('player_id', $ids)])
            ->past()
            ->limit(20)
            ->get();

        // Waar je nog bij kunt: open trainingen die niet al in de lijst staan
        // en waar minstens één kind in past.
        $inschrijfbaar = Training::query()
            ->with(['group', 'trainers', 'enrollments' => fn ($q) => $q->whereIn('player_id', $ids)])
            ->where('open_enrollment', true)
            ->whereNull('cancelled_at')
            ->where('starts_at', '>=', now())
            ->where('starts_at', '<=', now()->addWeeks(self::WEKEN_VOORUIT))
            ->whereNotIn('id', $komend->pluck('id'))
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Training $t) => $kinderen->contains(fn (Player $k) => $this->status($t, $k) === null && $t->acceptsPlayer($k)));

        return [
            'children' => $kinderen->map(fn (Player $k) => ['id' => $k->id, 'first_name' => $k->first_name])->values()->all(),
            'upcoming' => $komend->map(fn (Training $t) => $this->rij($t, $kinderen))->values()->all(),
            'enrollable' => $inschrijfbaar->map(fn (Training $t) => $this->rij($t, $kinderen))->values()->all(),
            'past' => $geweest->map(fn (Training $t) => $this->rij($t, $kinderen))->values()->all(),
        ];
    }

    /**
     * Wat een kind bij deze training is: in de groep, los aangemeld, of niets.
     */
    public function status(Training $training, Player $kind): ?string
    {
        if ($training->group_id !== null && $kind->groups->contains('id', $training->group_id)) {
            return 'group';
        }

        if ($training->group_id === null && $training->slot?->player_id === $kind->id) {
            return 'group';
        }

        $aanmelding = $training->enrollments->firstWhere('player_id', $kind->id);

        return $aanmelding?->status->isActive() ? $aanmelding->status->value : null;
    }

    /**
     * @param  Collection<int, Player>  $kinderen
     * @return array<string, mixed>
     */
    public function rij(Training $training, Collection $kinderen): array
    {
        $aanwezigheid = $training->attendances->keyBy('player_id');

        return [
            'id' => $training->id,
            'group' => $training->label(),
            'group_id' => $training->group_id,
            'is_demo' => $training->is_demo,
            'day' => $training->starts_at->format('Y-m-d'),
            'day_label' => $training->starts_at->translatedFormat('l j F'),
            'is_today' => $training->starts_at->isToday(),
            'date' => $training->starts_at->translatedFormat('l j F Y'),
            'starts_at' => $training->starts_at->format('H:i'),
            'ends_at' => $training->ends_at->format('H:i'),
            'time' => $training->starts_at->format('H:i').' - '.$training->ends_at->format('H:i'),
            'location' => $training->location,
            'trainers' => $training->trainers->pluck('name')->all(),
            'is_mine' => false,
            'has_passed' => $training->hasPassed(),
            'cancelled' => $training->isCancelled(),
            'recorded_count' => 0,
            'present_count' => 0,
            'expected_count' => 0,
            'my_registration' => $aanwezigheid->whereIn('player_id', $kinderen->pluck('id'))->first()?->registration?->value,
            // Inschrijven: wat het kost en of er plek is.
            'open' => $training->isOpenForEnrollment(),
            'price' => Money::format($training->price_cents),
            'is_free' => $training->price_cents === 0,
            'spots_left' => $training->open_enrollment ? $training->spotsLeft() : null,
            'is_full' => $training->open_enrollment && $training->isFull(),
            'requires_approval' => $training->requires_approval,
            'children' => $kinderen->map(function (Player $kind) use ($training, $aanwezigheid) {
                $status = $this->status($training, $kind);
                $aanmelding = $training->enrollments->firstWhere('player_id', $kind->id);
                $betaling = $aanmelding?->payment;

                return [
                    'id' => $kind->id,
                    'first_name' => $kind->first_name,
                    'status' => $status,
                    'status_label' => match ($status) {
                        'group' => null,
                        null => null,
                        default => $aanmelding?->status->label(),
                    },
                    'enrollable' => $status === null && $training->isOpenForEnrollment() && $training->acceptsPlayer($kind),
                    'registration' => $aanwezigheid->get($kind->id)?->registration?->value,
                    'attendance' => $aanwezigheid->get($kind->id)?->status?->value,
                    'attendance_label' => match ($aanwezigheid->get($kind->id)?->status) {
                        AttendanceStatus::Present => 'was erbij',
                        AttendanceStatus::Absent => 'was er niet',
                        default => 'niet afgevinkt',
                    },
                    // Contant te voldoen bij de training: geen achterstand.
                    'cash_due' => $betaling !== null && $betaling->status === PaymentStatus::Open && $aanmelding->paysCash(),
                    'payment_open' => $betaling !== null && $betaling->status === PaymentStatus::Open && ! $aanmelding->paysCash(),
                ];
            })->values()->all(),
        ];
    }
}
