<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De klant bij de betaalprovider, waar het incassomandaat aan hangt.
 *
 * Op de speler en niet op het ouderaccount: een gezin met twee kinderen kan
 * per kind een ander abonnement en een andere rekening hebben, en een ouder
 * die vertrekt mag het mandaat van het kind niet meenemen.
 *
 * Provider-neutraal genoemd, net als `external_reference` op de betaling: de
 * app kent geen Mollie, alleen een kenmerk bij "de provider".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('payment_customer_reference')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('payment_customer_reference');
        });
    }
};
