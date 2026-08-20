<?php

namespace App\Support;

use App\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Works out line and document amounts.
 *
 * VAT is computed per line and then summed per category and rate, which is how
 * the UBL recapitulation in phase 2 expects it — and it keeps the printed
 * breakdown consistent with the total.
 */
class InvoiceTotals
{
    /**
     * @param  array<string, mixed>  $item
     * @return array{line_subtotal: float, line_discount: float, line_vat: float, line_total: float}
     */
    public static function forLine(array $item): array
    {
        $quantity = (float) ($item['quantity'] ?? 0);
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $discountPercent = (float) ($item['discount_percent'] ?? 0);
        $vatRate = (float) ($item['vat_rate'] ?? 0);

        $gross = $quantity * $unitPrice;
        $discount = round($gross * $discountPercent / 100, 2);
        $subtotal = round($gross - $discount, 2);
        $vat = round($subtotal * $vatRate / 100, 2);

        return [
            'line_discount' => $discount,
            'line_subtotal' => $subtotal,
            'line_vat' => $vat,
            'line_total' => round($subtotal + $vat, 2),
        ];
    }

    /**
     * @param  iterable<array<string, mixed>>  $items
     * @return array{subtotal: float, discount_total: float, vat_total: float, total: float}
     */
    public static function forDocument(iterable $items): array
    {
        $subtotal = 0.0;
        $discount = 0.0;
        $vat = 0.0;

        foreach ($items as $item) {
            $line = self::forLine($item);

            $subtotal += $line['line_subtotal'];
            $discount += $line['line_discount'];
            $vat += $line['line_vat'];
        }

        $subtotal = round($subtotal, 2);
        $discount = round($discount, 2);
        $vat = round($vat, 2);

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'vat_total' => $vat,
            'total' => round($subtotal + $vat, 2),
        ];
    }

    /**
     * Amounts converted to dinars at the document's rate.
     *
     * @return array{subtotal_rsd: float, vat_total_rsd: float, total_rsd: float}
     */
    public static function inDinars(float $subtotal, float $vat, float $total, float $rate): array
    {
        return [
            'subtotal_rsd' => round($subtotal * $rate, 2),
            'vat_total_rsd' => round($vat * $rate, 2),
            'total_rsd' => round($total * $rate, 2),
        ];
    }

    /**
     * VAT breakdown per category and rate, for the printed recapitulation.
     *
     * @return Collection<int, array{vat_category: string, vat_rate: float, base: float, vat: float}>
     */
    public static function recapitulation(Invoice $invoice): Collection
    {
        return $invoice->items
            ->groupBy(fn ($item) => $item->vat_category->value.'|'.$item->vat_rate)
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'vat_category' => $first->vat_category->value,
                    'vat_rate' => (float) $first->vat_rate,
                    'base' => round((float) $group->sum('line_subtotal'), 2),
                    'vat' => round((float) $group->sum('line_vat'), 2),
                ];
            })
            ->sortByDesc('vat_rate')
            ->values();
    }
}
