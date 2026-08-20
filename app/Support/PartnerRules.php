<?php

namespace App\Support;

use App\Enums\PartnerType;
use App\Models\Company;
use App\Models\Partner;
use Closure;
use Illuminate\Validation\Rule;

/**
 * One definition of what a valid partner is, shared by the form and the CSV
 * import so the two can never drift apart.
 */
class PartnerRules
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Company $company, ?PartnerType $type, ?Partner $ignore = null): array
    {
        $needsTaxNumber = $type?->requiresTaxNumber() ?? false;

        return [
            'type' => ['required', Rule::enum(PartnerType::class)],
            'name' => ['required', 'string', 'max:255'],

            'pib' => [
                $needsTaxNumber ? 'required' : 'nullable',
                'nullable',
                'digits:9',
                Rule::unique('partners', 'pib')
                    ->where('company_id', $company->id)
                    ->ignore($ignore),
                self::notTheIssuerItself($company),
            ],

            'registration_number' => [$needsTaxNumber ? 'required' : 'nullable', 'nullable', 'digits:8'],

            'jmbg' => [
                'nullable',
                'digits:13',
                self::onlyForIndividuals($type),
            ],

            'vat_id' => ['nullable', 'string', 'max:30'],
            'jbkjs' => ['nullable', 'digits:5'],

            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'country_code' => ['required', 'string', 'size:2'],

            'in_vat_system' => ['boolean'],

            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:255'],

            'payment_days' => ['required', 'integer', 'min:0', 'max:365'],
            'default_currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],

            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'pib.digits' => 'PIB mora imati tačno 9 cifara.',
            'pib.unique' => 'Partner sa ovim PIB-om već postoji kod ovog pravnog lica.',
            'registration_number.digits' => 'Matični broj mora imati tačno 8 cifara.',
            'jmbg.digits' => 'JMBG mora imati tačno 13 cifara.',
            'default_currency.exists' => 'Valuta ne postoji u šifarniku.',
        ];
    }

    /**
     * The issuer must not appear as its own customer — that is the "not on the
     * same invoice" rule, enforced at the point where the data is entered.
     */
    private static function notTheIssuerItself(Company $company): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($company): void {
            if ($value && (string) $value === (string) $company->pib) {
                $fail('Pravno lice ne može biti partner samo sebi.');
            }
        };
    }

    private static function onlyForIndividuals(?PartnerType $type): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($type): void {
            if ($value && ! ($type?->allowsPersonalNumber() ?? false)) {
                $fail('JMBG se unosi samo za fizička lica.');
            }
        };
    }
}
