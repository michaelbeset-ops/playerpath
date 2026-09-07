<?php

namespace Database\Factories;

use App\Enums\OrderLineType;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderLine> */
class OrderLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'order_id' => Order::factory(),
            'type' => OrderLineType::Offering,
            'description' => 'Keepersblok',
            'quantity' => 1,
            'amount_cents' => 12000,
            'vat_rate' => 21,
        ];
    }
}
