<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Enrollments\CancelEnrollment;
use App\Actions\Subscriptions\PlanCancellation;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Subscription;
use App\Support\Money\Money;
use App\Support\Status\TransitionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Wat een ouder zelf met zijn inschrijvingen kan: annuleren vóór de start
 * (volgens het restitutiebeleid) en een abonnement opzeggen (met de
 * opzegtermijn). Alleen voor de eigen kinderen; zie de policies.
 */
class FamilyEnrollmentController extends Controller
{
    public function cancel(Request $request, Enrollment $enrollment, CancelEnrollment $annuleer): RedirectResponse
    {
        $this->authorize('cancel', $enrollment);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:300']], [], ['reason' => 'De reden']);

        try {
            $annuleer->handle($enrollment, $request->user(), $validated['reason'] ?? null);
        } catch (TransitionException $e) {
            return back()->withErrors(['enrollment' => $e->getMessage()]);
        }

        $restitutie = (int) ($enrollment->refresh()->refund_cents ?? 0);

        return back()->with('status', $restitutie > 0
            ? "De inschrijving van {$enrollment->first_name} is geannuleerd. Je krijgt ".Money::format($restitutie).' terug; de school regelt dat met je.'
            : "De inschrijving van {$enrollment->first_name} is geannuleerd.");
    }

    public function cancelSubscription(Request $request, Subscription $subscription, PlanCancellation $opzeggen): RedirectResponse
    {
        $this->authorize('cancel', $subscription);

        if (! $subscription->status->canTransitionTo(SubscriptionStatus::CancellationPlanned)) {
            return back()->withErrors(['subscription' => 'Dit abonnement kan niet meer opgezegd worden.']);
        }

        $opzeggen->handle($subscription);

        return back()->with('status', 'Opgezegd. Het abonnement loopt door tot '.$subscription->refresh()->ends_on->format('d-m-Y').'.');
    }
}
