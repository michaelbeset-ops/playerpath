<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
            // Wie via de link in zijn mail een wachtwoord kiest, heeft daarmee
            // bewezen dat het adres van hem is. Zonder dit bleef een account dat
            // platformbeheer aanmaakte op "nog niet bevestigd" staan, ook nadat
            // de eigenaar allang was ingelogd - en dan kun je aan de lijst niet
            // zien of iemand zijn account in gebruik heeft genomen.
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
    }
}
