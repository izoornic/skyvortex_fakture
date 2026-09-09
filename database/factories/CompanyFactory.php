<?php

namespace Database\Factories;

use App\Enums\CompanyType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'type' => CompanyType::LegalEntity,
            'name' => $name.' d.o.o.',
            // Derived from the name, the way a real short name would be. Two
            // unrelated faker names made one company look like two.
            'short_name' => rtrim(explode(' ', $name)[0], ','),
            'pib' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'registration_number' => (string) fake()->numberBetween(10000000, 99999999),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => (string) fake()->numberBetween(11000, 38000),
            'country_code' => 'RS',
            'activity_code' => (string) fake()->numberBetween(1000, 9999),
            'in_vat_system' => true,
            'default_currency' => 'RSD',
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }

    public function housingCommunity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CompanyType::HousingCommunity,
            'name' => 'Stambena zajednica '.fake()->streetAddress(),
            'short_name' => null,
            'activity_code' => null,
            'jbkjs' => null,
            'in_vat_system' => false,
        ]);
    }

    public function outsideVatSystem(): static
    {
        return $this->state(fn (array $attributes) => [
            'in_vat_system' => false,
            'vat_registered_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
