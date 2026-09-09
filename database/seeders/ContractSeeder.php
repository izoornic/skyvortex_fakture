<?php

namespace Database\Seeders;

use App\Enums\BillingMode;
use App\Enums\ContractFrequency;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Partner;
use App\Models\VatExemptionReason;
use Illuminate\Database\Seeder;

/**
 * The standing agreements that make the monthly drafts. Matched on the partner
 * and the contract name, the pair the application already treats as one row per
 * service, so a re-run refreshes the price instead of adding a second contract
 * that would invoice the same partner twice.
 *
 * Amounts are never stored on a contract: the item below carries the monthly
 * price, and every draft computes its own totals from it.
 */
class ContractSeeder extends Seeder
{
    /**
     * What every contract below shares; only the differences are listed per row.
     */
    private const DEFAULTS = [
        'frequency' => ContractFrequency::Monthly,
        'generation_day' => 1,
        'starts_on' => '2026-09-01',
        'currency' => 'RSD',
        'payment_days' => 30,
        'valid_without_signature' => true,
        'is_active' => true,
    ];

    /**
     * Every line is one month of one service, outside the VAT system.
     */
    private const ITEM_DEFAULTS = [
        'unit_code' => 'MON',
        'unit_symbol' => 'mes',
        'quantity' => 1,
        'discount_percent' => 0,
        'vat_rate' => 0,
        'vat_category' => 'O',
    ];

    public function run(): void
    {
        $company = Company::where('pib', CompanySeeder::DIGITAL_SKYVORTEX_PIB)->first();

        if ($company === null) {
            $this->command?->warn('Digital Skyvortex nije pronađen, ugovori preskočeni.');

            return;
        }

        $partners = Partner::where('company_id', $company->getKey())->pluck('id', 'pib');

        $bankAccountId = BankAccount::where('company_id', $company->getKey())
            ->orderByDesc('is_primary')
            ->value('id');

        $exemptionReasonId = VatExemptionReason::where('code', 'PDV-RS-33')->value('id');

        foreach ($this->digitalSkyvortexContracts() as $definition) {
            $partnerId = $partners->get($definition['partner_pib']);

            if ($partnerId === null) {
                $this->command?->warn("Partner sa PIB-om {$definition['partner_pib']} ne postoji, ugovor „{$definition['name']}\" preskočen.");

                continue;
            }

            $contract = Contract::updateOrCreate(
                [
                    'company_id' => $company->getKey(),
                    'partner_id' => $partnerId,
                    'name' => $definition['name'],
                ],
                [
                    'billing_mode' => $definition['billing_mode'],
                    'bank_account_id' => $bankAccountId,
                ] + self::DEFAULTS + [
                    'company_id' => $company->getKey(),
                    'partner_id' => $partnerId,
                ],
            );

            $this->items($contract, $definition['items'], $exemptionReasonId);
        }
    }

    /**
     * Lines are matched on their position, so a re-run rewrites the price of the
     * line that is already there and drops whatever the contract has beyond the
     * list below.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function items(Contract $contract, array $items, ?int $exemptionReasonId): void
    {
        foreach ($items as $sortOrder => $item) {
            ContractItem::updateOrCreate(
                ['contract_id' => $contract->getKey(), 'sort_order' => $sortOrder],
                $item + self::ITEM_DEFAULTS + [
                    'contract_id' => $contract->getKey(),
                    'vat_exemption_reason_id' => $exemptionReasonId,
                ],
            );
        }

        $contract->items()->where('sort_order', '>=', count($items))->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function digitalSkyvortexContracts(): array
    {
        return [
            ['partner_pib' => '102054577', 'name' => 'Potal fiskalnih terminala', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Održavanje veb portala za praćenje i servis fiskalnih terminala', 'unit_price' => 46800]]],
            ['partner_pib' => '111947782', 'name' => 'Veb portal', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Redovno i vanredno održavanje veb portala i prirprema materijala za digitalni marketing', 'unit_price' => 70000]]],
            ['partner_pib' => '110806618', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1050]]],
            ['partner_pib' => '110770237', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 945]]],
            ['partner_pib' => '104097288', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2415]]],
            ['partner_pib' => '110976882', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1155]]],
            ['partner_pib' => '109074600', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1050]]],
            ['partner_pib' => '112028241', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1155]]],
            ['partner_pib' => '110898337', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1890]]],
            ['partner_pib' => '110862914', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 4725]]],
            ['partner_pib' => '110476860', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2100]]],
            ['partner_pib' => '111133965', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1470]]],
            ['partner_pib' => '112142080', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1260]]],
            ['partner_pib' => '110745428', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1050]]],
            ['partner_pib' => '111110155', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1785]]],
            ['partner_pib' => '110933472', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 3675]]],
            ['partner_pib' => '110911282', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 630]]],
            ['partner_pib' => '111222736', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2835]]],
            ['partner_pib' => '111362480', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2100]]],
            ['partner_pib' => '112449142', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 840]]],
            ['partner_pib' => '112306648', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 945]]],
            ['partner_pib' => '111535643', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2205]]],
            ['partner_pib' => '111322955', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 840]]],
            ['partner_pib' => '111356690', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 4830]]],
            ['partner_pib' => '111324889', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1785]]],
            ['partner_pib' => '111495878', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1260]]],
            ['partner_pib' => '112821023', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 7035]]],
            ['partner_pib' => '112872998', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 630]]],
            ['partner_pib' => '110862998', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 4620]]],
            ['partner_pib' => '113375200', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 6510]]],
            ['partner_pib' => '113595319', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1785]]],
            ['partner_pib' => '114013537', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 8085]]],
            ['partner_pib' => '115072116', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 4875]]],
            ['partner_pib' => '115148219', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1155]]],
            ['partner_pib' => '110488620', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 7035]]],
            ['partner_pib' => '111388447', 'name' => 'Analitika MS', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 3150]]],
            ['partner_pib' => '110498123', 'name' => 'Analitika AF', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1995]]],
            ['partner_pib' => '110635058', 'name' => 'Analitika AF', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2100]]],
            ['partner_pib' => '110417370', 'name' => 'Analitika AF', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2730]]],
            ['partner_pib' => '111221166', 'name' => 'Analitika AF', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 4620]]],
            ['partner_pib' => '107005791', 'name' => 'Analitika AF', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 7350]]],
            ['partner_pib' => '110780517', 'name' => 'Analitika AF', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2940]]],
            ['partner_pib' => '114697495', 'name' => 'Analitika AF', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1680]]],
            ['partner_pib' => '110746951', 'name' => 'Analitika AF', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1995]]],
            ['partner_pib' => '110636544', 'name' => 'Analitika AF', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2835]]],
            ['partner_pib' => '110785724', 'name' => 'Analitika AM', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1890]]],
            ['partner_pib' => '110829494', 'name' => 'Analitika AM', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2625]]],
            ['partner_pib' => '110660083', 'name' => 'Analitika AM', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 840]]],
            ['partner_pib' => '110660026', 'name' => 'Analitika AM', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2520]]],
            ['partner_pib' => '110659956', 'name' => 'Analitika AM', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1995]]],
            ['partner_pib' => '110659534', 'name' => 'Analitika AM', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1050]]],
            ['partner_pib' => '110719563', 'name' => 'Analitika AM', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1785]]],
            ['partner_pib' => '111276780', 'name' => 'Analitika AM', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 5145]]],
            ['partner_pib' => '112150100', 'name' => 'Analitika AM', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 945]]],
            ['partner_pib' => '110696856', 'name' => 'Analitika AM', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 735]]],
            ['partner_pib' => '112474186', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1155]]],
            ['partner_pib' => '112733929', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1995]]],
            ['partner_pib' => '112706913', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2205]]],
            ['partner_pib' => '112884022', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2730]]],
            ['partner_pib' => '113256514', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2520]]],
            ['partner_pib' => '109960521', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 5775]]],
            ['partner_pib' => '110549755', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1470]]],
            ['partner_pib' => '110746919', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2625]]],
            ['partner_pib' => '113262826', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1995]]],
            ['partner_pib' => '110976489', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1890]]],
            ['partner_pib' => '112426269', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 840]]],
            ['partner_pib' => '110498107', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1470]]],
            ['partner_pib' => '115319214', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1155]]],
            ['partner_pib' => '112878108', 'name' => 'Analitika DK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1890]]],
            ['partner_pib' => '110553989', 'name' => 'Analitika NA', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2730]]],
            ['partner_pib' => '111548422', 'name' => 'Analitika NA', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 7200]]],
            ['partner_pib' => '111548422', 'name' => 'Analitika NA SPA', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Održavanje veb portala "Spa booking" za kontrolu pristupa spa centru', 'unit_price' => 6000]]],
            ['partner_pib' => '104857714', 'name' => 'Analitika LP', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 7980]]],
            ['partner_pib' => '113726748', 'name' => 'Analitika MM', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 7980]]],
            ['partner_pib' => '113689544', 'name' => 'Analitika ZK', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1470]]],
            ['partner_pib' => '113048564', 'name' => 'Analitika SR', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 630]]],
            ['partner_pib' => '111101443', 'name' => 'Analitika ZD', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 2940]]],
            ['partner_pib' => '110384279', 'name' => 'Analitika DJ', 'billing_mode' => BillingMode::Advance, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 1260]]],
            ['partner_pib' => '114881092', 'name' => 'Analitika VM', 'billing_mode' => BillingMode::Arrears, 'items' => [['name' => 'Priprema mesečnih računa i održavanje veb portala za evidenciju dugovanja', 'unit_price' => 4750]]],
        ];
    }
}
