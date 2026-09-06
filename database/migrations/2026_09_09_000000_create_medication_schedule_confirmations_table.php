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
        Schema::create('medication_schedule_confirmations', function (Blueprint $table) {
            $table->id();
            // SQL Server forbids multiple cascade paths, so confirmations cascade via schedules only.
            $table->foreignId('user_id')->constrained();
            $table->foreignId('medication_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->unique(['user_id', 'medication_schedule_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_schedule_confirmations');
    }
};
