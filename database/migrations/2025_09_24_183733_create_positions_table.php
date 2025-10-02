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
        // 5) positions: 포지션(모드+TP/SL 포함)
        Schema::create('positions', function (Blueprint $t) {
            $t->id();
            $t->string('symbol');
            // 모드: REAL / DRY
            $t->string('mode')->default('REAL')
                ->checkIn(['REAL', 'DRY']);

            // 기본 체결 정보
            $t->decimal('qty', 24, 8);
            $t->decimal('entry_price', 24, 8);

            // 목표/손절
            $t->decimal('tp_price', 24, 8)->nullable();
            $t->decimal('sl_price', 24, 8)->nullable();

            // 상태: open / closed / canceled
            $t->string('status')->default('open')
                ->checkIn(['open', 'closed', 'canceled']);

            // 시간 관리
            $t->dateTime('opened_at');
            $t->dateTime('closed_at')->nullable();

            // 성능 지표(선택)
            $t->decimal('mfe', 24, 8)->nullable(); // Max Favorable Excursion
            $t->decimal('mae', 24, 8)->nullable(); // Max Adverse Excursion

            $t->text('notes')->nullable();

            $t->timestamps();

            $t->index(['symbol', 'status']);
            $t->index(['mode', 'status']);
            $t->index(['opened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
