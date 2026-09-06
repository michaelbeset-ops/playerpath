<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contactgegevens van de school, voor het platformbeheer.
 *
 * Dit is niet hetzelfde als de eigenaar-gebruiker: die kan wisselen, terwijl
 * het factuuradres van de school blijft. Vandaar losse velden op de school en
 * geen verwijzing naar een account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('brand_color');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone', 40)->nullable()->after('contact_email');
            $table->text('notes')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_email', 'contact_phone', 'notes']);
        });
    }
};
