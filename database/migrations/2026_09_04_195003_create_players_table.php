<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De speler. Leeftijd leiden we af uit date_of_birth, zodat die na een
     * seizoenswissel vanzelf klopt.
     *
     * user_id is het eigen inlogaccount van de speler (optioneel: jonge spelers
     * loggen niet zelf in, hun ouder wel).
     */
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->string('position'); // keeper | field, zie App\Enums\PlayerPosition
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
