<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * De app-iconen tekenen die een geïnstalleerde PWA op het beginscherm zet, plus
 * de favicon voor het tabblad.
 *
 * Als commando en niet met de hand: zo staat vast hoe ze gemaakt zijn en kun je
 * ze na een merkwijziging opnieuw uitdraaien in plaats van in een
 * tekenprogramma te gaan zoeken.
 *
 * De bron is het merkteken zelf (`public/brand/mark-256.png`): de twee P's op
 * een donkere tegel. Alles wat hier gebeurt is schalen — zo staat op elk
 * beginscherm hetzelfde logo als in de app, en niet een benadering ervan.
 */
class GenerateAppIcons extends Command
{
    protected $signature = 'playerpath:icons';

    protected $description = 'Schaalt het merkteken naar de app-iconen en de favicon in public/icons';

    /** @var list<int> */
    private array $maten = [192, 512];

    /** De achtergrond van de tegel; zie public/brand. */
    private const TEGEL = [10, 15, 28]; // #0A0F1C

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('De GD-extensie is nodig om de iconen te maken.');

            return self::FAILURE;
        }

        $bron = public_path('brand/mark-256.png');

        if (! is_file($bron)) {
            $this->error('Het merkteken ontbreekt: public/brand/mark-256.png.');

            return self::FAILURE;
        }

        $map = public_path('icons');

        if (! is_dir($map)) {
            mkdir($map, 0755, recursive: true);
        }

        foreach ([...$this->maten, 180 => 180, 32 => 32] as $maat) {
            // 180 is wat Apple zoekt; 32 is het tabblad.
            $naam = match ($maat) {
                180 => 'apple-touch-icon.png',
                32 => 'favicon-32.png',
                default => "icon-{$maat}.png",
            };

            $this->schaal($bron, "{$map}/{$naam}", $maat);
            $this->line("  {$naam}");
        }

        // Maskable: hetzelfde teken kleiner op de tegelkleur, zodat Android er
        // een cirkel uit mag knippen zonder de letters af te snijden.
        $this->schaal($bron, "{$map}/icon-maskable-512.png", 512, marge: 0.18);
        $this->line('  icon-maskable-512.png');

        $this->info('Klaar.');

        return self::SUCCESS;
    }

    /**
     * Het merkteken op maat, met zo nodig een rand eromheen.
     *
     * De rand krijgt de kleur van de tegel: een doorzichtige rand zou op een
     * beginscherm een lichte hoek rond een donker icoon opleveren.
     */
    private function schaal(string $bron, string $doel, int $maat, float $marge = 0.0): void
    {
        $origineel = imagecreatefrompng($bron);

        $beeld = imagecreatetruecolor($maat, $maat);
        imagesavealpha($beeld, true);
        imagealphablending($beeld, false);
        imagefilledrectangle($beeld, 0, 0, $maat, $maat, imagecolorallocate($beeld, ...self::TEGEL));
        imagealphablending($beeld, true);

        $binnen = (int) round($maat * (1 - 2 * $marge));
        $offset = (int) round($maat * $marge);

        imagecopyresampled(
            $beeld, $origineel,
            $offset, $offset, 0, 0,
            $binnen, $binnen,
            imagesx($origineel), imagesy($origineel),
        );

        imagepng($beeld, $doel);
        imagedestroy($beeld);
        imagedestroy($origineel);
    }
}
