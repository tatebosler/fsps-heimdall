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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedSmallInteger('ps_year');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('zip')->nullable();
            $table->string('vlid')->nullable();
            $table->json('shifts')->nullable();
            $table->boolean('group_zero')->default(false);
            $table->char('serial', 6);
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('scanned_by')->nullable();

            $table->unique(['ps_year', 'serial']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
