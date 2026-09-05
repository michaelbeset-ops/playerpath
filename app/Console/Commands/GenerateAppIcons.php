<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * De app-iconen tekenen die een geïnstalleerde PWA op het beginscherm zet.
 *
 * Als commando en niet met de hand: zo staat vast hoe ze gemaakt zijn en kun
 * je ze na een merkwijziging opnieuw uitdraaien in plaats van in een
 * tekenprogramma te gaan zoeken.
 *
 * Het merkteken is hetzelfde als in AppLogoIcon.vue: een stijgende lijn met
 * een punt op de top.
 */
class GenerateAppIcons extends Command
{
    protected $signature = 'playerpath:icons';

    protected $description = 'Tekent de app-iconen voor de PWA in public/icons';

    /** @var list<int> */
    private array $maten = [192, 512];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('De GD-extensie is nodig om de iconen te tekenen.');

            return self::FAILURE;
        }

        $map = public_path('icons');

        if (! is_dir($map)) {
            mkdir($map, 0755, recursive: true);
        }

        foreach ($this->maten as $maat) {
            $this->teken($maat, "{$map}/icon-{$maat}.png");
            $this->line("  icon-{$maat}.png");
        }

        // Apple negeert het manifest en zoekt dit bestand.
        $this->teken(180, "{$map}/apple-touch-icon.png");
        $this->line('  apple-touch-icon.png');

        // Maskable: hetzelfde teken, maar kleiner zodat Android er een cirkel
        // uit mag knippen zonder de lijn af te snijden.
        $this->teken(512, "{$map}/icon-maskable-512.png", marge: 0.28);
        $this->line('  icon-maskable-512.png');

        $this->info('Klaar.');

        return self::SUCCESS;
    }

    private function teken(int $maat, string $pad, float $marge = 0.20): void
    {
        $beeld = imagecreatetruecolor($maat, $maat);
        imagesavealpha($beeld, true);
        imagealphablending($beeld, true);

        // Achtergrond in het donkerblauw van de speler-kant: een app-icoon
        // staat op een willekeurig beginscherm en moet daar tegen afsteken.
        imagefilledrectangle($beeld, 0, 0, $maat, $maat, imagecolorallocate($beeld, 10, 15, 28));

        $groen = imagecolorallocate($beeld, 34, 224, 107);

        $binnen = $maat * (1 - 2 * $marge);
        $links = $maat * $marge;
        $dikte = max(3, (int) round($binnen * 0.13));

        // Dezelfde punten als het SVG-merkteken (viewBox 24), geschaald naar 0..1.
        $punten = [[0.125, 0.729], [0.354, 0.5], [0.5, 0.646], [0.854, 0.292]];

        for ($i = 0; $i < count($punten) - 1; $i++) {
            $this->lijn(
                $beeld,
                $links + $punten[$i][0] * $binnen,
                $links + $punten[$i][1] * $binnen,
                $links + $punten[$i + 1][0] * $binnen,
                $links + $punten[$i + 1][1] * $binnen,
                $dikte,
                $groen,
            );
        }

        // De punt op de top, op hetzelfde eindpunt als de lijn.
        $this->stip(
            $beeld,
            $links + 0.854 * $binnen,
            $links + 0.292 * $binnen,
            $binnen * 0.115,
            $groen,
        );

        imagepng($beeld, $pad);
        imagedestroy($beeld);
    }

    /**
     * Een dikke lijn met ronde uiteinden.
     *
     * imagesetthickness() valt weg zodra je antialiasing aanzet, en levert
     * bovendien hoekige uiteinden op. Een reeks gevulde cirkels langs het pad
     * geeft precies de ronde stroke-linecap van het merkteken.
     */
    private function lijn(\GdImage $beeld, float $x1, float $y1, float $x2, float $y2, int $dikte, int $kleur): void
    {
        $afstand = max(1.0, sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2));
        $stappen = (int) ceil($afstand);

        for ($i = 0; $i <= $stappen; $i++) {
            $t = $i / $stappen;
            $this->stip($beeld, $x1 + ($x2 - $x1) * $t, $y1 + ($y2 - $y1) * $t, $dikte / 2, $kleur);
        }
    }

    private function stip(\GdImage $beeld, float $x, float $y, float $straal, int $kleur): void
    {
        $d = (int) round($straal * 2);

        imagefilledellipse($beeld, (int) round($x), (int) round($y), $d, $d, $kleur);
    }
}
