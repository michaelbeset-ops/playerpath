<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het pakket van een school, en een logboek van wat de platformbeheerder doet.
 *
 * Het logboek staat los van `impersonations`. Die tabel legt een *sessie* vast
 * met een begin en een eind; dit zijn losse gebeurtenissen op één moment. Twee
 * vormen, twee tabellen — samenvoegen zou betekenen dat de helft van de
 * kolommen altijd leeg is.
 *
 * `school_id` is nullable met nullOnDelete: juist bij het verwijderen van een
 * school wil je later nog kunnen zien dát het gebeurd is. Daarom staat de naam
 * er ook als tekst bij; die overleeft de school zelf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('package')->nullable()->after('is_active');
        });

        Schema::create('platform_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();

            $table->string('admin_email');
            $table->string('school_name')->nullable();
            $table->string('action');
            $table->string('summary');
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index(['school_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_logs');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('package');
        });
    }
};
