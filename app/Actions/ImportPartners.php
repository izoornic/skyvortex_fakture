<?php

namespace App\Actions;

use App\Enums\PartnerType;
use App\Models\Company;
use App\Models\Partner;
use App\Support\PartnerRules;
use Illuminate\Support\Facades\Validator;
use SplFileObject;

/**
 * Imports partners from a CSV export. Rows are validated one by one: a bad row
 * is reported and skipped, it never aborts the rest of the file.
 */
class ImportPartners
{
    /**
     * Header name in the file => attribute on the partner.
     */
    public const COLUMNS = [
        'tip' => 'type',
        'naziv' => 'name',
        'pib' => 'pib',
        'maticni_broj' => 'registration_number',
        'jmbg' => 'jmbg',
        'pib_stranog_lica' => 'vat_id',
        'jbkjs' => 'jbkjs',
        'adresa' => 'address',
        'mesto' => 'city',
        'postanski_broj' => 'postal_code',
        'drzava' => 'country_code',
        'u_sistemu_pdv' => 'in_vat_system',
        'email' => 'email',
        'telefon' => 'phone',
        'kontakt_osoba' => 'contact_person',
        'rok_placanja' => 'payment_days',
        'valuta' => 'default_currency',
        'napomena' => 'notes',
    ];

    /**
     * Rows the file must carry for anything to be importable.
     */
    public const REQUIRED_HEADERS = ['naziv'];

    /**
     * @return array{parsed: int, created: int, updated: int, errors: list<array{row: int, name: string, message: string}>}
     */
    public function handle(string $path, Company $company, bool $updateExisting = true): array
    {
        $result = ['parsed' => 0, 'created' => 0, 'updated' => 0, 'errors' => []];

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($this->detectDelimiter($path));

        $headers = null;
        $lineNumber = 0;

        foreach ($file as $row) {
            $lineNumber++;

            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($headers === null) {
                $headers = $this->normaliseHeaders($row);

                continue;
            }

            $result['parsed']++;

            $attributes = $this->mapRow($headers, $row);
            $name = (string) ($attributes['name'] ?? '');

            $type = $this->resolveType($attributes['type'] ?? null);
            $attributes['type'] = $type->value;

            $validator = Validator::make(
                $this->normaliseAttributes($attributes, $company),
                PartnerRules::for($company, $type, $this->existing($attributes, $company)),
                PartnerRules::messages(),
            );

            if ($validator->fails()) {
                $result['errors'][] = [
                    'row' => $lineNumber,
                    'name' => $name,
                    'message' => $validator->errors()->first(),
                ];

                continue;
            }

            $data = $validator->validated();
            $existing = $this->existing($attributes, $company);

            if ($existing && ! $updateExisting) {
                continue;
            }

            if ($existing) {
                $existing->update($data);
                $result['updated']++;

                continue;
            }

            $company->partners()->create($data);
            $result['created']++;
        }

        return $result;
    }

    /**
     * Headers the file is expected to carry, for the download template.
     *
     * @return list<string>
     */
    public static function templateHeaders(): array
    {
        return array_keys(self::COLUMNS);
    }

    private function existing(array $attributes, Company $company): ?Partner
    {
        $pib = $attributes['pib'] ?? null;

        if (! $pib) {
            return null;
        }

        return Partner::acrossCompanies()
            ->where('company_id', $company->id)
            ->where('pib', $pib)
            ->first();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $headers, array $row): array
    {
        $attributes = [];

        foreach ($headers as $index => $header) {
            if (! isset(self::COLUMNS[$header])) {
                continue;
            }

            $value = trim((string) ($row[$index] ?? ''));

            $attributes[self::COLUMNS[$header]] = $value === '' ? null : $value;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normaliseAttributes(array $attributes, Company $company): array
    {
        // Columns may be absent from the file, or present but empty; both fall
        // back to the same defaults.
        $attributes['country_code'] = mb_strtoupper((string) (($attributes['country_code'] ?? null) ?: 'RS'));
        $attributes['default_currency'] = mb_strtoupper((string) (($attributes['default_currency'] ?? null) ?: $company->default_currency));
        $attributes['payment_days'] = (int) (($attributes['payment_days'] ?? null) ?: 15);
        $attributes['in_vat_system'] = $this->toBool($attributes['in_vat_system'] ?? null);
        $attributes['is_active'] = true;

        foreach (['pib', 'registration_number', 'jmbg', 'jbkjs'] as $numeric) {
            if (! empty($attributes[$numeric])) {
                $attributes[$numeric] = preg_replace('/\D/', '', (string) $attributes[$numeric]);
            }
        }

        return $attributes;
    }

    private function resolveType(?string $value): PartnerType
    {
        if (! $value) {
            return PartnerType::LegalEntity;
        }

        $normalised = str_replace([' ', '-'], '_', mb_strtolower(trim($value)));

        return PartnerType::tryFrom($normalised) ?? PartnerType::LegalEntity;
    }

    private function toBool(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(mb_strtolower(trim((string) $value)), ['1', 'da', 'true', 'yes', 'y'], true);
    }

    /**
     * @param  list<string|null>  $row
     * @return list<string>
     */
    private function normaliseHeaders(array $row): array
    {
        return array_map(
            fn ($header) => str_replace([' ', '-'], '_', mb_strtolower(trim((string) $header))),
            $row,
        );
    }

    /**
     * Serbian spreadsheet exports use a semicolon far more often than a comma.
     */
    private function detectDelimiter(string $path): string
    {
        $firstLine = (string) fgets(fopen($path, 'r'));

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }
}
