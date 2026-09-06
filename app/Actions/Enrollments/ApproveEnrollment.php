<?php

namespace App\Actions\Enrollments;

use App\Actions\Payments\GeneratePayments;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Enrollment;
use App\Models\Player;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Een inschrijving goedkeuren: in één transactie ontstaan de speler, het
 * ouderaccount (of de koppeling aan een bestaand account) en het abonnement.
 *
 * Bestaat het e-mailadres van de ouder al bij deze school, dan koppelen we
 * dat account. Bestaat het bij een andere school, dan stoppen we: een
 * account hoort bij precies één school.
 */
class ApproveEnrollment
{
    public function __construct(protected GeneratePayments $facturen) {}

    public function handle(Enrollment $enrollment, User $eigenaar): Player
    {
        if ($enrollment->status !== EnrollmentStatus::Pending) {
            throw new RuntimeException('Deze inschrijving is al afgehandeld.');
        }

        $bestaand = User::where('email', $enrollment->guardian_email)->first();

        if ($bestaand !== null && $bestaand->school_id !== $enrollment->school_id) {
            throw new RuntimeException(
                'Het e-mailadres van de ouder hoort al bij een account van een andere school. Neem contact op met de ouder.'
            );
        }

        $nieuwAccount = null;

        $player = DB::transaction(function () use ($enrollment, $eigenaar, $bestaand, &$nieuwAccount) {
            $player = Player::create([
                'first_name' => $enrollment->first_name,
                'last_name' => $enrollment->last_name,
                'date_of_birth' => $enrollment->date_of_birth,
                'position' => $enrollment->position,
                'is_active' => true,
            ]);

            $ouder = $bestaand;

            if ($ouder === null) {
                $ouder = User::create([
                    'school_id' => $enrollment->school_id,
                    'name' => $enrollment->guardian_name,
                    'email' => $enrollment->guardian_email,
                    'password' => Str::password(32),
                ]);
                $ouder->assignRole(Role::Ouder->value);
                $nieuwAccount = $ouder;
            } elseif (! $ouder->isOuder()) {
                $ouder->assignRole(Role::Ouder->value);
            }

            $player->guardians()->syncWithoutDetaching([
                $ouder->id => ['relationship' => $enrollment->relationship],
            ]);

            if ($enrollment->product) {
                $abonnement = Subscription::create([
                    'player_id' => $player->id,
                    'product_id' => $enrollment->product->id,
                    'amount_cents' => $enrollment->product->amount_cents,
                    'vat_rate' => $enrollment->product->vat_rate,
                    'interval' => $enrollment->product->interval,
                    'status' => SubscriptionStatus::Active,
                    'payment_method' => $enrollment->payment_method,
                    'starts_on' => now()->toDateString(),
                ]);

                // Meteen de eerste rekening, zodat er iets te betalen is zodra
                // de ouder inlogt. Wachten op de nachtelijke facturenloop zou
                // betekenen dat een net goedgekeurd gezin een leeg scherm ziet.
                $this->facturen->handle($abonnement);
            }

            $enrollment->forceFill([
                'status' => EnrollmentStatus::Approved,
                'player_id' => $player->id,
                'handled_by_id' => $eigenaar->id,
                'handled_at' => now(),
            ])->save();

            return $player;
        });

        // Pas na de transactie: een mail over een account dat niet bestaat wil je niet.
        if ($nieuwAccount !== null) {
            Password::sendResetLink(['email' => $nieuwAccount->email]);
        }

        return $player;
    }
}
