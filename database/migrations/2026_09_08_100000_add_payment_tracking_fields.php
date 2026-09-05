<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Twee velden die pas nodig zijn zodra er echt geld loopt.
 *
 * `reminded_at` voorkomt dat een herinnering elke dag opnieuw de deur uitgaat:
 * een ouder die drie mails per week krijgt leest ze geen van alle.
 *
 * `checkout_url` bewaart de betaallink van de provider. Een ouder die de
 * browser sluit halverwege iDEAL kan zo dezelfde betaling hervatten, in plaats
 * van dat er een tweede betaling voor hetzelfde bedrag ontstaat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('paid_at');
            $table->string('checkout_url')->nullable()->after('external_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['reminded_at', 'checkout_url']);
        });
    }
};
