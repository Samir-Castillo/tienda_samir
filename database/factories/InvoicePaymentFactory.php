<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'payment_form' => '1',
            'payment_method_code' => '10',
            'reference_code' => null,
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'due_date' => null,
        ];
    }
}
