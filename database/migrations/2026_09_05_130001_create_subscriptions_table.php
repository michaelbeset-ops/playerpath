<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Het abonnement van één speler op één abonnementsvorm.
     *
     * amount_cents staat hier apart van het plan: wijzigt de school later haar
     * tarief, dan mag dat niet met terugwerkende kracht het bedrag van een
     * lopend abonnement veranderen.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedBigInteger('amount_cents');
            $table->string('interval');
            $table->string('status')->default('active');
            $table->string('payment_method')->nullable();

            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            // Straks het abonnement-id bij de betaalprovider.
            $table->string('external_reference')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
