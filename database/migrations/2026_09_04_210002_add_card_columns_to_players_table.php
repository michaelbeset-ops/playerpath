<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De doorgerekende spelerskaart, bijgewerkt na elk rapport.
     *
     * Dit is bewust een momentopname van iets dat je ook uit de rapporten kunt
     * afleiden: zo blijven overzichten en sorteren op rating goedkoop. De
     * waarheid blijft in reports staan; deze kolommen worden altijd opnieuw
     * berekend, nooit met de hand aangepast.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->unsignedTinyInteger('overall_rating')->nullable()->after('is_active');
            $table->json('category_ratings')->nullable()->after('overall_rating');
            $table->timestamp('rated_at')->nullable()->after('category_ratings');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['overall_rating', 'category_ratings', 'rated_at']);
        });
    }
};
