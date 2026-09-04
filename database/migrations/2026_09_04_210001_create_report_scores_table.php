<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eén cijfer per categorie. Schaal 1 t/m 10, zoals een rapportcijfer;
     * op de spelerskaart wordt dat maal tien (een 8 leest als 80).
     */
    public function up(): void
    {
        Schema::create('report_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // zie App\Enums\ReportCategory
            $table->unsignedTinyInteger('score'); // 1 t/m 10
            $table->timestamps();

            $table->unique(['report_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_scores');
    }
};
