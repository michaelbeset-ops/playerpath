<?php

namespace Database\Factories;

use App\Enums\PaymentOptionType;
use App\Models\PaymentOption;
use App\Models\Product;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentOption> */
class PaymentOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'product_id' => Product::factory(),
            'type' => PaymentOptionType::Eenmalig,
            'amount_cents' => 12000,
            'installments' => null,
            'interval' => null,
            'is_default' => true,
            'sort' => 0,
        ];
    }

    public function termijnen(int $aantal = 3, int $bedrag = 4000): static
    {
        return $this->state(['type' => PaymentOptionType::Termijnen, 'installments' => $aantal, 'amount_cents' => $bedrag, 'interval' => 'month']);
    }

    public function abonnement(int $bedrag = 3000, string $interval = 'monthly'): static
    {
        return $this->state(['type' => PaymentOptionType::Abonnement, 'amount_cents' => $bedrag, 'interval' => $interval]);
    }
}
