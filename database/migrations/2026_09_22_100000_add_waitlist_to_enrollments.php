<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een inschrijving voor een aanbod dat vol zat.
 *
 * Vol betekent niet "kom maar niet": het betekent wachten tot er iemand afvalt.
 * Zonder deze vlag zou zo'n aanmelding er in de inbox uitzien als elke andere,
 * en zou goedkeuren een dertiende kind in een groep van twaalf zetten — of
 * erger, een rekening sturen voor een plek die er niet is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->boolean('waitlist')->default(false)->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('waitlist');
        });
    }
};
