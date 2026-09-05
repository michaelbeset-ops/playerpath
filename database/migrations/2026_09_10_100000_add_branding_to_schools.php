<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Huisstijl per school.
 *
 * Bewust twee velden en geen vrij CSS-veld: een school kiest een logo en één
 * merkkleur, de rest van de huisstijl blijft van PlayerPath. Zo kan een school
 * het product niet per ongeluk onleesbaar maken, en blijven statuskleuren
 * (waarschuwing, fout) overal hetzelfde betekenen.
 *
 * De slug bestaat al en is het subdomein. Die bepaalt alleen de branding en de
 * inlogpagina — nooit welke data je ziet. Zie CLAUDE.md 3.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('slug');
            $table->string('brand_color', 7)->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'brand_color']);
        });
    }
};
