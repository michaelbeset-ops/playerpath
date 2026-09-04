<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Een gebruiker hoort bij precies één school.
     *
     * Bewust nullable: dat laat ruimte voor een platformbeheerder zonder school.
     * Runtime is dit dicht: de middleware SetCurrentSchool weigert een ingelogde
     * gebruiker zonder school, en de global scope levert dan niets op.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
        });
    }
};
