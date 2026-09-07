<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Locaties worden een echt ding.
 *
 * "Sportpark De Vliert" stond als los tekstveld bij elke training, elk aanbod en
 * elk moment. Dat betekent: elke keer opnieuw intikken, en drie schrijfwijzen
 * van hetzelfde veld ("De Vliert", "de vliert veld 3", "Vliert"). Een school met
 * twee locaties kon ze nergens naast elkaar zien.
 *
 * Twee dingen die je niet moet omdraaien:
 *
 * 1. **De tekst blijft staan naast de verwijzing.** `location_id` zegt welke
 *    locatie het is; `location` houdt de naam vast zoals die op dat moment was.
 *    Dat is dezelfde regel als bij een aankoop, die naam en bedrag overneemt:
 *    een locatie hernoemen mag de agenda van vorig seizoen niet herschrijven.
 * 2. **Bestaande teksten worden echte locaties.** Anders begint elke school met
 *    een lege lijst terwijl haar trainingen wél een adres hadden, en moet ze
 *    alles opnieuw intikken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('address')->nullable();
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        foreach (['trainings', 'products', 'slots'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->foreignId('location_id')->nullable()->after('location')->constrained()->nullOnDelete();
            });
        }

        $this->bestaandeTekstenOmzetten();
    }

    /**
     * Elke unieke locatietekst per school wordt een locatie, en alles wat die
     * tekst draagt gaat ernaar wijzen.
     */
    protected function bestaandeTekstenOmzetten(): void
    {
        foreach (['trainings', 'products', 'slots'] as $tabel) {
            $rijen = DB::table($tabel)
                ->select('school_id', 'location')
                ->whereNotNull('location')
                ->where('location', '!=', '')
                ->distinct()
                ->get();

            foreach ($rijen as $rij) {
                $naam = trim($rij->location);

                if ($naam === '') {
                    continue;
                }

                $id = DB::table('locations')
                    ->where('school_id', $rij->school_id)
                    ->where('name', $naam)
                    ->value('id');

                $id ??= DB::table('locations')->insertGetId([
                    'school_id' => $rij->school_id,
                    'name' => $naam,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table($tabel)
                    ->where('school_id', $rij->school_id)
                    ->where('location', $rij->location)
                    ->update(['location_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        foreach (['slots', 'products', 'trainings'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropForeign(['location_id']);
                $table->dropColumn('location_id');
            });
        }

        Schema::dropIfExists('locations');
    }
};
