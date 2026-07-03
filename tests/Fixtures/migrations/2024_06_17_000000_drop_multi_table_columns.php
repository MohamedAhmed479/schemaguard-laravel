<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $users): void {
            $users->dropColumn('phone');
        });

        Schema::table('orders', function (Blueprint $orders): void {
            $orders->dropColumn('legacy_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $orders): void {
            $orders->string('legacy_code')->nullable();
        });

        Schema::table('users', function (Blueprint $users): void {
            $users->string('phone')->nullable();
        });
    }
};
