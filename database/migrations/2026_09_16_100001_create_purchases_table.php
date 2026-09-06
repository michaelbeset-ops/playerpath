<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wat een speler heeft afgenomen: een rittenkaart, een kamp, een losse training.
 *
 * Abonnementen hebben hun eigen tabel, want die lopen door en brengen telkens
 * een nieuwe rekening voort. Dit is de andere helft: één keer afnemen, één
 * rekening, en bij een rittenkaart een saldo dat opraakt.
 *
 * De naam en het bedrag worden **overgenomen** uit het product en niet
 * opgezocht. Verhoogt de school later haar prijs of hernoemt ze het product,
 * dan blijft er staan wat er is afgesproken — precies zoals bij abonnementen.
 * `product_id` is daarom nullOnDelete: die verwijzing is voor het overzicht,
 * niet voor de waarheid.
 *
 * `attendances.purchase_id` legt vast welke beurt van welke kaart is
 * afgeschreven. Zonder die verwijzing kun je een vinkje niet meer terugdraaien
 * zonder te gokken van welke kaart de beurt kwam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('type');
            $table->bigInteger('amount_cents');
            $table->unsignedTinyInteger('vat_rate')->default(21);

            // Alleen bij een rittenkaart gevuld.
            $table->unsignedSmallInteger('credits_total')->nullable();
            $table->unsignedSmallInteger('credits_used')->default(0);

            $table->date('starts_on');
            $table->date('expires_on')->nullable();
            $table->string('status')->default('active');
            $table->string('note')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'player_id']);
            $table->index(['school_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('purchase_id')->nullable()->after('subscription_id')->constrained()->nullOnDelete();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_id');
        });

        Schema::dropIfExists('purchases');
    }
};
