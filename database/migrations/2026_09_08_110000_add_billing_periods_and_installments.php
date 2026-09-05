<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Van abonnement naar facturen.
 *
 * `period_start` is de sleutel van een termijn. Daarmee weet de facturenloop
 * of er voor deze maand al een betaling bestaat; zonder dat veld zou een
 * tweede keer draaien een tweede rekening voor dezelfde maand opleveren, en
 * dat is het soort fout dat een school klanten kost.
 *
 * `installments` op het abonnement: een jaarbedrag in tien maandtermijnen is
 * bij sportclubs de normaalste zaak. Het bedrag blijft ondeelbaar in centen;
 * zie SplitAmount voor hoe het restant wordt verdeeld.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('due_on');
            $table->unsignedTinyInteger('installment_number')->nullable()->after('period_start');
            $table->unsignedTinyInteger('installment_total')->nullable()->after('installment_number');

            // Waarop de facturenloop controleert of er al iets bestaat.
            $table->index(['subscription_id', 'period_start', 'installment_number'], 'payments_period_index');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('installments')->nullable()->after('interval');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_period_index');
            $table->dropColumn(['period_start', 'installment_number', 'installment_total']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('installments');
        });
    }
};
