<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eén betaling. Ook mislukte en gestorneerde blijven staan: juist die
     * wil een eigenaar terugzien.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedBigInteger('amount_cents');
            $table->string('status')->default('open'); // zie App\Enums\PaymentStatus
            $table->string('method')->nullable();
            $table->string('description');

            $table->date('due_on');
            $table->timestamp('paid_at')->nullable();

            // Straks het betaling-id bij de betaalprovider.
            $table->string('external_reference')->nullable()->unique();

            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
