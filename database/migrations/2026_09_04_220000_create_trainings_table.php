<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Een training hoort altijd bij één groep. Wie er verwacht worden zijn de
     * actieve spelers van die groep; dat wordt niet apart vastgelegd, zodat een
     * wijziging in de groep vanzelf doorwerkt.
     */
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('location')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
