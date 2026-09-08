<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los inschrijven op een training, naast de groep.
 *
 * Een kind doet op twee manieren mee aan een training: het zit in de groep
 * (via een abonnement of omdat de school het heeft ingedeeld), of het is
 * **los ingeschreven** op precies die ene training. Dat tweede is nieuw.
 *
 * 1. **Toegangsregels op de training zelf**: wie mag meedoen (leeftijd,
 *    positie), hoeveel plekken er zijn, wat het kost, hoe je betaalt en of de
 *    school eerst goedkeurt. Op de training, niet op een aanbod ernaast: een
 *    trainer plant een training en zet er in één moeite bij dat er nog vijf
 *    kinderen van buiten de groep mogen aanschuiven.
 * 2. **`training_enrollments`**: de losse aanmeldingen, met een status
 *    (aangevraagd, bevestigd, wachtlijst, afgewezen, geannuleerd) en hoe er
 *    betaald wordt. De rekening zelf is een gewone `payment`, met een
 *    verwijzing terug: zo telt hij mee in het financiële overzicht zonder een
 *    tweede boekhouding.
 * 3. **`products.schedule`**: het vaste ritme van een doorlopend aanbod
 *    (welke dagen, hoe laat), zodat de trainingen erbij vanzelf verder
 *    ingepland kunnen worden. Tot nu toe bestond dat ritme alleen in het
 *    formulier op het moment van opslaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->boolean('open_enrollment')->default(false)->after('note');
            // Leeg of null: iedereen. Anders een lijst als ["O10", "O12"].
            $table->json('age_categories')->nullable()->after('open_enrollment');
            $table->string('audience', 10)->default('all')->after('age_categories');
            $table->unsignedInteger('capacity')->nullable()->after('audience');
            $table->unsignedBigInteger('price_cents')->default(0)->after('capacity');
            // ["online", "cash"]; null betekent allebei.
            $table->json('payment_methods')->nullable()->after('price_cents');
            $table->boolean('requires_approval')->default(false)->after('payment_methods');
        });

        Schema::create('training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            // De ouder die aanmeldde; nullOnDelete, want de aanmelding hoort bij het kind.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            // "online" of "cash"; leeg bij een gratis training of een aanvraag.
            $table->string('payment_method', 10)->nullable();
            // Wat de school erbij zei, bijvoorbeeld bij afwijzen.
            $table->string('note', 300)->nullable();
            // Wanneer iemand op de wachtlijst bericht kreeg dat er plek is.
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();

            // Eén aanmelding per kind per training; opnieuw aanmelden werkt de
            // bestaande bij.
            $table->unique(['training_id', 'player_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('training_enrollment_id')->nullable()->after('order_id')
                ->constrained('training_enrollments')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->json('schedule')->nullable()->after('sessions_count');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('schedule'));

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('training_enrollment_id');
        });

        Schema::dropIfExists('training_enrollments');

        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn([
                'open_enrollment', 'age_categories', 'audience', 'capacity',
                'price_cents', 'payment_methods', 'requires_approval',
            ]);
        });
    }
};
