<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het aandacht-blok wegklikken.
 *
 * Hier staat geen "verborgen ja/nee" maar een **vingerafdruk** van wat er in
 * het blok stond toen je het wegklikte. Zolang die hetzelfde is blijft het weg;
 * verandert er iets — een nieuwe mislukte betaling, een speler erbij die geen
 * rapport heeft — dan komt het terug.
 *
 * Dat is met opzet. "Voorgoed weg" zou betekenen dat een school een half jaar
 * later niet weet dat er zeven rekeningen openstaan, omdat iemand ooit één keer
 * op een kruisje drukte. Wegklikken hoort te betekenen "dit heb ik gezien",
 * niet "waarschuw me nooit meer".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('attention_dismissed', 64)->nullable()->after('dashboard_layout');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('attention_dismissed');
        });
    }
};
