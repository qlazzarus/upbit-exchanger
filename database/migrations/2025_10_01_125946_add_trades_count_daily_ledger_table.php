<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_ledgers', function (Blueprint $table) {
            $table->unsignedInteger('trades_count')
                ->default(0)
                ->after('pnl_pct')
                ->comment('당일 전체 거래 수');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_ledgers', function (Blueprint $table) {
            $table->dropColumn('trades_count');
        });
    }
};
