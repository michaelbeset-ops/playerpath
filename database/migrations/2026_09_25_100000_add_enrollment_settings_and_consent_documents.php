<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De inschrijf- en betaalinstellingen van een school, plus de documenten
 * waarvoor een ouder bij het inschrijven toestemming geeft.
 *
 * De instellingen staan als JSON op de school, net als de rekenkern: alleen
 * afwijkingen van de standaard. Toestemmingen krijgen een eigen tabel, omdat
 * een toestemming aan een **documentversie** hangt: verandert de tekst, dan is
 * dat een nieuwe versie, en wie de oude tekende heeft niet de nieuwe getekend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->json('enrollment_settings')->nullable()->after('rating_settings');
        });

        Schema::create('consent_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // avg, beeldrecht, gedragsregels, medisch — zie ConsentDocument::SOORTEN
            $table->string('key', 40);
            $table->string('title');
            $table->text('body');
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('required')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_documents');

        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('enrollment_settings');
        });
    }
};
