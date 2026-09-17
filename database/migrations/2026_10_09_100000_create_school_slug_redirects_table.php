<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oude adressen van een inschrijfpagina.
 *
 * Een school die haar slug wijzigt heeft de oude link al op haar website, in
 * een nieuwsbrief en op een flyer staan. Die hoort door te sturen naar het
 * nieuwe adres in plaats van een 404 te geven. Een slug is uniek over alle
 * scholen: hij wijst naar precies één school.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_slug_redirects');
    }
};
