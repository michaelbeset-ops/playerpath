<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mededelingen van de school aan ouders en spelers.
 *
 * `group_id` leeg betekent: de hele school. Dat is één kolom in plaats van een
 * apart begrip "doelgroep", en het leest in de query net zo makkelijk.
 *
 * `author_id` is nullable met nullOnDelete, net als bij rapporten: een trainer
 * die vertrekt neemt zijn mededelingen niet mee — die zijn verstuurd en horen
 * bij de geschiedenis van de school.
 *
 * `recipients_count` wordt bij het versturen vastgelegd en niet later opnieuw
 * berekend. Wie er tóén in de groep zat is de waarheid; een speler die vandaag
 * vertrekt hoort het bericht niet met terugwerkende kracht niet gehad te hebben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('body');

            // Hoort deze mededeling bij een afgezegde training?
            $table->foreignId('training_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();

            $table->index(['school_id', 'created_at']);
        });

        Schema::table('trainings', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('note');
            $table->string('cancellation_reason')->nullable()->after('cancelled_at');
        });

        Schema::table('users', function (Blueprint $table) {
            // Welke soorten mail je wilt ontvangen. Leeg = alles, zodat een
            // bestaande gebruiker niet stilletjes zijn meldingen kwijtraakt.
            $table->json('notification_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');

        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
