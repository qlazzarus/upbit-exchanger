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
        // 6) trades: 체결 단위 로그
        Schema::create('trades', function (Blueprint $t) {
            $t->id();
            $t->foreignId('position_id')->constrained('positions')->onDelete('cascade');
            $t->string('symbol');

            // REAL / DRY (포지션 상속이지만, 독립 기록도 허용)
            $t->string('mode')->default('REAL')
                ->checkIn(['REAL', 'DRY']);

            // side: buy / sell
            $t->string('side')
                ->checkIn(['BUY', 'SELL']);

            $t->decimal('price', 24, 8);
            $t->decimal('qty', 24, 8);
            $t->decimal('fee', 24, 8)->default(0);

            $t->dateTime('executed_at');

            // 원천/주문ID 등(옵션)
            $t->string('provider')->nullable();  // ccxt, manual, webhook 등
            $t->string('external_order_id')->nullable();

            $t->timestamps();

            $t->index(['position_id']);
            $t->index(['symbol', 'executed_at']);
            $t->index(['mode', 'side']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
