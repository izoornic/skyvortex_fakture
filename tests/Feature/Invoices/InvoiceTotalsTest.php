<?php

namespace Tests\Feature\Invoices;

use App\Support\InvoiceTotals;
use PHPUnit\Framework\TestCase;

class InvoiceTotalsTest extends TestCase
{
    public function test_a_plain_line(): void
    {
        $line = InvoiceTotals::forLine([
            'quantity' => 3,
            'unit_price' => 1000,
            'discount_percent' => 0,
            'vat_rate' => 20,
        ]);

        $this->assertSame(3000.00, $line['line_subtotal']);
        $this->assertSame(600.00, $line['line_vat']);
        $this->assertSame(3600.00, $line['line_total']);
    }

    public function test_the_discount_comes_off_before_vat(): void
    {
        $line = InvoiceTotals::forLine([
            'quantity' => 2,
            'unit_price' => 5000,
            'discount_percent' => 10,
            'vat_rate' => 20,
        ]);

        $this->assertSame(1000.00, $line['line_discount']);
        $this->assertSame(9000.00, $line['line_subtotal']);
        $this->assertSame(1800.00, $line['line_vat']);
        $this->assertSame(10800.00, $line['line_total']);
    }

    public function test_a_line_without_vat(): void
    {
        $line = InvoiceTotals::forLine([
            'quantity' => 1,
            'unit_price' => 1234.56,
            'vat_rate' => 0,
        ]);

        $this->assertSame(1234.56, $line['line_subtotal']);
        $this->assertSame(0.0, $line['line_vat']);
    }

    public function test_amounts_are_rounded_to_two_decimals_per_line(): void
    {
        $line = InvoiceTotals::forLine([
            'quantity' => 3,
            'unit_price' => 33.333,
            'vat_rate' => 20,
        ]);

        // 99.999 rounds to 100.00, and VAT follows from the rounded base.
        $this->assertSame(100.00, $line['line_subtotal']);
        $this->assertSame(20.00, $line['line_vat']);
    }

    public function test_the_document_total_is_the_sum_of_its_lines(): void
    {
        $items = [
            ['quantity' => 1, 'unit_price' => 100, 'vat_rate' => 20],
            ['quantity' => 2, 'unit_price' => 50.50, 'vat_rate' => 10],
            ['quantity' => 1, 'unit_price' => 999.99, 'vat_rate' => 0],
        ];

        $totals = InvoiceTotals::forDocument($items);

        $this->assertSame(1200.99, $totals['subtotal']);
        $this->assertSame(30.10, $totals['vat_total']);
        $this->assertSame(1231.09, $totals['total']);
    }

    /**
     * VAT is rounded per line and then summed, which is what the printed
     * recapitulation and the UBL breakdown in phase 2 both need. It gives a
     * different answer than rounding once over the whole base, and the stored
     * total has to agree with the lines it is printed next to.
     */
    public function test_vat_is_rounded_per_line_not_over_the_total(): void
    {
        $items = [];

        for ($i = 0; $i < 20; $i++) {
            $items[] = ['quantity' => 1, 'unit_price' => 33.33, 'vat_rate' => 20];
        }

        $totals = InvoiceTotals::forDocument($items);

        // 33.33 × 20% = 6.666 → 6.67 per line, twenty times over.
        $this->assertSame(666.60, $totals['subtotal']);
        $this->assertSame(133.40, $totals['vat_total']);

        // Rounding the whole base at once would have given 133.32.
        $this->assertNotSame(133.32, $totals['vat_total']);

        $this->assertSame(
            round($totals['subtotal'] + $totals['vat_total'], 2),
            $totals['total']
        );
    }

    public function test_conversion_to_dinars(): void
    {
        $converted = InvoiceTotals::inDinars(1000.00, 200.00, 1200.00, 117.2345);

        $this->assertSame(117234.50, $converted['subtotal_rsd']);
        $this->assertSame(23446.90, $converted['vat_total_rsd']);
        $this->assertSame(140681.40, $converted['total_rsd']);
    }
}
