<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Producten worden het **aanbod** van een voetbalschool.
 *
 * Een school verkoopt geen losse trainingen maar programma's: een blok van zes
 * weken, een kamp, privétraining, small group. Dat heeft data, plekken,
 * leeftijden, een locatie en trainers — en het is tegelijk het ding met een
 * prijs. Vandaar dat het hier bij `products` blijft en er geen tweede lijst
 * naast komt: twee lijsten betekent bij elke vraag nadenken waar een kamp ook
 * alweer staat.
 *
 * Drie dingen die je niet moet omdraaien:
 *
 * 1. **Betalen is een eigenschap, geen soort.** Een blok kan €120 ineens zijn
 *    of €30 per maand. `billing_type` staat daarom los van `type`; het oude
 *    type `abonnement` wordt `doorlopend` met maandelijkse betaling.
 * 2. **"Vol" wordt niet opgeslagen.** Dat tel je uit de capaciteit en de
 *    bevestigde deelnemers. Een opgeslagen "vol" loopt uit de pas zodra iemand
 *    afzegt, en dan staat een school een plek te weigeren die er wel is.
 * 3. **Een blok krijgt een gewone groep** (`groups.product_id`). De trainingen
 *    hangen onder die groep, precies zoals nu, en daardoor blijven
 *    aanwezigheid, rapporten en de agenda werken zonder één regel wijziging.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('description');

            // Eenmalig of maandelijks; het type zegt alleen wat voor programma
            // het is.
            $table->string('billing_type')->default('eenmalig')->after('amount_cents');

            // Een blok en een kamp lopen van een datum tot een datum.
            $table->date('starts_on')->nullable()->after('interval');
            $table->date('ends_on')->nullable()->after('starts_on');

            $table->unsignedSmallInteger('capacity')->nullable()->after('ends_on');
            $table->unsignedSmallInteger('min_participants')->nullable()->after('capacity');

            $table->unsignedTinyInteger('min_age')->nullable()->after('min_participants');
            $table->unsignedTinyInteger('max_age')->nullable()->after('min_age');

            // Nu nog tekst, net als bij een training. Wordt een echte entiteit
            // zodra een school meerdere locaties beheert.
            $table->string('location')->nullable()->after('max_age');

            // concept / open / gesloten — zie App\Enums\OfferingStatus.
            $table->string('status')->default('open')->after('location');

            // Stopt een maandelijkse betaling vanzelf op de einddatum van het
            // aanbod, of loopt hij door tot iemand hem stopt? Een blok van zes
            // weken dat na afloop blijft doorschrijven is precies waar een
            // ouder boos over wordt, dus dit staat standaard aan — maar een
            // school die doorlopende training verkoopt zet hem uit.
            $table->boolean('stops_at_end')->default(true)->after('status');
        });

        // Het oude type "abonnement" beschrijft hoe je betaalt, niet wat het is.
        DB::table('products')->where('type', 'abonnement')->update([
            'type' => 'doorlopend',
            'billing_type' => 'maandelijks',
        ]);

        // De trainers bij een aanbod. Informatief, net als bij een training:
        // het bepaalt niet wie er mag afvinken.
        Schema::create('product_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'user_id']);
        });

        /*
         * Wie er meedoet aan een aanbod.
         *
         * Bewust naast `group_player`: die zegt alleen "zit in deze groep", en
         * hier hoort een status bij (bevestigd, wachtlijst, geannuleerd) plus
         * de rekening die eraan hangt. De wachtlijstvolgorde is `created_at`;
         * een los volgnummer moet je onderhouden en loopt vroeg of laat scheef.
         */
        Schema::create('participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();

            $table->string('status')->default('confirmed');

            // Waar het geld aan hangt. Nullable: een gratis proefles levert
            // geen rekening op, en een aanbod kan ook zonder betaling worden
            // toegekend.
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            // Eén deelname per speler per aanbod; opnieuw aanmelden werkt die
            // regel bij in plaats van er een tweede naast te zetten.
            $table->unique(['product_id', 'player_id']);
            $table->index(['school_id', 'status']);
        });

        Schema::table('groups', function (Blueprint $table) {
            // De groep die bij een blok of kamp hoort. Wordt automatisch
            // gemaakt en gaat mee als het aanbod verdwijnt.
            $table->foreignId('product_id')->nullable()->after('school_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        Schema::dropIfExists('participations');
        Schema::dropIfExists('product_user');

        DB::table('products')->where('type', 'doorlopend')->update(['type' => 'abonnement']);

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'photo_path', 'billing_type', 'starts_on', 'ends_on',
                'capacity', 'min_participants', 'min_age', 'max_age', 'location', 'status', 'stops_at_end',
            ]);
        });
    }
};
