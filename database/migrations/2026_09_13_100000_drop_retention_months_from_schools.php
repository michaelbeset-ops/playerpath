<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De bewaartermijn per school vervalt.
 *
 * Het scherm waarin een school die termijn instelde is weggehaald, en een
 * instelling die niemand meer kan wijzigen en die nergens meer gelezen wordt
 * is erger dan geen instelling: hij wekt de indruk dat er iets mee gebeurt.
 *
 * `players.deactivated_at` blijft wél staan. Dat is geen instelling maar een
 * feit — wanneer een speler is gestopt — en dat blijft bruikbaar, ook zonder
 * termijn eromheen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('retention_months');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->unsignedSmallInteger('retention_months')->nullable()->after('slug');
        });
    }
};
