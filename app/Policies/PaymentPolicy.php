<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Het betaaloverzicht van de school is van de eigenaar.
 *
 * Een ouder ziet zijn eigen betalingen wel, maar via een eigen scherm en
 * alleen die van zijn eigen kind - niet via dit overzicht.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEigenaar();
    }

    public function view(User $user, Payment $payment): bool
    {
        if (! $user->belongsToSameSchool($payment)) {
            return false;
        }

        if ($user->isEigenaar()) {
            return true;
        }

        // Een ouder of speler mag de betalingen van zijn eigen speler zien.
        return in_array($payment->player_id, $user->visiblePlayerIds(), strict: true);
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->belongsToSameSchool($payment) && $user->isEigenaar();
    }
}
