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
        Schema::create('market_snapshots', function (Blueprint $t) {
            $t->id();
            $t->string('symbol');
            $t->dateTime('captured_at');                  // 스냅샷 시각(분 정렬)
            $t->decimal('price_last', 24, 8);
            $t->decimal('volume', 24, 8)->nullable();

            // 지표 캐시(필요 시만 사용)
            $t->decimal('ema20', 24, 8)->nullable();
            $t->decimal('ema60', 24, 8)->nullable();
            $t->decimal('vol_sma20', 24, 8)->nullable();

            $t->timestamps();

            $t->unique(['symbol', 'captured_at']);
            $t->index(['symbol', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_snapshots');
    }
};
