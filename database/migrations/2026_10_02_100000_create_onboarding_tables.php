<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alles wat een school van niets naar draaiend brengt.
 *
 * Drie dingen komen erbij, en ze horen bij elkaar:
 *
 * 1. **`schools.onboarding`** houdt bij hoe ver een school is: wanneer de
 *    startchecklist is weggeklikt, wanneer de rondleiding is gezien, wanneer de
 *    voorbeelddata is opgeruimd. Eén JSON-kolom, dezelfde afspraak als bij de
 *    functies en de rekenkern: standaarden in code, in de database alleen wat
 *    deze school daadwerkelijk deed.
 * 2. **`is_demo`** op alles wat als voorbeeld wordt neergezet. Een lege
 *    omgeving is de vijand — een nieuwe school ziet meteen gevulde
 *    spelerskaarten, een agenda en een aanbod — maar het moet met één knop
 *    weer weg kunnen, en tot die tijd moet overal zichtbaar zijn dat het
 *    voorbeeld is. Een aparte "demo"-tabel zou betekenen dat elk scherm twee
 *    bronnen moet samenvoegen; een vlaggetje op de gewone tabellen doet dat
 *    niet, en verdwijnt met de rij mee.
 * 3. **`invitations`** voor het uitnodigen van trainers en ouders. Dat ging via
 *    de wachtwoord-vergeten-route, en dat kan twee dingen niet: onthouden welk
 *    kind bij een ouder hoort, en een eigen geldigheidsduur hebben. Allebei
 *    nodig zodra je in bulk uitnodigt.
 */
return new class extends Migration
{
    /**
     * Wat een voorbeeld kan zijn.
     *
     * `reports` staat er bewust bij en niet alleen `players`: de startchecklist
     * vraagt "is er al een rapport?", en een voorbeeldrapport hoort daar niet
     * voor door te gaan. Aanwezigheid en cijfers hangen aan een training of een
     * rapport en gaan met de verwijdering mee.
     */
    protected array $demoTabellen = ['players', 'groups', 'trainings', 'reports', 'products', 'announcements', 'locations'];

    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->json('onboarding')->nullable()->after('enrollment_settings');
            // Hoe lang een uitnodiging geldig is. Per school instelbaar: een
            // vereniging die in augustus honderd ouders uitnodigt wil langer
            // dan een school die er één per week uitstuurt.
            $table->unsignedSmallInteger('invitation_valid_days')->default(14);
        });

        foreach ($this->demoTabellen as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->boolean('is_demo')->default(false);
                $table->index('is_demo');
            });
        }

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('email');
            // trainer of ouder. Een speler nodig je niet los uit: die hoort bij
            // een ouder, en een kind van acht heeft geen e-mailadres.
            $table->string('role');
            $table->string('token', 64)->unique();

            // Bij een ouder: welke kinderen er bij activatie gekoppeld worden.
            // Zonder dit moet de school na het activeren alsnog handmatig
            // koppelen, en dan is de uitnodiging het halve werk.
            $table->json('player_ids')->nullable();
            $table->string('relationship')->nullable();

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedTinyInteger('sent_count')->default(1);
            $table->timestamp('accepted_at')->nullable();
            // Het account dat eruit voortkwam; blijft staan als bewijs dat het
            // gelukt is, ook nadat de uitnodiging is verlopen.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'email']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Het welkomstregeltje voor een ouder of speler: één keer, en daarna
            // nooit meer. Per gebruiker en niet per school, want het gaat over
            // wat déze persoon al gezien heeft.
            $table->timestamp('intro_seen_at')->nullable();
        });
    }

    /**
     * Terugdraaien mag nooit halverwege blijven steken.
     *
     * Elke stap kijkt eerst of hij er nog is. Zonder die controle strandt een
     * rollback op de eerste kolom die al weg is, en blijft de rest staan terwijl
     * de migratie wél als teruggedraaid geldt.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'intro_seen_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('intro_seen_at'));
        }

        Schema::dropIfExists('invitations');

        foreach ($this->demoTabellen as $tabel) {
            if (! Schema::hasColumn($tabel, 'is_demo')) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) {
                $table->dropIndex(['is_demo']);
                $table->dropColumn('is_demo');
            });
        }

        foreach (['onboarding', 'invitation_valid_days'] as $kolom) {
            if (Schema::hasColumn('schools', $kolom)) {
                Schema::table('schools', fn (Blueprint $table) => $table->dropColumn($kolom));
            }
        }
    }
};
