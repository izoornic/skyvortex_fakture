<?php

namespace Database\Factories;

use App\Enums\VatCategory;
use App\Models\Contract;
use App\Models\ContractItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractItem>
 */
class ContractItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'sort_order' => 0,
            'name' => 'Mesečno održavanje',
            'unit_code' => 'MON',
            'unit_symbol' => 'mes',
            'quantity' => 1,
            'unit_price' => fake()->numberBetween(10000, 120000),
            'discount_percent' => 0,
            'vat_rate' => 20,
            'vat_category' => VatCategory::Standard,
        ];
    }
}
