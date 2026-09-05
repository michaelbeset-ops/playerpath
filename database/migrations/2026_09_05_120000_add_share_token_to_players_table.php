<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De deel-link van de spelerskaart.
     *
     * Bewust null als standaard: delen staat uit tenzij iemand het bewust
     * aanzet. Het token is willekeurig en niet te raden, en uitzetten maakt
     * hem leeg — een gedeelde link is daarna meteen dood.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->string('share_token', 64)->nullable()->unique()->after('rated_at');
            $table->timestamp('shared_at')->nullable()->after('share_token');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['share_token', 'shared_at']);
        });
    }
};
