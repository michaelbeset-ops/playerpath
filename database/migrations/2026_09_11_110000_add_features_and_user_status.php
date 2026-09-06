<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature-flags per school, en accounts die je kunt deactiveren.
 *
 * De features staan als json op de school en niet in een koppeltabel: het is
 * een handvol schakelaars per school, en een tabel zou betekenen dat je bij
 * elke nieuwe feature rijen moet bijmaken voor scholen die er niets van weten.
 * Een ontbrekende sleutel betekent "standaard"; zie App\Enums\Feature.
 *
 * `deactivated_at` op de gebruiker en geen `is_active`: zo weet je ook wannéér
 * iemand eruit ging, en dat is precies wat je wilt weten als er later een
 * vraag over komt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->json('features')->nullable()->after('notes');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('email_verified_at');
        });

        Schema::create('impersonations', function (Blueprint $table) {
            $table->id();
            // Wie er keek, en bij wie. Beide nullable met nullOnDelete: het log
            // hoort te blijven staan als een account later verdwijnt.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();

            $table->string('admin_email');
            $table->string('user_email');
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonations');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('features');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
