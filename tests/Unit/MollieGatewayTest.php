<?php

namespace Tests\Unit;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Support\Payments\MollieGateway;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment as MolliePayment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * De vertaling naar Mollie. Hier zitten de twee dingen die met geld écht
 * misgaan: het bedrag en de status.
 */
class MollieGatewayTest extends TestCase
{
    /** @return array<string, array{int, string}> */
    public static function bedragen(): array
    {
        return [
            'hele euro' => [1000, '10.00'],
            'met centen' => [1250, '12.50'],
            'onder een euro' => [5, '0.05'],
            'negen centen' => [909, '9.09'],
            'nul' => [0, '0.00'],
            'groot bedrag' => [123456789, '1234567.89'],
        ];
    }

    #[DataProvider('bedragen')]
    public function test_centen_worden_exact_omgezet(int $cents, string $verwacht): void
    {
        $this->assertSame($verwacht, MollieGateway::toAmount($cents));
    }

    public function test_het_klassieke_floatprobleem_treedt_niet_op(): void
    {
        // 12.50 * 100 geeft in floating point 1249,9999...; via centen niet.
        $this->assertSame('12.50', MollieGateway::toAmount(1250));
        $this->assertSame('0.29', MollieGateway::toAmount(29));
        $this->assertSame('1.15', MollieGateway::toAmount(115));
    }

    public function test_een_betaalde_betaling_leest_als_betaald(): void
    {
        $this->assertSame(PaymentStatus::Paid, MollieGateway::toStatus($this->mollie(['status' => 'paid', 'paidAt' => '2026-09-05T12:00:00+00:00'])));
    }

    public function test_alles_wat_niets_meer_oplevert_leest_als_mislukt(): void
    {
        foreach (['failed', 'expired', 'canceled'] as $status) {
            $this->assertSame(
                PaymentStatus::Failed,
                MollieGateway::toStatus($this->mollie(['status' => $status])),
                "status {$status}",
            );
        }
    }

    public function test_een_openstaande_betaling_blijft_openstaan(): void
    {
        foreach (['open', 'pending', 'authorized'] as $status) {
            $this->assertSame(PaymentStatus::Open, MollieGateway::toStatus($this->mollie(['status' => $status])));
        }
    }

    public function test_een_stornering_wint_van_betaald(): void
    {
        $betaling = $this->mollie([
            'status' => 'paid',
            'paidAt' => '2026-09-05T12:00:00+00:00',
            'amount' => $this->bedrag('27.50'),
            'amountChargedBack' => $this->bedrag('27.50'),
        ]);

        $this->assertSame(PaymentStatus::ChargedBack, MollieGateway::toStatus($betaling));
    }

    public function test_een_volledige_terugbetaling_wordt_herkend(): void
    {
        $betaling = $this->mollie([
            'status' => 'paid',
            'paidAt' => '2026-09-05T12:00:00+00:00',
            'amount' => $this->bedrag('27.50'),
            'amountRefunded' => $this->bedrag('27.50'),
        ]);

        $this->assertSame(PaymentStatus::Refunded, MollieGateway::toStatus($betaling));
    }

    public function test_een_gedeeltelijke_terugbetaling_blijft_betaald(): void
    {
        // De rekening is in de kern voldaan; hem op terugbetaald zetten zou de
        // omzet laten verdampen voor een tientje korting.
        $betaling = $this->mollie([
            'status' => 'paid',
            'paidAt' => '2026-09-05T12:00:00+00:00',
            'amount' => $this->bedrag('27.50'),
            'amountRefunded' => $this->bedrag('10.00'),
        ]);

        $this->assertSame(PaymentStatus::Paid, MollieGateway::toStatus($betaling));
    }

    public function test_een_gedeeltelijke_stornering_telt_wel_meteen(): void
    {
        $betaling = $this->mollie([
            'status' => 'paid',
            'paidAt' => '2026-09-05T12:00:00+00:00',
            'amount' => $this->bedrag('27.50'),
            'amountChargedBack' => $this->bedrag('5.00'),
        ]);

        $this->assertSame(PaymentStatus::ChargedBack, MollieGateway::toStatus($betaling));
    }

    public function test_betaalmethodes_worden_vertaald_of_leeggelaten(): void
    {
        $this->assertSame(PaymentMethod::Ideal, MollieGateway::toMethod('ideal'));
        $this->assertSame(PaymentMethod::DirectDebit, MollieGateway::toMethod('directdebit'));
        $this->assertSame(PaymentMethod::Transfer, MollieGateway::toMethod('banktransfer'));

        // Wat wij niet kennen laten we leeg in plaats van te gokken.
        $this->assertNull(MollieGateway::toMethod('creditcard'));
        $this->assertNull(MollieGateway::toMethod(null));
    }

    private function bedrag(string $waarde): object
    {
        return (object) ['currency' => 'EUR', 'value' => $waarde];
    }

    /** @param array<string, mixed> $eigenschappen */
    private function mollie(array $eigenschappen): MolliePayment
    {
        $betaling = new MolliePayment(new MollieApiClient);

        foreach ($eigenschappen as $naam => $waarde) {
            $betaling->{$naam} = $waarde;
        }

        return $betaling;
    }
}
