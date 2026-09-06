<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wat iemand op zijn dashboard wil zien.
 *
 * Per gebruiker en niet per school: een trainer kijkt naar zijn rapporten en
 * de eigenaar naar zijn omzet. Leeg betekent "nog niets ingesteld", en dan
 * geldt de standaard uit App\Enums\DashboardTile en DashboardBlock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('dashboard_preferences')->nullable()->after('notification_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_preferences');
        });
    }
};
