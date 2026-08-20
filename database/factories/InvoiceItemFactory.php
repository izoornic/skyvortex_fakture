<?php

namespace Database\Factories;

use App\Enums\VatCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\InvoiceTotals;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->numberBetween(500, 50000);

        $amounts = InvoiceTotals::forLine([
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_percent' => 0,
            'vat_rate' => 20,
        ]);

        return [
            'invoice_id' => Invoice::factory(),
            'sort_order' => 0,
            'name' => fake()->sentence(3),
            'unit_code' => 'H87',
            'unit_symbol' => 'kom',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_percent' => 0,
            'vat_rate' => 20,
            'vat_category' => VatCategory::Standard,
            ...$amounts,
        ];
    }
}
