<?php

namespace App\Enums;

enum SignalSkipReasonEnum: string
{
    case OPEN_POSITION = 'OPEN_POSITION';        // 동일 심볼 오픈 포지션 존재
    case RISK_REJECTED = 'RISK_REJECTED';        // 리스크 정책 불가(예산/쿨다운/DD 등)
    case INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';   // 실잔고/일일예산 부족
    case NO_PRICE = 'NO_PRICE';             // 현재가/스냅샷 없음
    case BUY_FAILED = 'BUY_FAILED';           // 주문 실패(예외/거부)
    case QTY_UNRESOLVED = 'QTY_UNRESOLVED';       // 수량 산출 불가(호가/최소금액 등)
    case COOLING_DOWN = 'COOLING_DOWN';         // 심볼 쿨다운 중
    case MANUAL_SKIP = 'MANUAL_SKIP';          // 운영자 수동 스킵
    case RULE_NOT_MET = 'RULE_NOT_MET';         // 규칙 미충족(기타)
}
