<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\User;
use App\Support\Media\ProfilePhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Profielfoto's van spelers en accounts.
 *
 * Wie wat mag staat in de policies, niet hier: `updatePhoto` op de speler
 * (de eigenaar, de ouders van dit kind, het kind zelf) en `update` op de
 * gebruiker (jezelf, of de eigenaar binnen zijn eigen school). Zo kan een
 * trainer geen foto van andermans kind verwisselen.
 */
class PhotoController extends Controller
{
    public function __construct(protected ProfilePhoto $fotos) {}

    public function storePlayer(Request $request, Player $player): RedirectResponse
    {
        $this->authorize('updatePhoto', $player);

        $this->fotos->store($player, $this->valideer($request), 'players/'.$player->school_id);

        return back()->with('status', 'De foto is opgeslagen.');
    }

    public function destroyPlayer(Player $player): RedirectResponse
    {
        $this->authorize('updatePhoto', $player);

        $this->fotos->delete($player);

        return back()->with('status', 'De foto is verwijderd.');
    }

    public function storeUser(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->fotos->store($user, $this->valideer($request), 'users/'.($user->school_id ?? 0));

        return back()->with('status', 'De foto is opgeslagen.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->fotos->delete($user);

        return back()->with('status', 'De foto is verwijderd.');
    }

    /**
     * Geen svg: dat is geen foto maar uitvoerbare opmaak, en die komt hier op
     * een pagina te staan die gedeeld kan worden.
     */
    protected function valideer(Request $request): UploadedFile
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ], [
            'photo.required' => 'Kies een foto.',
            'photo.image' => 'Kies een afbeelding.',
            'photo.mimes' => 'Gebruik een png, jpg of webp.',
            'photo.max' => 'De foto mag hooguit 5 MB zijn.',
        ], [
            'photo' => 'De foto',
        ]);

        return $request->file('photo');
    }
}
