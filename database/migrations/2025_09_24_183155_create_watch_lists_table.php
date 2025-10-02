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
        Schema::create('watch_lists', function (Blueprint $t) {
            $t->id();
            $t->string('symbol');                 // 예: BTC/USDT
            $t->string('base')->nullable();       // 예: BTC
            $t->string('quote')->nullable();      // 예: USDT
            $t->unsignedInteger('priority')->default(100); // 낮을수록 우선
            $t->decimal('max_entry_usdt', 18, 6)->default(0); // 심볼별 진입 상한
            $t->decimal('tick_size', 18, 8)->nullable();     // 호가 단위
            $t->decimal('step_size', 18, 8)->nullable();     // 수량 단위
            $t->boolean('enabled')->default(true);
            $t->timestamps();

            $t->unique(['symbol']);
            $t->index(['enabled', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watch_lists');
    }
};
