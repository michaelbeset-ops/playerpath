<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een trainer uit een import heeft soms geen e-mailadres.
 *
 * Hij bestaat wel (hij staat bij trainingen), maar kan niet inloggen: zonder
 * adres is er geen inlog en geen wachtwoordmail. Een nep-adres zou een mailbox
 * suggereren die niet bestaat. Uniek blijft het: meerdere lege adressen mogen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
