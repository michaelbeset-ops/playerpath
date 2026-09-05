<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Welke trainer(s) staan er bij een training.
     *
     * Veel-op-veel: bij een keeperstraining staat vaak een keeperstrainer
     * samen met een assistent, en een invaller komt er tijdelijk bij.
     */
    public function up(): void
    {
        Schema::create('training_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_user');
    }
};
