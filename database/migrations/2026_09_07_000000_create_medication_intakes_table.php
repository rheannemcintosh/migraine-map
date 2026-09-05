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
        Schema::create('medication_intakes', function (Blueprint $table) {
            $table->id();
            // SQL Server forbids multiple cascade paths, so intakes cascade via medications only.
            $table->foreignId('user_id')->constrained();
            $table->foreignId('medication_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();

            $table->unique(['user_id', 'medication_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_intakes');
    }
};
