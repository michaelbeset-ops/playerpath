<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarieven worden producten.
 *
 * De tabel heette `plans` toen er alleen abonnementen bestonden. Een school
 * verkoopt meer dan dat: een losse training, een tienrittenkaart, een
 * jeugdkamp. Twee prijslijsten naast elkaar zou betekenen dat je bij elke
 * vraag "waar staat dat ook alweer" moet nadenken, dus het wordt er één.
 *
 * Wat erbij komt:
 *
 * - **`type`** bepaalt hoe het product zich gedraagt. Zie App\Enums\ProductType.
 * - **`credits`** en **`validity_months`** horen bij een rittenkaart: hoeveel
 *   beurten, en hoe lang geldig.
 * - **`vat_rate`** is het btw-percentage. Sportlessen vallen in Nederland vaak
 *   onder het lage tarief en soms onder een vrijstelling; dat verschilt per
 *   school en hoort dus per product instelbaar te zijn.
 * - **`interval`** wordt nullable: alleen een abonnement heeft er een.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('plans', 'products');

        Schema::table('products', function (Blueprint $table) {
            $table->string('type')->default('abonnement')->after('description');
            $table->unsignedSmallInteger('credits')->nullable()->after('amount_cents');
            $table->unsignedSmallInteger('validity_months')->nullable()->after('credits');
            $table->unsignedTinyInteger('vat_rate')->default(21)->after('validity_months');
            $table->string('interval')->nullable()->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('plan_id', 'product_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->renameColumn('plan_id', 'product_id');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->renameColumn('product_id', 'plan_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('product_id', 'plan_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['type', 'credits', 'validity_months', 'vat_rate']);
        });

        Schema::rename('products', 'plans');
    }
};
