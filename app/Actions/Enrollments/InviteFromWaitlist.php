<?php

namespace App\Actions\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ParticipationStatus;
use App\Enums\PaymentStatus;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\WaitlistInvitation;
use App\Notifications\PlekVrijgekomen;
use App\Notifications\UitnodigingVerlopen;
use App\Support\Enrollment\EnrollmentSettings;
use App\Support\Enrollment\OrderWriter;
use App\Support\Payments\PaymentLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * De wachtlijst: uitnodigen, laten verlopen, en de volgende laten doorschuiven.
 *
 * Komt er plek, dan nodigt de school iemand uit (of, na een verlopen
 * uitnodiging, doet de app dat zelf voor de volgende in de rij). De ouder
 * krijgt een betaallink met een **tijdslimiet**; op de wachtlijst stond niets
 * open, dus de order ontstaat nu pas. Betaalt hij op tijd, dan is de plek van
 * hem (SettleOrder bevestigt). Verloopt de limiet, dan vervalt de uitnodiging,
 * vervalt de plek, en schuift de volgende door.
 *
 * De school kiest wie er uitgenodigd wordt; alleen na een verlopen
 * uitnodiging pakt de app de eerstvolgende. Iemand overslaan omdat er al
 * gebeld is blijft daarmee mogelijk.
 */
class InviteFromWaitlist
{
    public function __construct(
        protected OrderWriter $orders,
        protected ConfirmEnrollment $bevestig,
        protected PaymentLink $link,
    ) {}

    public function handle(Enrollment $enrollment, ?User $door = null): WaitlistInvitation
    {
        if (! in_array($enrollment->status, [EnrollmentStatus::Waitlist, EnrollmentStatus::Expired], strict: true)) {
            throw new RuntimeException('Deze inschrijving staat niet op de wachtlijst.');
        }

        $aanbod = $enrollment->product;

        if ($aanbod === null || $aanbod->isFull()) {
            throw new RuntimeException('Dit aanbod zit nog vol. Maak eerst een plek vrij.');
        }

        if ($enrollment->player === null || $enrollment->guardian === null || $enrollment->paymentOption === null) {
            throw new RuntimeException('Bij deze inschrijving horen geen speler, ouder of betaalvorm meer.');
        }

        $instellingen = EnrollmentSettings::for($enrollment->school);
        $dagen = (int) ($instellingen->get('capacity')['invitation_days'] ?? 3);
        $verloopt = CarbonImmutable::now()->addDays($dagen)->endOfDay();

        $uitnodiging = DB::transaction(function () use ($enrollment, $aanbod, $instellingen, $door, $verloopt) {
            // Verlopen en opnieuw uitgenodigd: eerst terug naar de wachtlijst,
            // zodat de rest van de machine dezelfde weg loopt.
            if ($enrollment->status === EnrollmentStatus::Expired) {
                $enrollment->transitionTo(EnrollmentStatus::Waitlist);
            }

            // De order ontstaat nu pas (betalen bij plaatsing), tenzij hij er
            // van een eerdere uitnodiging al is.
            if ($enrollment->order === null) {
                $this->orders->write($instellingen, [[
                    'product' => $aanbod,
                    'option' => $enrollment->paymentOption,
                    'child_name' => $enrollment->first_name,
                    'player_id' => $enrollment->player_id,
                    'enrollment' => $enrollment,
                ]], $enrollment->guardian);
                $enrollment->refresh();
            } elseif ($enrollment->order->status === OrderStatus::Open && $enrollment->order->payments()->outstanding()->doesntExist()) {
                // Opnieuw uitgenodigd na een verlopen plek: de oude rekeningen
                // zijn vervallen, dus er komt opnieuw een rekening voor wat
                // er nog openstaat.
                $order = $enrollment->order;
                $betaald = (int) $order->payments()->get()->filter(fn (Payment $p) => $p->status->countsAsRevenue())->sum('amount_cents');

                if ($order->total_cents - $betaald > 0) {
                    $this->bevestig->maakRekeningen($order, $betaald > 0 ? $order->total_cents - $betaald : null);
                }
            }

            $enrollment->forceFill(['handled_by_id' => $door?->id, 'handled_at' => now()])->save();

            $uitnodiging = WaitlistInvitation::create([
                'enrollment_id' => $enrollment->id,
                'sent_at' => now(),
                'expires_at' => $verloopt,
            ]);

            // Open zetten: rekeningen, wacht op betaling - of meteen rond bij nul.
            $this->bevestig->openOrConfirm($enrollment, $enrollment->order);

            return $uitnodiging;
        });

        $enrollment->refresh();

        if ($enrollment->status === EnrollmentStatus::AwaitingPayment) {
            $rekening = $enrollment->order?->payments()->outstanding()->orderBy('due_on')->first();

            $enrollment->guardian->notify(new PlekVrijgekomen(
                $aanbod,
                $enrollment->player,
                $rekening !== null ? $this->link->for($rekening, $verloopt) : null,
                $verloopt,
            ));
        }

        return $uitnodiging;
    }

    /**
     * Verlopen uitnodigingen afhandelen: de plek vervalt, en de volgende in de
     * rij krijgt hem. Wordt dagelijks aangeroepen door enrollments:lifecycle.
     *
     * @return int het aantal verlopen uitnodigingen
     */
    public function expire(?CarbonImmutable $op = null): int
    {
        $op ??= CarbonImmutable::now();
        $aantal = 0;

        $verlopen = WaitlistInvitation::query()
            ->whereNull('accepted_at')
            ->whereNull('expired_at')
            ->where('expires_at', '<', $op)
            ->with('enrollment.product')
            ->get();

        foreach ($verlopen as $uitnodiging) {
            $inschrijving = $uitnodiging->enrollment;

            $uitnodiging->forceFill(['expired_at' => $op])->save();

            // Al betaald (SettleOrder bevestigde): dan is er niets verlopen.
            if ($inschrijving === null || ! in_array($inschrijving->status, [EnrollmentStatus::AwaitingPayment, EnrollmentStatus::PaymentFailed], strict: true)) {
                continue;
            }

            DB::transaction(function () use ($inschrijving) {
                $inschrijving->order?->payments()->outstanding()->get()
                    ->filter(fn (Payment $p) => $p->canTransitionTo(PaymentStatus::Cancelled))
                    ->each(fn (Payment $p) => $p->transitionTo(PaymentStatus::Cancelled));
                $inschrijving->player?->participations()->where('product_id', $inschrijving->product_id)
                    ->update(['status' => ParticipationStatus::Cancelled->value]);
                $inschrijving->transitionTo(EnrollmentStatus::Expired);
            });

            $inschrijving->guardian?->notify(new UitnodigingVerlopen($inschrijving));
            $aantal++;

            // En de volgende in de rij.
            if ($inschrijving->product !== null) {
                $this->inviteNext($inschrijving->product);
            }
        }

        return $aantal;
    }

    /** De eerstvolgende op de wachtlijst van dit aanbod uitnodigen, als er plek is. */
    public function inviteNext(Product $product): ?WaitlistInvitation
    {
        if ($product->refresh()->isFull()) {
            return null;
        }

        $volgende = Enrollment::query()
            ->where('product_id', $product->id)
            ->where('status', EnrollmentStatus::Waitlist->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($volgende === null) {
            return null;
        }

        try {
            return $this->handle($volgende);
        } catch (RuntimeException) {
            return null;
        }
    }
}
