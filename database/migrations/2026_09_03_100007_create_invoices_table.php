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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->foreignId('numbering_range_id')
                ->nullable()
                ->constrained('numbering_ranges')
                ->nullOnDelete();
            $table->string('reference_code', 100)->unique();
            $table->string('document', 10)->nullable();
            $table->string('operation_type', 10)->nullable();
            $table->dateTime('issue_date');
            $table->string('observation', 250)->nullable();
            $table->boolean('send_email')->default(true);
            $table->string('currency_code', 10)->nullable();
            $table->decimal('exchange_rate', 15, 6)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 30);
            $table->string('factus_id', 100)->nullable();
            $table->string('factus_number', 100)->nullable();
            $table->string('factus_status', 100)->nullable();
            $table->string('cufe', 255)->nullable();
            $table->text('qr_code')->nullable();
            $table->text('pdf_url')->nullable();
            $table->text('xml_url')->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
