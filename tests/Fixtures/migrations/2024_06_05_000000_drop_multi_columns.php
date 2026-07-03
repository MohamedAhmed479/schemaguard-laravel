<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $t): void {
            $t->dropColumn(['street', 'zip']);
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $t): void {
            $t->string('street');
            $t->string('zip');
        });
    }
};
