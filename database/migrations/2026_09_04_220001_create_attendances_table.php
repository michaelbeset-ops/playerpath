<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eén rij per speler per training, met bewust twee losse velden:
     *
     * - registration: wat de speler/ouder vooraf zei ("ik kom" / "ik kom niet")
     * - status:       wat de trainer achteraf afvinkte (aanwezig / afwezig)
     *
     * Die twee zijn niet hetzelfde. Iemand kan zich afmelden en toch komen, of
     * niets zeggen en er gewoon staan. Ze samenvoegen zou die informatie
     * weggooien — juist het verschil is voor een school interessant.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();

            $table->string('registration')->nullable(); // attending | declined
            $table->foreignId('registered_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->nullable(); // present | absent
            $table->timestamps();

            $table->unique(['training_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
