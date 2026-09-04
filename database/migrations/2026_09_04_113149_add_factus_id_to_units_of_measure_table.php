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
        Schema::table('units_of_measure', function (Blueprint $table) {
            $table->unsignedBigInteger('factus_id')->nullable()->after('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units_of_measure', function (Blueprint $table) {
            $table->dropColumn('factus_id');
        });
    }
};
