<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beschikbare momenten voor privétraining.
 *
 * Een blok schrijf je je op in; een privétraining boek je. De school zet neer
 * wanneer welke trainer kan, en een ouder kiest daaruit. Vandaar een eigen
 * tabel: een moment bestaat vóórdat er iemand geboekt heeft, en dat is precies
 * het verschil met een training.
 *
 * Drie dingen die je niet moet omdraaien:
 *
 * 1. **Eén moment, één boeking.** `player_id` gevuld betekent bezet. Twee
 *    kinderen op hetzelfde uur bij dezelfde trainer is geen privétraining meer.
 * 2. **Een inschrijving houdt een moment vast** (`enrollment_id`), ook voordat
 *    de school die inschrijving heeft goedgekeurd. Anders boekt de volgende
 *    ouder hetzelfde uur terwijl de eerste nog op antwoord wacht.
 * 3. **Bij het boeken ontstaat een echte training** (`trainings.slot_id`), zodat
 *    dit uur in de agenda van de trainer staat en er gewoon aanwezigheid en een
 *    rapport bij kunnen. Daarvoor mag een training voortaan zonder groep: een
 *    privétraining is één kind, geen groep.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // De trainer die dit uur beschikbaar heeft.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('location')->nullable();

            // Wie het geboekt heeft, en waar het geld aan hangt.
            $table->foreignId('player_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('booked_at')->nullable();

            $table->timestamps();

            $table->index(['school_id', 'starts_at']);
            $table->index(['product_id', 'starts_at']);
        });

        Schema::table('trainings', function (Blueprint $table) {
            $table->foreignId('slot_id')->nullable()->after('group_id')->constrained()->nullOnDelete();
        });

        // Een privétraining hoort bij één kind en niet bij een groep. Dit is de
        // enige plek waar group_id leeg mag zijn; alles wat een groep verwacht
        // valt terug op de speler van het moment (zie Training::expectedPlayers).
        Schema::table('trainings', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropForeign(['slot_id']);
            $table->dropColumn('slot_id');
        });

        Schema::dropIfExists('slots');
    }
};
