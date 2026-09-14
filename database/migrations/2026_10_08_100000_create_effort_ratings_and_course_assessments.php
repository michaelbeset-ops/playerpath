<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De inzetkaart (variant B) en de voortgang in kleuren per cursus.
     *
     * `effort_ratings`: na elke training per kind één rij met de inzet en de
     * houding die de trainer aantikte, de punten zoals ze toen golden, en een
     * notitie. Aanwezig zelf staat in `attendances`; dit is wat erbij komt.
     * De punten staan erop zodat een export van vorig seizoen niet verandert
     * doordat de school vandaag haar puntenwaarden aanpast.
     *
     * `course_assessments`: per cursus of blok per kind een beginniveau en een
     * eindniveau per categorie, als positie op de kleurenschaal van de school.
     * `scale` legt vast hoeveel niveaus er toen waren, zodat een school die
     * later van vier naar vijf niveaus gaat het oude niveau nog kan terugrekenen.
     */
    public function up(): void
    {
        Schema::create('effort_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('effort', 8)->nullable();
            $table->string('attitude', 8)->nullable();
            $table->unsignedSmallInteger('effort_points')->default(0);
            $table->unsignedSmallInteger('attitude_points')->default(0);
            $table->text('note')->nullable();
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->unique(['training_id', 'player_id']);
            $table->index(['player_id', 'effort']);
        });

        Schema::create('course_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('moment', 8);
            $table->json('levels');
            $table->unsignedTinyInteger('scale');
            $table->text('note')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assessed_on');
            $table->timestamps();

            $table->unique(['product_id', 'player_id', 'moment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_assessments');
        Schema::dropIfExists('effort_ratings');
    }
};
