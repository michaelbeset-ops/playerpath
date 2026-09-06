<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cijfers met één decimaal.
 *
 * Een trainer denkt in "een zeven, maar wel een goeie" — dat is een 7,4. Met
 * hele cijfers moest hij kiezen tussen 7 en 8, en dan verdwijnt precies het
 * verschil dat hij zag.
 *
 * Eén decimaal en niet meer: op een schaal van tien is 7,45 een precisie die
 * niemand kan waarmaken, en het zou de schuif onbruikbaar fijn maken.
 *
 * Bestaande cijfers zijn hele getallen en blijven dat; een 7 wordt 7,0. De
 * doorrekening naar de kaart verandert daar niets aan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_scores', function (Blueprint $table) {
            $table->decimal('score', 3, 1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('report_scores', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->change();
        });
    }
};
