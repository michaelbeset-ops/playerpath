<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // email_verified_at blijft staan: User kent geen MustVerifyEmail, en
        // platformbeheer leest die kolom als "account is geactiveerd". Leegmaken
        // zou een ingebruikgenomen account als nooit geactiveerd tonen.
        $request->user()->fill($request->validated())->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        /** @var User $user */
        $user = $request->user();

        // Zonder eigenaar is een school onbeheerbaar: niemand kan dan nog
        // trainers uitnodigen, betalingen zien of het account opzeggen.
        if ($user->isEigenaar() && $user->school_id !== null) {
            $andereEigenaren = User::query()
                ->where('school_id', $user->school_id)
                ->whereKeyNot($user->id)
                ->role(Role::Eigenaar->value)
                ->count();

            if ($andereEigenaren === 0) {
                throw ValidationException::withMessages([
                    'password' => 'Je bent de enige eigenaar van deze school. Maak eerst iemand anders eigenaar, of neem contact met ons op om de school op te zeggen.',
                ]);
            }
        }

        Auth::logout();

        // Meldingen hangen polymorf aan de gebruiker en hebben geen
        // databasesleutel die ze meeneemt.
        $user->notifications()->delete();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
