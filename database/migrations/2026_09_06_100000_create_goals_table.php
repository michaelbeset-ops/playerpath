<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Een ontwikkelingsdoel: "Uitkomen naar 80 vóór 1 december".
     *
     * start_rating is het kaartcijfer op het moment van stellen. Dat leggen we
     * vast, want "op koers" gaat over de afgelegde weg: van start naar streef,
     * afgezet tegen de verstreken tijd.
     */
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('set_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('category'); // zie App\Enums\ReportCategory
            $table->unsignedTinyInteger('start_rating')->default(0); // 0-100
            $table->unsignedTinyInteger('target_rating'); // 10-100
            $table->date('starts_on');
            $table->date('due_on');
            $table->string('note')->nullable();

            $table->string('status')->default('active'); // zie App\Enums\GoalStatus
            $table->timestamp('achieved_at')->nullable();

            $table->timestamps();

            $table->index(['player_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
