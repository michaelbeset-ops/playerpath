<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eén rapport = één moment waarop een trainer een speler beoordeelt.
     * De losse cijfers staan in report_scores.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->date('reported_on');
            $table->text('note')->nullable();
            $table->timestamps();

            // De spelerskaart leest steeds de nieuwste rapporten van één speler.
            $table->index(['player_id', 'reported_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
