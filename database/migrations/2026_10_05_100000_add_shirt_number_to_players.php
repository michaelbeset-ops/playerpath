<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een rugnummer op de kaart, zoals op een shirt.
 *
 * Optioneel en van het kind zelf (of zijn ouders): het staat groot op de
 * foto, en dat is precies het soort ding dat een kaart "van mij" maakt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedTinyInteger('shirt_number')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('shirt_number');
        });
    }
};
