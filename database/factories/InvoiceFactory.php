<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issueDate = now();

        return [
            'company_id' => Company::factory(),
            'partner_id' => Partner::factory(),
            'type' => DocumentType::Invoice,
            'status' => InvoiceStatus::Draft,
            'period_year' => $issueDate->year,
            'period_month' => $issueDate->month,
            'issue_date' => $issueDate->toDateString(),
            'supply_date' => $issueDate->toDateString(),
            'due_date' => $issueDate->copy()->addDays(15)->toDateString(),
            'currency' => 'RSD',
            'exchange_rate' => 1,
            'place_of_issue' => 'Beograd',
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
            'partner_id' => Partner::factory()->state(['company_id' => $company->id]),
        ]);
    }

    public function inPeriod(int $year, int $month): static
    {
        return $this->state(fn (array $attributes) => [
            'period_year' => $year,
            'period_month' => $month,
        ]);
    }

    public function inCurrency(string $currency, float $rate): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
            'exchange_rate' => $rate,
        ]);
    }
}
