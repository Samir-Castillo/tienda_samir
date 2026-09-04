<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NumberingRange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 50000);
        $taxTotal = round($subtotal * 0.19, 2);

        return [
            'customer_id' => Customer::factory(),
            'numbering_range_id' => NumberingRange::factory(),
            'reference_code' => fake()->unique()->bothify('REF########'),
            'document' => 'FACT',
            'operation_type' => '10',
            'issue_date' => now(),
            'observation' => fake()->sentence(),
            'send_email' => true,
            'currency_code' => 'COP',
            'exchange_rate' => null,
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'tax_total' => $taxTotal,
            'total' => round($subtotal + $taxTotal, 2),
            'status' => InvoiceStatus::Draft,
            'factus_id' => null,
            'factus_number' => null,
            'factus_status' => null,
            'cufe' => null,
            'qr_code' => null,
            'pdf_url' => null,
            'xml_url' => null,
            'validated_at' => null,
        ];
    }

    /**
     * Indicate that the invoice is pending validation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Pending,
        ]);
    }
}
