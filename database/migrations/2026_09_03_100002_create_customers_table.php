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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('identification_document_code', 10);
            $table->string('identification', 30);
            $table->string('dv', 2)->nullable();
            $table->string('legal_organization_code', 10);
            $table->string('tribute_code', 10)->nullable();
            $table->string('company', 150)->nullable();
            $table->string('trade_name', 150)->nullable();
            $table->string('names', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('municipality_code', 20)->nullable();
            $table->timestamps();

            $table->index('identification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
