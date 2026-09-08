<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eigen mijlpalen die een trainer toekent.
 *
 * De negen standaardmijlpalen worden afgeleid uit rapporten en aanwezigheid
 * en staan nergens opgeslagen. Een mijlpaal die de school zelf verzint
 * ("Eerste wedstrijd gekeept") heeft geen regel om uit af te leiden: iemand
 * moet hem toekennen. Dat moment — wie, wanneer, met welk woordje erbij — is
 * precies wat hier staat. De definitie zelf (naam, omschrijving) staat bij de
 * instellingen van de school, net als de keuze welke standaardmijlpalen
 * gelden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('badge_key', 40);
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('awarded_on');
            $table->string('note', 160)->nullable();
            $table->timestamps();

            // Eén keer per speler: twee keer dezelfde mijlpaal is geen tweede mijlpaal.
            $table->unique(['player_id', 'badge_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_badges');
    }
};
