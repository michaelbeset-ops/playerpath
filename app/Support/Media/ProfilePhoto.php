<?php

namespace App\Support\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Een profielfoto opslaan, vervangen of weghalen.
 *
 * Eén plek voor spelers en accounts, want het is precies hetzelfde werk en
 * twee kopieën lopen uit elkaar zodra er iets verandert aan de opslag.
 *
 * Drie dingen die deze klasse doet en die je niet moet weglaten:
 *
 * 1. **Vierkant maken.** Een foto komt op een rond medaillon en op de
 *    spelerskaart. Een liggende foto die met CSS wordt bijgesneden ziet er op
 *    elke plek net anders uit; hier wordt hij één keer goed uitgesneden, uit
 *    het midden, en daarna klopt hij overal.
 * 2. **Verkleinen naar 512 pixels.** Een telefoonfoto van vier megabyte in een
 *    medaillon van 40 pixels is verspilling van geheugen en van de bundel van
 *    een ouder die op het veld staat te kijken.
 * 3. **Het oude bestand opruimen.** Anders blijft elke poging staan en groeit
 *    de schijf vol met foto's die niemand meer ziet.
 *
 * De bestandsnaam is willekeurig, niet het id. Een foto van een kind hoort niet
 * te raden te zijn aan de hand van een nummer in een URL.
 */
class ProfilePhoto
{
    /** De zijde van de opgeslagen foto, in pixels. */
    public const ZIJDE = 512;

    /**
     * @param  Model&object{photo_path: string|null}  $model
     */
    public function store(Model $model, UploadedFile $bestand, string $map): void
    {
        $this->delete($model, save: false);

        $naam = $map.'/'.Str::random(40).'.jpg';

        $vierkant = $this->vierkant($bestand);

        if ($vierkant === null) {
            // Geen GD of een formaat dat we niet kunnen lezen: dan maar
            // ongewijzigd bewaren. Een foto die er scheef op staat is beter
            // dan een foutmelding waar de gebruiker niets mee kan.
            $model->photo_path = $bestand->store($map, 'public');
            $model->save();

            return;
        }

        Storage::disk('public')->put($naam, $vierkant);

        $model->photo_path = $naam;
        $model->save();
    }

    /** @param  Model&object{photo_path: string|null}  $model */
    public function delete(Model $model, bool $save = true): void
    {
        if ($model->photo_path === null) {
            return;
        }

        Storage::disk('public')->delete($model->photo_path);
        $model->photo_path = null;

        if ($save) {
            $model->save();
        }
    }

    /**
     * Uitsnijden op het midden en verkleinen. Null als het niet lukt.
     */
    protected function vierkant(UploadedFile $bestand): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $inhoud = file_get_contents($bestand->getRealPath());

        if ($inhoud === false) {
            return null;
        }

        $bron = @imagecreatefromstring($inhoud);

        if ($bron === false) {
            return null;
        }

        $breedte = imagesx($bron);
        $hoogte = imagesy($bron);
        $zijde = min($breedte, $hoogte);

        // Uit het midden: bij een portret staat het gezicht daar zelden precies,
        // maar iets hoger dan het midden komt in de praktijk beter uit. Een
        // achtste van de overhoogte omhoog is genoeg om de kruin er niet af te
        // snijden zonder dat een liggende foto scheef gaat.
        $x = (int) (($breedte - $zijde) / 2);
        $y = (int) max(0, ($hoogte - $zijde) / 2 - ($hoogte - $zijde) / 8);

        $doel = imagecreatetruecolor(self::ZIJDE, self::ZIJDE);
        imagecopyresampled($doel, $bron, 0, 0, $x, $y, self::ZIJDE, self::ZIJDE, $zijde, $zijde);

        ob_start();
        imagejpeg($doel, null, 82);
        $uitvoer = ob_get_clean();

        imagedestroy($bron);
        imagedestroy($doel);

        return $uitvoer === false ? null : $uitvoer;
    }
}
