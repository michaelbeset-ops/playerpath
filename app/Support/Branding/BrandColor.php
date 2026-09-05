<?php

namespace App\Support\Branding;

use InvalidArgumentException;

/**
 * Een merkkleur van een school omzetten naar wat de huisstijl nodig heeft.
 *
 * De tokens in app.css zijn HSL-drietallen ("143 75% 42%"), geen hex. Een
 * school kiest een hex, want dat is wat er in haar logo-bestand staat.
 *
 * De belangrijkste taak hier is **niet** het omrekenen maar het leesbaar
 * houden. Een school die knalgeel kiest zou met witte letters op de knop
 * onleesbaar worden. Daarom bepalen we de tekstkleur zelf uit de helderheid
 * van de kleur, in plaats van hem vast op wit te zetten.
 */
final class BrandColor
{
    private function __construct(
        public readonly int $r,
        public readonly int $g,
        public readonly int $b,
    ) {}

    public static function fromHex(string $hex): self
    {
        $schoon = ltrim(trim($hex), '#');

        // Korte notatie (#1a2) is net zo geldig als de lange.
        if (strlen($schoon) === 3) {
            $schoon = $schoon[0].$schoon[0].$schoon[1].$schoon[1].$schoon[2].$schoon[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $schoon)) {
            throw new InvalidArgumentException("Geen geldige kleurcode: {$hex}");
        }

        return new self(
            (int) hexdec(substr($schoon, 0, 2)),
            (int) hexdec(substr($schoon, 2, 2)),
            (int) hexdec(substr($schoon, 4, 2)),
        );
    }

    public static function isValid(string $hex): bool
    {
        try {
            self::fromHex($hex);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Dezelfde kleur, zo nodig lichter of donkerder gemaakt tot er leesbare
     * tekst op past.
     *
     * Sommige middentinten — een fel oranjerood bijvoorbeeld — halen met wít
     * noch met zwart de ondergrens van 4,5:1. De eis verlagen zou betekenen dat
     * een school haar eigen knoppen niet meer kan lezen; de kleur weigeren zou
     * betekenen dat ze haar merk niet mag gebruiken. Daarom schuiven we alleen
     * de helderheid op, met behoud van tint en verzadiging, en zo min mogelijk.
     *
     * We proberen beide richtingen en nemen de kleinste aanpassing, zodat het
     * resultaat zo dicht mogelijk bij de gekozen kleur blijft.
     */
    public function adjustedForContrast(float $minimum = 4.5): self
    {
        if ($this->foregroundContrast() >= $minimum) {
            return $this;
        }

        [$h, $sat, $l] = $this->toHsl();

        $donkerder = null;
        $lichter = null;

        for ($stap = 1; $stap <= 100; $stap++) {
            if ($donkerder === null) {
                $kandidaat = self::fromHsl($h, $sat, max(0.0, $l - $stap / 100));

                if ($kandidaat->foregroundContrast() >= $minimum) {
                    $donkerder = [$stap, $kandidaat];
                }
            }

            if ($lichter === null) {
                $kandidaat = self::fromHsl($h, $sat, min(1.0, $l + $stap / 100));

                if ($kandidaat->foregroundContrast() >= $minimum) {
                    $lichter = [$stap, $kandidaat];
                }
            }

            if ($donkerder !== null && $lichter !== null) {
                break;
            }
        }

        return match (true) {
            $donkerder !== null && $lichter !== null => $donkerder[0] <= $lichter[0] ? $donkerder[1] : $lichter[1],
            $donkerder !== null => $donkerder[1],
            $lichter !== null => $lichter[1],
            default => $this,
        };
    }

    /** Is deze kleur aangepast moeten worden om leesbaar te zijn? */
    public function needsAdjustment(float $minimum = 4.5): bool
    {
        return $this->foregroundContrast() < $minimum;
    }

    public function toHex(): string
    {
        return sprintf('#%02X%02X%02X', $this->r, $this->g, $this->b);
    }

    /** @param float $h graden, $s en $l als 0..1 */
    private static function fromHsl(float $h, float $s, float $l): self
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (intdiv((int) $h, 60) % 6) {
            0 => [$c, $x, 0.0],
            1 => [$x, $c, 0.0],
            2 => [0.0, $c, $x],
            3 => [0.0, $x, $c],
            4 => [$x, 0.0, $c],
            default => [$c, 0.0, $x],
        };

        return new self(
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        );
    }

    /** Het drietal dat in een CSS-variabele past, bijvoorbeeld "143 75% 42%". */
    public function toHslTriplet(): string
    {
        [$h, $s, $l] = $this->toHsl();

        return sprintf('%d %d%% %d%%', round($h), round($s * 100), round($l * 100));
    }

    /**
     * Wit of bijna-zwart, afhankelijk van wat leesbaar is op deze kleur.
     *
     * We vergelijken het contrast van beide en nemen de beste. Een vaste keuze
     * voor wit werkt voor donkergroen, maar niet voor geel of lichtblauw.
     */
    public function readableForeground(): string
    {
        $opWit = self::contrast($this->relativeLuminance(), 1.0);
        $opZwart = self::contrast($this->relativeLuminance(), self::luminanceOf(15, 23, 42));

        return $opWit >= $opZwart ? '0 0% 100%' : '222 47% 11%';
    }

    /** Het contrast met de gekozen tekstkleur, om de school te kunnen waarschuwen. */
    public function foregroundContrast(): float
    {
        $opWit = self::contrast($this->relativeLuminance(), 1.0);
        $opZwart = self::contrast($this->relativeLuminance(), self::luminanceOf(15, 23, 42));

        return round(max($opWit, $opZwart), 2);
    }

    /**
     * Contrast met de lichte werkvloer (#F8F9FA).
     *
     * Hiermee zie je of de kleur zelf nog leesbaar is als tekst of als dun
     * randje; onder 3:1 is een merkkleur op wit niet meer te onderscheiden.
     */
    public function contrastOnLightSurface(): float
    {
        return round(self::contrast($this->relativeLuminance(), self::luminanceOf(248, 249, 250)), 2);
    }

    /** @return array{float, float, float} tint, verzadiging, helderheid */
    private function toHsl(): array
    {
        $r = $this->r / 255;
        $g = $this->g / 255;
        $b = $this->b / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match (true) {
            $max === $r => fmod(($g - $b) / $d + ($g < $b ? 6 : 0), 6),
            $max === $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h * 60, $s, $l];
    }

    private function relativeLuminance(): float
    {
        return self::luminanceOf($this->r, $this->g, $this->b);
    }

    private static function luminanceOf(int $r, int $g, int $b): float
    {
        $kanaal = static function (int $waarde): float {
            $v = $waarde / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $kanaal($r) + 0.7152 * $kanaal($g) + 0.0722 * $kanaal($b);
    }

    private static function contrast(float $een, float $twee): float
    {
        $licht = max($een, $twee);
        $donker = min($een, $twee);

        return ($licht + 0.05) / ($donker + 0.05);
    }
}
