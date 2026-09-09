<?php

namespace Database\Factories;

use App\Enums\BillingMode;
use App\Enums\ContractFrequency;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'partner_id' => Partner::factory(),
            'name' => 'Održavanje '.fake()->word(),
            'frequency' => ContractFrequency::Monthly,
            'billing_mode' => BillingMode::Arrears,
            'generation_day' => 1,
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => null,
            'currency' => 'RSD',
            'payment_days' => 15,
            'is_active' => true,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
            'partner_id' => Partner::factory()->state(['company_id' => $company->id]),
        ]);
    }

    public function billedInAdvance(): static
    {
        return $this->state(fn (array $attributes) => ['billing_mode' => BillingMode::Advance]);
    }

    public function billedTwoMonthsInArrears(): static
    {
        return $this->state(fn (array $attributes) => ['billing_mode' => BillingMode::ArrearsTwoMonths]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function generatingOn(int $day): static
    {
        return $this->state(fn (array $attributes) => ['generation_day' => $day]);
    }

    public function running(string $startsOn, ?string $endsOn = null): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);
    }

    public function alreadyGenerated(Carbon $period): static
    {
        return $this->state(fn (array $attributes) => [
            'last_generated_year' => $period->year,
            'last_generated_month' => $period->month,
            'last_generated_at' => $period->copy(),
        ]);
    }
}
