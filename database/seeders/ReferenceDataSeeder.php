<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\UnitOfMeasure;
use App\Models\VatRate;
use Illuminate\Database\Seeder;

/**
 * Codebooks that are national or international standards. Safe to re-run:
 * every row is matched on its code.
 *
 * Deliberately absent: vat_exemption_reasons. Those codes come from current
 * tax regulation and the SEF specification, not from a guess made here, so the
 * table ships empty and is filled through the interface.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->currencies();
        $this->unitsOfMeasure();
        $this->vatRates();
    }

    private function currencies(): void
    {
        $currencies = [
            ['code' => 'RSD', 'name' => 'Srpski dinar', 'symbol' => 'дин.', 'sort_order' => 1],
            ['code' => 'EUR', 'name' => 'Evro', 'symbol' => '€', 'sort_order' => 2],
            ['code' => 'USD', 'name' => 'Američki dolar', 'symbol' => '$', 'sort_order' => 3],
            ['code' => 'CHF', 'name' => 'Švajcarski franak', 'symbol' => 'CHF', 'sort_order' => 4],
            ['code' => 'GBP', 'name' => 'Britanska funta', 'symbol' => '£', 'sort_order' => 5],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], $currency);
        }
    }

    /**
     * UN/ECE Recommendation 20 codes, limited to what invoicing actually uses.
     * The list is editable, so anything missing can be added later.
     */
    private function unitsOfMeasure(): void
    {
        $units = [
            ['code' => 'H87', 'name' => 'Komad', 'symbol' => 'kom', 'sort_order' => 1],
            ['code' => 'E48', 'name' => 'Usluga', 'symbol' => 'usl', 'sort_order' => 2],
            ['code' => 'HUR', 'name' => 'Čas', 'symbol' => 'h', 'sort_order' => 3],
            ['code' => 'DAY', 'name' => 'Dan', 'symbol' => 'dan', 'sort_order' => 4],
            ['code' => 'MON', 'name' => 'Mesec', 'symbol' => 'mes', 'sort_order' => 5],
            ['code' => 'ANN', 'name' => 'Godina', 'symbol' => 'god', 'sort_order' => 6],
            ['code' => 'MIN', 'name' => 'Minut', 'symbol' => 'min', 'sort_order' => 7],
            ['code' => 'KGM', 'name' => 'Kilogram', 'symbol' => 'kg', 'sort_order' => 8],
            ['code' => 'GRM', 'name' => 'Gram', 'symbol' => 'g', 'sort_order' => 9],
            ['code' => 'TNE', 'name' => 'Tona', 'symbol' => 't', 'sort_order' => 10],
            ['code' => 'LTR', 'name' => 'Litar', 'symbol' => 'l', 'sort_order' => 11],
            ['code' => 'MTR', 'name' => 'Metar', 'symbol' => 'm', 'sort_order' => 12],
            ['code' => 'MTK', 'name' => 'Kvadratni metar', 'symbol' => 'm²', 'sort_order' => 13],
            ['code' => 'MTQ', 'name' => 'Kubni metar', 'symbol' => 'm³', 'sort_order' => 14],
            ['code' => 'KMT', 'name' => 'Kilometar', 'symbol' => 'km', 'sort_order' => 15],
            ['code' => 'KWH', 'name' => 'Kilovat-čas', 'symbol' => 'kWh', 'sort_order' => 16],
            ['code' => 'SET', 'name' => 'Set', 'symbol' => 'set', 'sort_order' => 17],
            ['code' => 'PR', 'name' => 'Par', 'symbol' => 'par', 'sort_order' => 18],
            ['code' => 'NAR', 'name' => 'Broj artikala', 'symbol' => 'art', 'sort_order' => 19],
            ['code' => 'P1', 'name' => 'Procenat', 'symbol' => '%', 'sort_order' => 20],
        ];

        foreach ($units as $unit) {
            UnitOfMeasure::updateOrCreate(['code' => $unit['code']], $unit);
        }
    }

    /**
     * Rates in force in Serbia. `valid_from` is set far enough back to cover
     * historical invoices being entered; adjust once real dates are known.
     */
    private function vatRates(): void
    {
        $rates = [
            ['name' => 'Opšta stopa', 'rate' => 20.00, 'is_default' => true, 'sort_order' => 1],
            ['name' => 'Posebna stopa', 'rate' => 10.00, 'is_default' => false, 'sort_order' => 2],
            ['name' => 'Bez PDV-a', 'rate' => 0.00, 'is_default' => false, 'sort_order' => 3],
        ];

        foreach ($rates as $rate) {
            VatRate::updateOrCreate(
                ['name' => $rate['name']],
                [...$rate, 'valid_from' => '2012-10-01', 'valid_to' => null],
            );
        }
    }
}
