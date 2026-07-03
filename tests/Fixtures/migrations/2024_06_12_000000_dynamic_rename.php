<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $oldColumn = 'full_name';

        Schema::table('users', function (Blueprint $table) use ($oldColumn): void {
            $table->renameColumn($oldColumn, 'name');
        });
    }
};
