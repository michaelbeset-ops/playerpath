<?php

namespace App\Support\Media;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * De afbeelding bij een aanbod, voor in de shop en op de inschrijfpagina.
 *
 * Anders dan een profielfoto niet vierkant maar **liggend**: verkleind tot
 * hooguit 1200 pixels breed, met de verhouding intact. Een kampfoto met de
 * hele groep erop wil je niet tot een vierkant knippen. Verder dezelfde
 * regels als ProfilePhoto: het oude bestand opruimen, een willekeurige naam,
 * geen svg.
 */
class ProductImage
{
    public const MAX_BREEDTE = 1200;

    public function store(Product $product, UploadedFile $bestand): void
    {
        $this->delete($product, save: false);

        $map = 'products/'.$product->school_id;
        $naam = $map.'/'.Str::random(40).'.jpg';
        $verkleind = $this->verklein($bestand);

        if ($verkleind === null) {
            $product->forceFill(['photo_path' => $bestand->store($map, 'public')])->save();

            return;
        }

        Storage::disk('public')->put($naam, $verkleind);
        $product->forceFill(['photo_path' => $naam])->save();
    }

    public function delete(Product $product, bool $save = true): void
    {
        if ($product->photo_path === null) {
            return;
        }

        Storage::disk('public')->delete($product->photo_path);
        $product->photo_path = null;

        if ($save) {
            $product->save();
        }
    }

    /** Verkleinen tot de maximale breedte. Null als GD het niet kan lezen. */
    protected function verklein(UploadedFile $bestand): ?string
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
        $factor = min(1, self::MAX_BREEDTE / max(1, $breedte));
        $nieuwBreedte = max(1, (int) round($breedte * $factor));
        $nieuwHoogte = max(1, (int) round($hoogte * $factor));

        $doel = imagecreatetruecolor($nieuwBreedte, $nieuwHoogte);
        imagecopyresampled($doel, $bron, 0, 0, 0, 0, $nieuwBreedte, $nieuwHoogte, $breedte, $hoogte);

        ob_start();
        imagejpeg($doel, null, 85);
        $uitvoer = ob_get_clean();

        imagedestroy($bron);
        imagedestroy($doel);

        return $uitvoer === false ? null : $uitvoer;
    }
}
