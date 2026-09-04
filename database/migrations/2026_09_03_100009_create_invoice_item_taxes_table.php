<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_item_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')
                ->constrained('invoice_items')
                ->cascadeOnDelete();
            $table->string('code', 20);
            $table->decimal('rate', 8, 4);
            $table->boolean('is_excluded')->default(false);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index('invoice_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_item_taxes');
    }
};
