<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columnName = 'phone';

        Schema::table('users', function (Blueprint $schema) use ($columnName): void {
            $schema->dropColumn($columnName);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $schema): void {
            $schema->string('phone')->nullable();
        });
    }
};
