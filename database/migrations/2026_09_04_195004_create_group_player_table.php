<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Een speler kan in meerdere groepen zitten (bijv. keeperstraining én
     * veldtraining). Indelen gebeurt altijd handmatig, nooit automatisch
     * op leeftijd.
     */
    public function up(): void
    {
        Schema::create('group_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_player');
    }
};
