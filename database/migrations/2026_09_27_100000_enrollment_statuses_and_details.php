<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De statusmachine van inschrijvingen (onderdeel 4) en de extra aanmeldvelden.
 *
 * De oude drie statussen worden de nieuwe: "nieuw" was wachten op goedkeuring,
 * "goedgekeurd" is bevestigd. Afgewezen blijft afgewezen.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('enrollments')->where('status', 'pending')->update(['status' => 'awaiting_approval']);
        DB::table('enrollments')->where('status', 'approved')->update(['status' => 'confirmed']);

        Schema::table('enrollments', function (Blueprint $table) {
            // Kledingmaat, niveau, medische bijzonderheden: welke er gevraagd
            // worden staat in de inschrijfinstellingen, dus het is een vrij vak.
            $table->json('details')->nullable()->after('note');
            $table->timestamp('confirmed_at')->nullable()->after('handled_at');
            $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
            $table->bigInteger('refund_cents')->nullable()->after('cancellation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['details', 'confirmed_at', 'cancelled_at', 'cancellation_reason', 'refund_cents']);
        });

        DB::table('enrollments')->where('status', 'awaiting_approval')->update(['status' => 'pending']);
        DB::table('enrollments')->where('status', 'confirmed')->update(['status' => 'approved']);
    }
};
