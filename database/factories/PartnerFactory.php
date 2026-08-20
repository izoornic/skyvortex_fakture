<?php

namespace Database\Factories;

use App\Enums\PartnerType;
use App\Models\Company;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => PartnerType::LegalEntity,
            'name' => fake()->company().' d.o.o.',
            'pib' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'registration_number' => (string) fake()->numberBetween(10000000, 99999999),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => (string) fake()->numberBetween(11000, 38000),
            'country_code' => 'RS',
            'in_vat_system' => true,
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'payment_days' => 15,
            'default_currency' => 'RSD',
            'is_active' => true,
        ];
    }

    public function entrepreneur(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PartnerType::Entrepreneur,
            'name' => fake()->lastName().' '.fake()->firstName().' pr',
        ]);
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PartnerType::Individual,
            'name' => fake()->name(),
            'pib' => null,
            'registration_number' => null,
            'jmbg' => (string) fake()->numerify(str_repeat('#', 13)),
            'in_vat_system' => false,
        ]);
    }

    public function foreign(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PartnerType::Foreign,
            'name' => fake()->company().' GmbH',
            'pib' => null,
            'registration_number' => null,
            'vat_id' => 'DE'.fake()->numerify(str_repeat('#', 9)),
            'country_code' => 'DE',
            'default_currency' => 'EUR',
            'in_vat_system' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
