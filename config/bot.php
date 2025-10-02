<?php
return [
    // DRY/NIGHT 모드 등은 별도 Guard에서 최종 판정
    'signal_cooldown_minutes' => 20,
    'signal_candidate_window_minutes' => 120,

    'daily_budget_usdt' => 10.0,  // 일일 진입 예산
    'take_profit_pct' => 1.0,   // %
    'stop_loss_pct' => 1.0,   // %
    'daily_drawdown_stop_pct' => 2.0,   // % (hit 시 HALT)

    'position_timeout_minutes' => 90,    // 보유 시간 제한

    'min_order_notional_usdt' => 5.0,   // 심볼 미설정시 기본 최소주문액
];
