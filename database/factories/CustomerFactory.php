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
     * The default is a natural person using the official Factus codes used in
     * the sandbox test (CC, PN, tribute 01 = IVA, municipality San Gil id 980).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identification_document_code' => 'CC',
            'identification' => fake()->numerify('#########'),
            'dv' => null,
            'legal_organization_code' => 'PN',
            'tribute_code' => '01',
            'company' => null,
            'trade_name' => null,
            'names' => fake()->name(),
            'address' => fake()->streetAddress(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'country_code' => 'CO',
            'municipality_code' => '68679',
            'factus_municipality_id' => 980,
            'responsibilities' => ['R-99-PN'],
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

    /**
     * Indicate that the customer is a legal person.
     */
    public function legalPerson(): static
    {
        return $this->state(fn (array $attributes) => [
            'identification_document_code' => 'NIT',
            'dv' => fake()->digit(),
            'legal_organization_code' => 'PJ',
            'company' => fake()->company(),
            'trade_name' => fake()->company(),
            'names' => null,
        ]);
    }
}
