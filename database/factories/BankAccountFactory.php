<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'bank_name' => fake()->randomElement([
                'Banca Intesa',
                'UniCredit Bank',
                'Raiffeisen banka',
                'OTP banka',
                'NLB Komercijalna banka',
            ]),
            'account_number' => (string) fake()->unique()->numerify(str_repeat('#', 18)),
            'currency' => 'RSD',
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
