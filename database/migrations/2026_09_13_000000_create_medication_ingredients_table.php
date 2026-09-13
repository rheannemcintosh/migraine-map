<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('medication_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained()->cascadeOnDelete();
            // Null for a single-ingredient medication, where the medication name says it all.
            $table->string('name')->nullable();
            $table->decimal('dose_amount', 10, 3);
            $table->string('dose_unit', 20);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['medication_id', 'position']);
        });

        DB::table('medication_ingredients')->insertUsing(
            ['medication_id', 'dose_amount', 'dose_unit', 'position', 'created_at', 'updated_at'],
            DB::table('medications')->select(['id', 'dose_amount', 'dose_unit', DB::raw('0'), 'created_at', 'updated_at']),
        );

        Schema::table('medications', function (Blueprint $table) {
            $table->dropColumn(['dose_amount', 'dose_unit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->decimal('dose_amount', 10, 3)->default(0);
            $table->string('dose_unit', 20)->default('mg');
        });

        DB::table('medication_ingredients')
            ->where('position', 0)
            ->orderBy('id')
            ->each(function (object $ingredient): void {
                DB::table('medications')
                    ->where('id', $ingredient->medication_id)
                    ->update(['dose_amount' => $ingredient->dose_amount, 'dose_unit' => $ingredient->dose_unit]);
            });

        Schema::dropIfExists('medication_ingredients');
    }
};
