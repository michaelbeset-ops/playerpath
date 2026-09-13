<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De kind-link: een tweede link naar de kaart, voor het kind zelf.
     *
     * Naast de publieke deel-link (voornaam + initiaal, voor internet) is dit
     * de privélink die een ouder aan zijn kind geeft: zonder inlog, met de
     * hele kaart. Een eigen token, zodat de ene link aan of uit kan zonder
     * de andere te raken. Standaard uit, en leeg zodra iemand hem intrekt.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('child_token', 64)->nullable()->unique()->after('shared_at');
            $table->timestamp('child_link_at')->nullable()->after('child_token');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['child_token', 'child_link_at']);
        });
    }
};
