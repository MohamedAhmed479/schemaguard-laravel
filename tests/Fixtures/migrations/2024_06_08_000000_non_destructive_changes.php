<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $text = "dropColumn('phone')";
        // $table->dropColumn('phone');

        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('display_name');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone');
            $table->string('email')->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
