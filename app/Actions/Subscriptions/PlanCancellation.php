<?php

namespace App\Actions\Subscriptions;

use App\Enums\EnrollmentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Support\Enrollment\EnrollmentSettings;
use Carbon\CarbonImmutable;

/**
 * Een abonnement opzeggen met de opzegtermijn van de school.
 *
 * Opzeggen is niet stoppen: het abonnement loopt door tot het einde van de
 * termijn en brengt tot dan gewoon rekeningen voort. De einddatum wordt hier
 * één keer vastgelegd (`ends_on`); de facturenloop (BillingPeriod) en de
 * nachtelijke levensloop (enrollments:lifecycle) lezen die en doen de rest.
 */
class PlanCancellation
{
    public function handle(Subscription $subscription, ?CarbonImmutable $op = null): Subscription
    {
        $op ??= CarbonImmutable::now();
        $maanden = EnrollmentSettings::for($subscription->school)->noticeMonths();

        // Nul maanden: per direct, aan het eind van vandaag.
        $einde = $op->addMonths($maanden)->toDateString();

        // Een blok dat al eerder stopt houdt zijn eigen einddatum.
        if ($subscription->ends_on !== null && $subscription->ends_on->lt($einde)) {
            $einde = $subscription->ends_on->toDateString();
        }

        $subscription->transitionTo(SubscriptionStatus::CancellationPlanned, ['ends_on' => $einde]);

        $inschrijving = $subscription->enrollment;

        if ($inschrijving !== null && $inschrijving->status->canTransitionTo(EnrollmentStatus::CancellationPlanned)) {
            $inschrijving->transitionTo(EnrollmentStatus::CancellationPlanned);
        }

        return $subscription;
    }
}
