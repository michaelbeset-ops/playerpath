<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Een doel buiten de zes categorieën om: "Uitverdedigen links".
     *
     * De zes categorieën komen uit de rapporten en hebben dus een cijfer om
     * aan af te meten. Een eigen doel heeft dat niet, en daarom is
     * `target_rating` voortaan leeg toegestaan: liever geen streefcijfer dan
     * een getal waar niets tegenover staat. Zo'n doel vinkt de trainer zelf af.
     */
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->string('custom_label')->nullable()->after('category');
            $table->unsignedTinyInteger('target_rating')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn('custom_label');
        });
    }
};
