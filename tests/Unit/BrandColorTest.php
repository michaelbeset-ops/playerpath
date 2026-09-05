<?php

namespace Tests\Unit;

use App\Support\Branding\BrandColor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * De merkkleur van een school. Het risico hier is niet het omrekenen maar de
 * leesbaarheid: een school die geel kiest mag geen witte letters krijgen.
 */
class BrandColorTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function kleuren(): array
    {
        return [
            'merkgroen' => ['#1BB85E', '146 74% 41%'],
            'zwart' => ['#000000', '0 0% 0%'],
            'wit' => ['#FFFFFF', '0 0% 100%'],
            'rood' => ['#FF0000', '0 100% 50%'],
            'korte notatie' => ['#1a2', '127 82% 37%'],
            'zonder hekje' => ['1BB85E', '146 74% 41%'],
        ];
    }

    #[DataProvider('kleuren')]
    public function test_een_hex_wordt_een_hsl_drietal(string $hex, string $verwacht): void
    {
        $this->assertSame($verwacht, BrandColor::fromHex($hex)->toHslTriplet());
    }

    public function test_een_onzinnige_kleur_wordt_geweigerd(): void
    {
        $this->assertFalse(BrandColor::isValid('groen'));
        $this->assertFalse(BrandColor::isValid('#12345'));
        $this->assertFalse(BrandColor::isValid(''));
        $this->assertTrue(BrandColor::isValid('#1BB85E'));

        $this->expectException(InvalidArgumentException::class);
        BrandColor::fromHex('geen kleur');
    }

    public function test_op_een_donkere_kleur_komt_witte_tekst(): void
    {
        $this->assertSame('0 0% 100%', BrandColor::fromHex('#0F172A')->readableForeground());
        $this->assertSame('0 0% 100%', BrandColor::fromHex('#7B1FA2')->readableForeground());
    }

    public function test_de_tekstkleur_volgt_de_meting_en_niet_het_gevoel(): void
    {
        // Middengroen vóélt donker, maar is het niet: donkere tekst haalt er
        // ruim het dubbele contrast op. Wie hier wit forceert maakt een knop
        // die je in de zon niet meer leest.
        $groen = BrandColor::fromHex('#1BB85E');

        $this->assertSame('222 47% 11%', $groen->readableForeground());
        $this->assertGreaterThan(4.5, $groen->foregroundContrast());
    }

    public function test_op_een_lichte_kleur_komt_donkere_tekst(): void
    {
        // Dit is het geval waar een vaste keuze voor wit onleesbaar zou worden.
        $this->assertSame('222 47% 11%', BrandColor::fromHex('#FFE600')->readableForeground());
        $this->assertSame('222 47% 11%', BrandColor::fromHex('#7FFFD4')->readableForeground());
    }

    public function test_de_gekozen_tekstkleur_haalt_altijd_voldoende_contrast(): void
    {
        // Voor elke kleur op de cirkel moet de beste van wit/zwart door de
        // ondergrens van 4,5:1 komen. Zo niet, dan is de regel niet goed genoeg.
        foreach (range(0, 350, 10) as $tint) {
            foreach ([25, 50, 75] as $helderheid) {
                $hex = self::hslNaarHex($tint, 80, $helderheid);
                $kleur = BrandColor::fromHex($hex);

                // De kleur zoals hij in de interface belandt, dus na correctie.
                $this->assertGreaterThanOrEqual(
                    4.5,
                    $kleur->adjustedForContrast()->foregroundContrast(),
                    "Tekst op {$hex} is niet leesbaar genoeg",
                );
            }
        }
    }

    public function test_een_kleur_die_al_goed_is_blijft_ongemoeid(): void
    {
        $groen = BrandColor::fromHex('#1BB85E');

        $this->assertFalse($groen->needsAdjustment());
        $this->assertSame('#1BB85E', $groen->adjustedForContrast()->toHex());
    }

    public function test_een_lastige_middentint_wordt_zo_min_mogelijk_bijgesteld(): void
    {
        // Fel oranjerood haalt met wit noch zwart 4,5:1.
        $lastig = BrandColor::fromHex('#E63B19');

        $this->assertTrue($lastig->needsAdjustment());

        $bijgesteld = $lastig->adjustedForContrast();

        $this->assertGreaterThanOrEqual(4.5, $bijgesteld->foregroundContrast());
        $this->assertNotSame($lastig->toHex(), $bijgesteld->toHex());

        // Tint blijft herkenbaar hetzelfde; alleen de helderheid schuift op.
        $this->assertSame(
            explode(' ', $lastig->toHslTriplet())[0],
            explode(' ', $bijgesteld->toHslTriplet())[0],
        );
    }

    public function test_contrast_met_de_werkvloer_wordt_gemeld(): void
    {
        // Merkgroen is op wit net te licht om als tekst te gebruiken; dat mag
        // een school weten voordat ze het kiest.
        $this->assertLessThan(3.0, BrandColor::fromHex('#22E06B')->contrastOnLightSurface());
        $this->assertGreaterThan(3.0, BrandColor::fromHex('#0F172A')->contrastOnLightSurface());
    }

    private static function hslNaarHex(int $h, int $s, int $l): string
    {
        $s /= 100;
        $l /= 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (intdiv($h, 60)) {
            0 => [$c, $x, 0],
            1 => [$x, $c, 0],
            2 => [0, $c, $x],
            3 => [0, $x, $c],
            4 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf('#%02X%02X%02X', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }
}
