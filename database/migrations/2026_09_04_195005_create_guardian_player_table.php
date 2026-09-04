<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ouder-koppeling: welke ouder (user) hoort bij welke speler.
     * Een ouder kan meerdere kinderen hebben, een speler meerdere ouders.
     */
    public function up(): void
    {
        Schema::create('guardian_player', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('relationship')->nullable(); // bijv. moeder, vader, verzorger
            $table->timestamps();

            $table->unique(['user_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_player');
    }
};
