<?php

namespace Database\Seeders;

use App\Enums\CompanyType;
use App\Enums\DocumentType;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\InvoiceNumberSequence;
use App\Models\VatExemptionReason;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The legal entities that issue documents in this installation, with their real
 * registry data. Every company is matched on its PIB and every account on its
 * number, so the seeder can be re-run without doubling a row.
 */
class CompanySeeder extends Seeder
{
    /**
     * The company the whole installation is built around; other seeders look it
     * up by this number.
     */
    public const DIGITAL_SKYVORTEX_PIB = '114362087';

    public function run(): void
    {
        foreach ($this->companies() as $definition) {
            $company = $this->company($definition);

            foreach ($definition['bank_accounts'] as $account) {
                $this->bankAccount($company, $account);
            }

            foreach ($definition['number_sequences'] ?? [] as $sequence) {
                $this->numberSequence($company, $sequence);
            }

            if (isset($definition['logo'])) {
                $this->logo($company, $definition['logo']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function company(array $definition): Company
    {
        $attributes = $definition['company'];

        if (isset($attributes['vat_exemption_reason_code'])) {
            $attributes['vat_exemption_reason_id'] = $this
                ->vatExemptionReason($attributes['vat_exemption_reason_code'])
                ->getKey();

            unset($attributes['vat_exemption_reason_code']);
        }

        return Company::updateOrCreate(['pib' => $attributes['pib']], $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function bankAccount(Company $company, array $attributes): void
    {
        BankAccount::updateOrCreate(
            ['company_id' => $company->getKey(), 'account_number' => $attributes['account_number']],
            $attributes + ['company_id' => $company->getKey()],
        );
    }

    /**
     * Numbering continues after documents issued outside the application, so
     * `last_number` is the number before the first one this installation hands
     * out. Created once and never touched again: a re-run must not push the
     * counter back over invoices already issued here.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function numberSequence(Company $company, array $attributes): void
    {
        InvoiceNumberSequence::firstOrCreate(
            [
                'company_id' => $company->getKey(),
                'type' => $attributes['type'],
                'year' => $attributes['year'],
            ],
            ['last_number' => $attributes['last_number']],
        );
    }

    /**
     * Logos live outside the public directory under `logos/{company}/`, so the
     * file is copied into place the same way an upload would put it there.
     */
    private function logo(Company $company, string $file): void
    {
        $source = database_path('seeders/assets/'.$file);

        if (! is_file($source)) {
            $this->command?->warn("Logotip {$file} ne postoji, preskočen.");

            return;
        }

        $disk = Storage::disk(Company::LOGO_DISK);

        if ($company->logo_path !== null) {
            $disk->delete($company->logo_path);
        }

        $path = sprintf(
            '%s/%d/%s.%s',
            Company::LOGO_DIRECTORY,
            $company->getKey(),
            Str::ulid(),
            pathinfo($file, PATHINFO_EXTENSION),
        );

        $disk->put($path, (string) file_get_contents($source));

        $company->update(['logo_path' => $path]);
    }

    /**
     * These codes come from tax regulation, not from a guess, which is why
     * `ReferenceDataSeeder` ships none. This one is seeded because a real
     * company below invoices without VAT and needs its legal basis printed.
     */
    private function vatExemptionReason(string $code): VatExemptionReason
    {
        return VatExemptionReason::updateOrCreate(['code' => $code], match ($code) {
            'PDV-RS-33' => [
                'vat_category' => 'O',
                'description' => 'Promet nije predmet oporezivanja PDV-om u skladu sa članom 33. Zakona o PDV.',
                'is_active' => true,
                'sort_order' => 0,
            ],
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function companies(): array
    {
        return [
            [
                'company' => [
                    'type' => CompanyType::Entrepreneur,
                    'name' => 'Ivan Zornić Pr Veb portali Digital Skyvortex',
                    'short_name' => 'Digital Skyvortex',
                    'pib' => self::DIGITAL_SKYVORTEX_PIB,
                    'registration_number' => '67516184',
                    'address' => 'Kopaonička 7V-16',
                    'city' => 'Kragujevac',
                    'postal_code' => '34000',
                    'country_code' => 'RS',
                    'activity_code' => '6313',
                    'in_vat_system' => false,
                    'vat_exemption_reason_code' => 'PDV-RS-33',
                    'default_currency' => 'RSD',
                    'payment_code' => Company::DEFAULT_PAYMENT_CODE,
                    'email' => 'izornic@gmail.com',
                    'phone' => '+381 63 7265 275',
                    'is_active' => true,
                ],
                'bank_accounts' => [
                    [
                        'bank_name' => 'OTP banka Srbija a.d.',
                        'account_number' => '325-9500700213457-24',
                        'currency' => 'RSD',
                        'is_primary' => true,
                        'sort_order' => 0,
                    ],
                ],
                // Invoicing continues at 2026-0639.
                'number_sequences' => [
                    [
                        'type' => DocumentType::Invoice,
                        'year' => 2026,
                        'last_number' => 638,
                    ],
                ],
                'logo' => 'digital-skyvortex.svg',
            ],
        ];
    }
}
