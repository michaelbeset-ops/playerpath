<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een seizoen heeft nu een naam ("Najaar 2026", "Blok 2 · 12 weken"), niet
 * alleen een jaartal als "2026/27". Tien tekens was daarvoor te krap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_card_seasons', function (Blueprint $table) {
            $table->string('season', 60)->change();
        });
    }

    public function down(): void
    {
        Schema::table('player_card_seasons', function (Blueprint $table) {
            $table->string('season', 10)->change();
        });
    }
};
