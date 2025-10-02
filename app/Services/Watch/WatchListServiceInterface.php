<?php

namespace App\Services\Watch;

use App\Models\WatchList;
use Illuminate\Database\Eloquent\Collection;

interface WatchListServiceInterface
{
    /**
     * 사용 가능한(활성) 심볼 목록을 문자열 배열로 반환.
     * 캐싱(초단위) 허용.
     * @return array<int,string>
     */
    public function enabledSymbols(): array;

    /**
     * 워치리스트 전체 조회 (필요한 컬럼만 선별).
     * 캐싱/페이징은 구현체에서 선택.
     * @return Collection<int, WatchList>
     */
    public function all(): Collection;

    /** 단일 심볼 존재 여부 */
    public function exists(string $symbol): bool;

    /** 단일 심볼 활성/비활성 여부 */
    public function isEnabled(string $symbol): bool;

    /** 단일 심볼 추가(기존 있으면 활성화 옵션) */
    public function add(string $symbol, bool $enableIfExists = true): WatchList;

    /** 단일 심볼 제거 (soft delete 또는 비활성화 정책은 구현체 결정) */
    public function remove(string $symbol): bool;

    /** 활성화/비활성화 토글 */
    public function enable(string $symbol): bool;
    public function disable(string $symbol): bool;
    public function toggle(string $symbol): bool;

    /** 다건 추가/제거 (멱등) */
    public function bulkAdd(array $symbols, bool $enableIfExists = true): int;
    public function bulkRemove(array $symbols): int;

    /**
     * 일일 빌드(아침 루틴): 시장 상위/거래량 기준 등으로 워치리스트 재구성.
     * 기존 목록을 유지/병합하거나 갈아끼우는 옵션 제공.
     * @return int 최종 enabled 개수
     */
    public function rebuildDaily(array $options = []): int;

    /**
     * 외부 거래소 메타(호가단위/최소주문금액 등) 싱크.
     * - 예: Upbit 마켓 메타를 가져와 워치리스트 컬럼 업데이트
     * @return int 갱신 개수
     */
    public function syncExchangeMeta(array $symbols = []): int;

    /** 캐시 무효화 */
    public function clearCache(): void;
}
