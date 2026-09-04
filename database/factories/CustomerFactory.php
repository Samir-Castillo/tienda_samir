<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The code values chosen here are generic development placeholders, not
     * official Factus codes. Confirmed values must come from the Factus API.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identification_document_code' => 'DEV-ID',
            'identification' => fake()->numerify('#########'),
            'dv' => null,
            'legal_organization_code' => 'DEV-LG',
            'tribute_code' => null,
            'company' => fake()->company(),
            'trade_name' => fake()->company(),
            'names' => null,
            'address' => fake()->streetAddress(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'country_code' => 'CO',
            'municipality_code' => null,
        ];
    }

    /**
     * Indicate that the customer is a natural person.
     */
    public function naturalPerson(): static
    {
        return $this->state(fn (array $attributes) => [
            'company' => null,
            'trade_name' => null,
            'names' => fake()->name(),
        ]);
    }
}
