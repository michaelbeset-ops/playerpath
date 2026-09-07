<?php

namespace App\Support\Enrollment;

use App\Enums\DiscountKind;
use App\Enums\OrderLineType;
use App\Enums\PaymentOptionType;
use App\Models\Discount;
use App\Models\OrderLine;
use App\Models\PaymentOption;
use App\Models\Product;
use App\Models\User;
use App\Support\Money\Money;

/**
 * Wat een ouder betaalt, en waarvoor: de regels van een order.
 *
 * Eén plek die uit de gekozen aanbod-regels, de inschrijfinstellingen en de
 * kortingen een lijst regels maakt met een totaal. Het overzicht op het
 * inschrijfformulier én de order die daarna ontstaat komen hiervandaan, dus
 * die twee kunnen niet uit elkaar lopen: wat een ouder zag is wat er wordt
 * vastgelegd.
 *
 * Regels die je niet moet omdraaien:
 *
 * - **Alles in centen.** Percentages via `intdiv`, nooit via een float.
 * - **Korting is een regel met een negatief bedrag**, zodat je later ziet
 *   waarom het lager was. Niet stapelbaar betekent: alleen de hoogste telt.
 * - **Inschrijfgeld en kledingpakket zijn per gezin één keer.** Wie ze al
 *   eens betaald heeft krijgt ze niet nog eens; wie twee kinderen tegelijk
 *   inschrijft ook niet.
 * - **Een abonnement betaal je niet vooraf.** Het bedrag per periode staat ter
 *   informatie op het overzicht; de rekeningen daarvoor komen uit het
 *   abonnement zelf. Wat je nú betaalt zijn de eenmalige regels.
 */
class OrderBuilder
{
    /**
     * @param  list<array{product: Product, option: PaymentOption, child_name: string, player_id?: int|null}>  $regels
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     total_cents: int,
     *     upfront_cents: int,
     *     discount_cents: int,
     *     recurring: list<array{description: string, amount: string}>,
     *     code: array{code: string, valid: bool, message: string|null}|null,
     * }
     */
    public function build(EnrollmentSettings $settings, array $regels, ?User $ouder = null, ?string $code = null): array
    {
        $lines = [];
        $recurring = [];
        $aanbodTotaal = 0;

        foreach ($regels as $regel) {
            $optie = $regel['option'];

            if ($optie->type === PaymentOptionType::Abonnement) {
                // Ter informatie: dit komt per periode, niet nu.
                $recurring[] = [
                    'description' => "{$regel['product']->name} voor {$regel['child_name']}",
                    'amount' => $optie->describe(),
                ];

                continue;
            }

            $bedrag = $optie->totalCents() ?? 0;
            $aanbodTotaal += $bedrag;

            $lines[] = $this->regel(
                $regel['product']->type->value === 'proefles' ? OrderLineType::Trial : OrderLineType::Offering,
                "{$regel['product']->name} voor {$regel['child_name']}"
                    .($optie->type === PaymentOptionType::Termijnen ? " ({$optie->installments} termijnen)" : ''),
                $bedrag,
                $regel['product']->vat_rate,
                ['product_id' => $regel['product']->id, 'player_id' => $regel['player_id'] ?? null],
            );
        }

        // Eén keer per gezin: inschrijfgeld en kledingpakket.
        if ($settings->registrationFeeCents() > 0 && ! $this->alBetaald($ouder, OrderLineType::RegistrationFee)) {
            $lines[] = $this->regel(OrderLineType::RegistrationFee, 'Eenmalig inschrijfgeld', $settings->registrationFeeCents(), 21);
        }

        if ($settings->kitCents() > 0 && ! $this->alBetaald($ouder, OrderLineType::Kit)) {
            $lines[] = $this->regel(OrderLineType::Kit, 'Kledingpakket', $settings->kitCents(), 21);
        }

        // Kortingen, over het aanbodbedrag (niet over inschrijfgeld of kleding).
        $kortingen = $this->kortingen($settings, $regels, $ouder, $code, $aanbodTotaal);

        foreach ($kortingen['lines'] as $korting) {
            $lines[] = $korting;
        }

        $totaal = array_sum(array_column($lines, 'amount_cents'));

        return [
            'lines' => array_map(fn (array $l) => [...$l, 'amount' => Money::format($l['amount_cents'])], $lines),
            'total_cents' => $totaal,
            'total' => Money::format($totaal),
            'upfront_cents' => $totaal,
            'discount_cents' => (int) abs(array_sum(array_map(fn (array $l) => min(0, $l['amount_cents']), $lines))),
            'recurring' => $recurring,
            'code' => $kortingen['code'],
        ];
    }

    /**
     * @param  list<array{product: Product, option: PaymentOption, child_name: string}>  $regels
     * @return array{lines: list<array<string, mixed>>, code: array{code: string, valid: bool, message: string|null}|null}
     */
    protected function kortingen(EnrollmentSettings $settings, array $regels, ?User $ouder, ?string $code, int $aanbodTotaal): array
    {
        $instellingen = $settings->get('discounts');
        $kandidaten = [];
        $codeUitkomst = null;

        $eenmalig = array_values(array_filter($regels, fn ($r) => $r['option']->type !== PaymentOptionType::Abonnement));

        // Gezin: een tweede kind (in deze order, of al een actief kind van
        // deze ouder) krijgt korting op zijn deel.
        if (($instellingen['family']['enabled'] ?? false) && count($eenmalig) > 0) {
            // Al een actief kind bij deze school, buiten de kinderen van deze order
            // om: die zijn bij het indienen al aangemaakt en zouden anders zichzelf
            // korting geven.
            $eigenIds = array_values(array_filter(array_map(fn ($r) => $r['player_id'] ?? null, $regels)));
            $heeftAlKind = $ouder !== null && $ouder->children()->where('is_active', true)->whereNotIn('players.id', $eigenIds)->exists();
            $vanaf = $heeftAlKind ? 0 : 1;
            $basis = 0;

            foreach (array_slice($eenmalig, $vanaf) as $r) {
                $basis += $r['option']->totalCents() ?? 0;
            }

            if ($basis > 0) {
                $kandidaten[] = [
                    'kind' => DiscountKind::Family,
                    'description' => "Gezinskorting {$instellingen['family']['percent']}%",
                    'amount_cents' => -intdiv($basis * (int) $instellingen['family']['percent'], 100),
                ];
            }
        }

        // Vroegboek: het aanbod begint over minstens X dagen.
        if (($instellingen['early']['enabled'] ?? false) && $aanbodTotaal > 0) {
            $basis = 0;

            foreach ($eenmalig as $r) {
                $start = $r['product']->starts_on;

                if ($start !== null && now()->startOfDay()->diffInDays($start, false) >= (int) $instellingen['early']['days_before']) {
                    $basis += $r['option']->totalCents() ?? 0;
                }
            }

            if ($basis > 0) {
                $kandidaten[] = [
                    'kind' => DiscountKind::Early,
                    'description' => "Vroegboekkorting {$instellingen['early']['percent']}%",
                    'amount_cents' => -intdiv($basis * (int) $instellingen['early']['percent'], 100),
                ];
            }
        }

        // Volume: vanaf N stuks in één bestelling.
        if (($instellingen['volume']['enabled'] ?? false) && count($eenmalig) >= (int) $instellingen['volume']['from_count'] && $aanbodTotaal > 0) {
            $kandidaten[] = [
                'kind' => DiscountKind::Volume,
                'description' => "Volumekorting {$instellingen['volume']['percent']}%",
                'amount_cents' => -intdiv($aanbodTotaal * (int) $instellingen['volume']['percent'], 100),
            ];
        }

        // Kortingscode, alleen als de school die aan heeft staan.
        if ($code !== null && trim($code) !== '') {
            $korting = ($instellingen['code']['enabled'] ?? false)
                ? Discount::active()->where('kind', DiscountKind::Code->value)->whereRaw('lower(code) = ?', [mb_strtolower(trim($code))])->first()
                : null;

            if ($korting === null || ! $korting->isUsable()) {
                $codeUitkomst = ['code' => trim($code), 'valid' => false, 'message' => 'Deze code is niet geldig.'];
            } elseif ($aanbodTotaal > 0) {
                $codeUitkomst = ['code' => $korting->code, 'valid' => true, 'message' => null];
                $kandidaten[] = [
                    'kind' => DiscountKind::Code,
                    'discount_id' => $korting->id,
                    'description' => "Kortingscode {$korting->code}",
                    'amount_cents' => -$korting->applyTo($aanbodTotaal),
                ];
            }
        }

        $kandidaten = array_values(array_filter($kandidaten, fn ($k) => $k['amount_cents'] < 0));

        if ($kandidaten === []) {
            return ['lines' => [], 'code' => $codeUitkomst];
        }

        // Niet stapelbaar: alleen de hoogste telt.
        if (! ($instellingen['stackable'] ?? false)) {
            usort($kandidaten, fn ($a, $b) => $a['amount_cents'] <=> $b['amount_cents']);
            $kandidaten = [$kandidaten[0]];
        }

        // Nooit meer korting dan er aanbod is.
        $som = 0;
        $lines = [];

        foreach ($kandidaten as $k) {
            $bedrag = max($k['amount_cents'], -($aanbodTotaal - $som));

            if ($bedrag >= 0) {
                continue;
            }

            $som += -$bedrag;
            $lines[] = $this->regel(OrderLineType::Discount, $k['description'], $bedrag, 0, [
                'discount_id' => $k['discount_id'] ?? null,
                'kind' => $k['kind']->value,
            ]);
        }

        return ['lines' => $lines, 'code' => $codeUitkomst];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function regel(OrderLineType $type, string $omschrijving, int $centen, int $btw, array $extra = []): array
    {
        return [
            'type' => $type->value,
            'description' => $omschrijving,
            'amount_cents' => $centen,
            'vat_rate' => $btw,
            'product_id' => $extra['product_id'] ?? null,
            'player_id' => $extra['player_id'] ?? null,
            'discount_id' => $extra['discount_id'] ?? null,
        ];
    }

    /** Heeft dit gezin deze eenmalige regel al eens betaald? */
    protected function alBetaald(?User $ouder, OrderLineType $type): bool
    {
        if ($ouder === null) {
            return false;
        }

        return OrderLine::query()
            ->where('type', $type->value)
            // Ook een order die nog op goedkeuring wacht telt: anders krijgt een
            // gezin dat twee kinderen kort na elkaar aanmeldt het twee keer.
            ->whereHas('order', fn ($q) => $q->where('user_id', $ouder->id)->whereNotIn('status', ['cancelled', 'refunded']))
            ->exists();
    }
}
