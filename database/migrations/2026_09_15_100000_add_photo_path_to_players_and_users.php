<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profielfoto's voor spelers en voor accounts.
 *
 * Twee kolommen en niet één gedeelde tabel: een speler is een profiel en een
 * ouder is een account, en dat onderscheid loopt door de hele app heen. Zie
 * "Speler versus gebruiker" in CLAUDE.md.
 *
 * Alleen het pad; het bestand zelf staat op de publieke schijf. Dat is bewust,
 * want de foto van een kind komt ook op zijn spelerskaart en die kaart is te
 * delen. Wie de link niet heeft, vindt de foto niet: het bestand krijgt een
 * onraadbare naam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('position');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
