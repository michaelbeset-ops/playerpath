<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voorbereiding op automatische incasso (onderdeel 7):
 *
 * - `prenotified_at`: wanneer de vooraankondiging is verstuurd. Een incasso
 *   mag pas veertien dagen daarna.
 * - `reminder_count`: hoeveel herinneringen er na een mislukte betaling zijn
 *   gegaan, tegen het instelbare herhaalschema aan.
 * - `parent_id`: de betaling waar deze bij hoort, voor storneringskosten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('prenotified_at')->nullable()->after('reminded_at');
            $table->unsignedTinyInteger('reminder_count')->default(0)->after('prenotified_at');
            $table->foreignId('parent_id')->nullable()->after('order_id')->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['prenotified_at', 'reminder_count']);
        });
    }
};
