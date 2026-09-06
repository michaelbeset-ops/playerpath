<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De verjaardagsmail.
 *
 * `schools.birthday_greeting` staat standaard **uit**. Een school die dit
 * gisteren nog niet had hoort niet vanochtend ineens namens zichzelf mails te
 * versturen; dat is een berichtje met haar naam eronder en dat kiest ze zelf.
 *
 * `players.greeted_on` is de dag waarop er voor het laatst gefeliciteerd is.
 * Daarmee is de dagelijkse ronde idempotent: twee keer draaien levert geen
 * tweede felicitatie op, en dat is precies het soort fout dat een school haar
 * klanten laat opmerken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('birthday_greeting')->default(false)->after('features');
            $table->string('birthday_message')->nullable()->after('birthday_greeting');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->date('greeted_on')->nullable()->after('deactivated_at');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('greeted_on');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['birthday_greeting', 'birthday_message']);
        });
    }
};
