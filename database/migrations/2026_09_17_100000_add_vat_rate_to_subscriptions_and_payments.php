<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Het btw-tarief mee naar beneden door de keten.
 *
 * Het staat op het product, maar een overzicht van wat er is binnengekomen mag
 * niet veranderen doordat iemand later het tarief van een product aanpast. Een
 * rekening bewaart daarom zijn eigen tarief, net zoals hij zijn eigen bedrag
 * bewaart: product → abonnement of aankoop → betaling, en elke stap neemt over.
 *
 * Bestaande rijen krijgen het tarief van het product waar ze aan hangen, en
 * anders 21%: het algemene tarief. Een leeg tarief zou betekenen dat een
 * omzetoverzicht een gat heeft, en dan klopt het totaal niet meer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('vat_rate')->default(21)->after('amount_cents');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedTinyInteger('vat_rate')->default(21)->after('amount_cents');
        });

        // Wat we nog weten overnemen; de rest blijft op het algemene tarief.
        DB::statement('
            update subscriptions
            set vat_rate = coalesce((select vat_rate from products where products.id = subscriptions.product_id), 21)
        ');

        DB::statement('
            update payments
            set vat_rate = coalesce(
                (select vat_rate from purchases where purchases.id = payments.purchase_id),
                (select vat_rate from subscriptions where subscriptions.id = payments.subscription_id),
                21
            )
        ');
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('vat_rate');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('vat_rate');
        });
    }
};
