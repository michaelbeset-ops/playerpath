<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Het datamodel van inschrijven en betalen (onderdeel 2).
 *
 * Zoveel mogelijk bestaande tabellen, uitgebreid:
 *
 * - `products` (het aanbod) krijgt een doelgroep en een aantal sessies, en
 *   **betaalvormen** in een eigen tabel: eenmalig, termijnen of abonnement,
 *   meerdere per aanbod. De kolommen `billing_type`, `amount_cents` en
 *   `interval` blijven bestaan als de standaardvorm; alles wat er al was leest
 *   die en blijft dus werken.
 * - `orders` + `order_lines`: één financiële kop per gezin, met regels voor
 *   aanbod, inschrijfgeld, kledingpakket en korting. Meerdere kinderen in
 *   één order.
 * - `enrollments` wijst naar de gekozen betaalvorm, de order en de ouder.
 * - `mandates`: alleen het kenmerk bij de betaalprovider. **Nooit een IBAN.**
 * - `discounts`, `consents` en `waitlist_invitations`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // all / keeper / field — zie App\Enums\ProductAudience
            $table->string('audience', 20)->default('all')->after('max_age');
            $table->unsignedSmallInteger('sessions_count')->nullable()->after('audience');
        });

        Schema::create('payment_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // eenmalig / termijnen / abonnement — zie App\Enums\PaymentOptionType
            $table->string('type', 20);
            $table->string('label')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedTinyInteger('installments')->nullable();
            // monthly / quarterly / yearly (abonnement) of month / week (termijnen)
            $table->string('interval', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'product_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // De ouder die betaalt en tekent.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('concept'); // zie App\Enums\OrderStatus
            $table->bigInteger('total_cents')->default(0);
            $table->bigInteger('discount_cents')->default(0);
            $table->string('discount_code', 40)->nullable();
            $table->string('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'user_id']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // offering / registration_fee / kit / trial / discount — zie App\Enums\OrderLineType
            $table->string('type', 30);
            $table->string('description');
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('discount_id')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            // Negatief bij korting, zodat de som van de regels het totaal is.
            $table->bigInteger('amount_cents');
            $table->unsignedTinyInteger('vat_rate')->default(21);
            $table->timestamps();

            $table->index(['school_id', 'order_id']);
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // family / early / volume / code — zie App\Enums\DiscountKind
            $table->string('kind', 20);
            $table->string('name');
            $table->string('code', 40)->nullable();
            $table->unsignedTinyInteger('percent')->nullable();
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedSmallInteger('max_uses')->nullable();
            $table->unsignedSmallInteger('uses')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'kind']);
        });

        Schema::table('order_lines', function (Blueprint $table) {
            $table->foreign('discount_id')->references('id')->on('discounts')->nullOnDelete();
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('payment_option_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->after('payment_option_id')->constrained()->nullOnDelete();
            // De ouder met een account; leeg zolang de aanmelding nog een
            // formulier is van iemand die nog geen account heeft.
            $table->foreignId('guardian_user_id')->nullable()->after('relationship')->constrained('users')->nullOnDelete();
        });

        Schema::create('mandates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // Per ouder: die betaalt en tekent.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30)->default('mollie');
            // Alleen kenmerken bij de provider. Geen IBAN, nergens.
            $table->string('customer_reference')->nullable();
            $table->string('mandate_reference')->nullable();
            $table->string('method', 30)->nullable();
            $table->string('status', 20)->default('pending'); // zie App\Enums\MandateStatus
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'user_id']);
            $table->unique(['provider', 'mandate_reference']);
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consent_document_id')->constrained()->cascadeOnDelete();
            // De versie zoals die was op het moment van tekenen.
            $table->unsignedSmallInteger('version');
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['school_id', 'user_id']);
            $table->index(['consent_document_id', 'version']);
        });

        Schema::create('waitlist_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('sent_at');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'enrollment_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('subscription_id')->constrained()->nullOnDelete();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('payment_option_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->after('payment_option_id')->constrained()->nullOnDelete();
        });

        // Elk bestaand aanbod krijgt zijn huidige betaalwijze als standaard
        // betaalvorm, zodat er nergens een aanbod zonder betaalvorm ontstaat.
        foreach (DB::table('products')->get() as $product) {
            DB::table('payment_options')->insert([
                'school_id' => $product->school_id,
                'product_id' => $product->id,
                'type' => $product->billing_type === 'maandelijks' ? 'abonnement' : 'eenmalig',
                'label' => null,
                'amount_cents' => $product->amount_cents,
                'installments' => null,
                'interval' => $product->billing_type === 'maandelijks' ? ($product->interval ?? 'monthly') : null,
                'is_default' => true,
                'sort' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_option_id');
            $table->dropConstrainedForeignId('enrollment_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::dropIfExists('waitlist_invitations');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('mandates');

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_option_id');
            $table->dropConstrainedForeignId('order_id');
            $table->dropConstrainedForeignId('guardian_user_id');
        });

        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropForeign(['discount_id']);
        });

        Schema::dropIfExists('discounts');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('payment_options');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['audience', 'sessions_count']);
        });
    }
};
