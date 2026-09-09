<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PartnerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerGroup>
 */
class PartnerGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Uprava '.fake()->unique()->lastName(),
            'email' => fake()->unique()->companyEmail(),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }

    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
