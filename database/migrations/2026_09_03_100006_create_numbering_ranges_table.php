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
        Schema::create('numbering_ranges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factus_id')->nullable();
            $table->string('prefix', 20)->nullable();
            $table->string('name', 100)->nullable();
            $table->unsignedBigInteger('range_from')->nullable();
            $table->unsignedBigInteger('range_to')->nullable();
            $table->unsignedBigInteger('current_number')->nullable();
            $table->string('resolution_number', 100)->nullable();
            $table->date('resolution_date')->nullable();
            $table->boolean('active')->default(true);
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numbering_ranges');
    }
};
