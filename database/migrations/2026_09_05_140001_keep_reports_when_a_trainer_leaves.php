<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rapporten mogen niet verdwijnen als een trainer weggaat.
     *
     * trainer_id stond op cascadeOnDelete: één trainer verwijderen wiste al
     * zijn rapporten, en daarmee de opgebouwde geschiedenis van de spelers die
     * hij beoordeeld heeft. De kaart is het hart van dit product; die historie
     * hoort bij de speler, niet bij de trainer.
     *
     * Nu blijft het rapport staan met een lege trainer ("trainer verwijderd").
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['trainer_id']);
            $table->foreignId('trainer_id')->nullable()->change();
            $table->foreign('trainer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['trainer_id']);
            $table->foreignId('trainer_id')->nullable(false)->change();
            $table->foreign('trainer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
