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
        // 4) signals: 규칙 기반 시그널
        Schema::create('signals', function (Blueprint $t) {
            $t->id();
            $t->string('symbol');
            $t->dateTime('triggered_at');
            $t->string('rule_key');                       // 예: ema20_cross_ema60_vol2x
            $t->decimal('confidence', 6, 4)->default(1.0);

            // 상태: waiting / consumed / skipped
            $t->string('status')->default('waiting');
            $t->text('reason')->nullable();

            // 가격 스냅샷(옵션)
            $t->decimal('ref_price', 24, 8)->nullable();

            $t->timestamps();

            $t->index(['symbol', 'triggered_at']);
            $t->index(['status', 'triggered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signals');
    }
};
