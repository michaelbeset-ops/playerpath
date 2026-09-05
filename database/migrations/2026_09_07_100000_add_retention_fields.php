<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bewaartermijn (AVG).
 *
 * De school bepaalt zelf hoe lang ze de gegevens van een oud-lid bewaart.
 * Null betekent: niets ingesteld, dus signaleren we ook niets — een lege
 * instelling mag nooit stilzwijgend "alles mag weg" gaan betekenen.
 *
 * `deactivated_at` is het startpunt van die termijn. We leiden dat niet af uit
 * `updated_at`, want een naamswijziging zou de klok dan opnieuw starten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedSmallInteger('retention_months')->nullable()->after('is_active');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->index(['school_id', 'deactivated_at']);
        });

        // Spelers die al gestopt waren hebben geen startdatum en zouden dus
        // nooit in het overzicht verschijnen — juist zij zijn het punt. We
        // schatten met updated_at, de laatste keer dat er iets aan het profiel
        // veranderde. Het blijft een schatting, maar de eigenaar ziet de datum
        // staan en beslist zelf; er wordt nooit iets automatisch verwijderd.
        DB::table('players')
            ->where('is_active', false)
            ->whereNull('deactivated_at')
            ->update(['deactivated_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('retention_months');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'deactivated_at']);
            $table->dropColumn('deactivated_at');
        });
    }
};
