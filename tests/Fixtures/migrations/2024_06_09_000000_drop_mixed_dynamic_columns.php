<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columnName = 'zip';

        Schema::table('users', function (Blueprint $table) use ($columnName): void {
            $table->dropColumn(['street', $columnName]);
        });
    }
};
