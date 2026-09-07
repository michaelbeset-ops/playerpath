<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wanneer kan een trainer?
 *
 * Twee tabellen, want het zijn twee verschillende dingen en die moet je niet
 * samenvoegen:
 *
 * - **`availability_rules`** is het gewone ritme: "doordeweeks 's avonds, en
 *   zaterdagochtend". Een rij betekent *beschikbaar*; wat er niet staat is dat
 *   niet. Dat is de kortste invoer die er is — je vinkt aan wat kan.
 * - **`availability_exceptions`** is wat er van dat ritme afwijkt, met een
 *   begin- en einddatum: een vakantie, een zaterdag waarop het niet uitkomt,
 *   of juist een week waarin hij extra kan.
 *
 * Een trainer die nog niets heeft ingevuld is **onbekend**, niet onbeschikbaar.
 * Zonder dat verschil zou het eigenaar-dashboard bij elke school die dit nog
 * niet gebruikt de hele planning rood kleuren, en dan kijkt niemand er meer
 * naar. `TrainerAvailability::hasSet()` maakt dat onderscheid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 1 = maandag t/m 7 = zondag (ISO-8601, zoals Carbon::dayOfWeekIso).
            $table->unsignedTinyInteger('weekday');
            $table->string('daypart');

            $table->timestamps();

            // Eén rij per dagdeel: twee keer "dinsdagavond" betekent niets extra.
            $table->unique(['user_id', 'weekday', 'daypart']);
        });

        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('starts_on');
            $table->date('ends_on');
            // Leeg betekent de hele dag. Een uitzondering per dagdeel kan ook:
            // "die zaterdag alleen 's ochtends niet".
            $table->string('daypart')->nullable();
            // Meestal een afwezigheid, maar niet altijd: een trainer kan ook
            // juist een week extra kunnen.
            $table->boolean('available')->default(false);
            $table->string('note')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('availability_rules');
    }
};
