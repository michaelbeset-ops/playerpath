<?php

namespace Tests\Unit;

use App\Support\Money\Money;
use App\Support\Money\SplitAmount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Wat mensen typen, naar centen. Wat isValid() goedkeurt moet toCents() ook
 * zo lezen: "1.250" is twaalfhonderdvijftig euro, geen € 1,25.
 */
class MoneyTest extends TestCase
{
    /** @return array<string, array{string, int}> */
    public static function bedragen(): array
    {
        return [
            'punt als decimaal' => ['12.50', 1250],
            'komma als decimaal' => ['12,50', 1250],
            'een decimaal met komma' => ['12,5', 1250],
            'een decimaal met punt' => ['12.5', 1250],
            'heel getal' => ['12', 1200],
            'duizendtal zonder komma' => ['1.250', 125000],
            'duizendtal met komma' => ['1.250,00', 125000],
            'twee duizendtallen' => ['1.250.000', 125000000],
            'met euroteken' => ['€ 12,50', 1250],
            'het floatprobleem' => ['0,29', 29],
            'nul' => ['0', 0],
            'negatief' => ['-12,50', -1250],
        ];
    }

    #[DataProvider('bedragen')]
    public function test_bedragen_worden_zonder_float_omgezet(string $invoer, int $centen): void
    {
        $this->assertSame($centen, Money::toCents($invoer));
    }

    public function test_wat_geldig_is_wordt_ook_zo_gelezen(): void
    {
        $this->assertTrue(Money::isValid('1.250'));
        $this->assertSame(125000, Money::toCents('1.250'));
        $this->assertTrue(Money::isValid('12.50'));
        $this->assertSame(1250, Money::toCents('12.50'));
    }

    public function test_naar_rato_verdelen_telt_exact_op(): void
    {
        $delen = SplitAmount::proportional(-2400, [9 => 12000, 21 => 12001]);

        $this->assertSame(-2400, array_sum($delen));
        // Naar beneden afgerond 1199 en 1200; de restcent gaat naar het zwaarste gewicht.
        $this->assertSame([9 => -1199, 21 => -1201], $delen);
        $this->assertSame(100, array_sum(SplitAmount::proportional(100, [1 => 1, 2 => 1, 3 => 1])));
        $this->assertSame([5 => 7], SplitAmount::proportional(7, [5 => 0]));
    }
}
