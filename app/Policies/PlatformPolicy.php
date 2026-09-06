<?php

namespace App\Policies;

use App\Models\User;

/**
 * Wat een platformbeheerder mag.
 *
 * Bewust een eigen policy en geen extra takken in SchoolPolicy: die gaat over
 * wat een schooleigenaar met zijn eigen school mag, en dat is een andere vraag
 * dan wat de beheerder van het platform met alle scholen mag. Ze bij elkaar
 * zetten zou de eerste vraag moeilijker leesbaar maken.
 *
 * Elke check begint hier bij de rol; de middleware controleert die ook al, maar
 * twee sloten op dezelfde deur is in dit project de afspraak.
 */
class PlatformPolicy
{
    public function access(User $user): bool
    {
        return $user->isPlatformbeheerder();
    }

    public function manageSchools(User $user): bool
    {
        return $user->isPlatformbeheerder();
    }
}
