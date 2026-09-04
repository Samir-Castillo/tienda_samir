<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Reject a sale that repeats the same product more than once.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $productIds = collect($this->input('items'))
                    ->pluck('product_id')
                    ->filter()
                    ->values()
                    ->all();

                if (count($productIds) !== count(array_unique($productIds))) {
                    $validator->errors()->add('items', 'La venta no puede repetir el mismo producto.');
                }
            },
        ];
    }
}
