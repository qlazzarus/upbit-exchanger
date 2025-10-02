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
        Schema::create('daily_ledgers', function (Blueprint $t) {
            $t->id();
            $t->date('date'); // KST 하루 기준

            $t->decimal('equity_start_usdt', 24, 8)->nullable();
            $t->decimal('equity_end_usdt', 24, 8)->nullable();

            $t->decimal('pnl_usdt', 24, 8)->nullable();  // end - start - 입출금
            $t->decimal('pnl_pct', 10, 6)->nullable();   // pnl / start

            $t->unsignedInteger('wins')->default(0);
            $t->unsignedInteger('losses')->default(0);

            $t->boolean('trading_halt')->default(false); // 일시 중단 여부(리스크 한도 hit)
            $t->string('halt_reason')->nullable();       // ex) dd(-2%) hit

            $t->timestamps();

            $t->unique(['date']);
            $t->index(['date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_ledgers');
    }
};
