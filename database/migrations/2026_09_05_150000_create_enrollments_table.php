<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Een inschrijving: een ouder meldt zijn kind aan via het openbare
     * inschrijfformulier van de school.
     *
     * Bewust een aparte tabel en geen directe speler: de eigenaar keurt eerst
     * goed. Pas dan ontstaan de speler, het ouderaccount en het abonnement.
     * Zo komt er nooit ongevraagd iemand in het ledenbestand.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            // Het kind
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->string('position'); // zie App\Enums\PlayerPosition

            // De ouder
            $table->string('guardian_name');
            $table->string('guardian_email');
            $table->string('guardian_phone')->nullable();
            $table->string('relationship')->nullable();

            // De wens
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method')->nullable();
            $table->text('note')->nullable();

            // De afhandeling
            $table->string('status')->default('pending'); // zie App\Enums\EnrollmentStatus
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
