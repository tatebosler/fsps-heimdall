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
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->timestamp('customers_arrived_at')->nullable();
            $table->timestamp('distribution_started_at')->nullable();
            $table->timestamp('estimated_entry_at')->nullable();
            $table->timestamp('original_estimated_entry_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
