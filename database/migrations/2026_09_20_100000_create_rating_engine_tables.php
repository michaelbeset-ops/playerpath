<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De rekenkern: XP, levels, leeftijdscategorie en seizoenskaarten.
 *
 * Vier dingen op een rij, en waarom elk een eigen plek heeft:
 *
 * - **`xp_events`** is de boekhouding. Elke XP die een speler krijgt is een
 *   regel: waarvoor, hoeveel, wanneer, en waar hij aan hangt. Zo is altijd
 *   uitlegbaar waar een level vandaan komt, en kan een beurt weer ingetrokken
 *   worden als een trainer zich vergist bij het afvinken.
 * - **`players.xp`** is de som, als momentopname. Zelfde afspraak als bij
 *   `overall_rating`: de waarheid staat in de boekingen.
 * - **`players.age_category`** is de categorie zoals hij het laatst is
 *   vastgesteld. Hij wordt afgeleid uit de geboortedatum, maar opgeslagen om
 *   een overgang te kunnen zíen: wijkt de berekende categorie af van de
 *   opgeslagen, dan is de speler een jaargang omhoog.
 * - **`player_card_seasons`** bewaart de kaart zoals hij was op het moment van
 *   zo'n overgang: de "Seizoen 2025/26-kaart". Een nieuwe categorie legt de lat
 *   hoger; de oude kaart blijft als herinnering staan in plaats van te
 *   verdwijnen.
 *
 * `schools.rating_settings` bevat alleen afwijkingen van de standaard; zie
 * Support\Rating\RatingSettings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();

            $table->string('source');
            $table->integer('points');
            $table->string('description');

            // Waar deze XP aan hangt, zodat hij weer ingetrokken kan worden.
            $table->nullableMorphs('reference');

            $table->date('occurred_on');
            $table->timestamps();

            $table->index(['school_id', 'player_id', 'occurred_on']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->unsignedInteger('xp')->default(0)->after('rated_at');
            $table->string('age_category', 8)->nullable()->after('xp');
            $table->date('category_changed_on')->nullable()->after('age_category');
        });

        Schema::create('player_card_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();

            $table->string('season', 10);
            $table->string('age_category', 8);
            $table->unsignedSmallInteger('overall_rating')->nullable();
            $table->json('category_ratings')->nullable();
            $table->unsignedInteger('xp')->default(0);
            $table->string('level', 16);
            $table->unsignedSmallInteger('report_count')->default(0);

            $table->timestamps();

            $table->unique(['player_id', 'season', 'age_category']);
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->json('rating_settings')->nullable()->after('birthday_message');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('rating_settings');
        });

        Schema::dropIfExists('player_card_seasons');

        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['xp', 'age_category', 'category_changed_on']);
        });

        Schema::dropIfExists('xp_events');
    }
};
