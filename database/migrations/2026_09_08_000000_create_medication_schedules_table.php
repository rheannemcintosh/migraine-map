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
        Schema::create('medication_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained()->cascadeOnDelete();
            // Exactly one of `time_of_day` (named period) or `time` (clock time) is set per dose.
            $table->string('time_of_day', 20)->nullable();
            $table->time('time')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['medication_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_schedules');
    }
};
