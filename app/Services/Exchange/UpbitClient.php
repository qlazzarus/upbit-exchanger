<?php

namespace App\Services\Exchange;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Random\RandomException;

/**
 * Minimal Upbit REST client for balances, last price, and market orders.
 * - Auth: HS256 JWT (no external libs; inline encoder)
 * - Symbols: accepts "BTC/USDT" or native "USDT-BTC"; normalizes to Upbit format "BASE-QUOTE" (e.g., USDT-BTC, KRW-BTC).
 * - Market orders:
 *   - Buy (quote amount): ord_type=price, price=quote amount, volume omitted
 *   - Sell (by volume):   ord_type=market, volume=base amount,  price omitted
 */
class UpbitClient implements ExchangeClientInterface
{
    private string $baseUrl;
    private string $accessKey;
    private string $secretKey;
    private int $timeout;

    public function __construct()
    {
        $cfg = config('exchange.upbit', []);
        $this->baseUrl = $cfg['base'] ?? 'https://api.upbit.com';
        $this->accessKey = $cfg['key'] ?? '';
        $this->secretKey = $cfg['secret'] ?? '';
        $this->timeout = (int)($cfg['timeout'] ?? 5);
    }

    /** @return array<int, array{asset:string,free:float,locked:float,total:float}>
     * @throws ConnectionException
     */
    public function fetchBalances(): array
    {
        $resp = $this->signedGet('/v1/accounts');
        if (!$resp->successful()) {
            Log::warning('[UpbitClient] fetchBalances failed', ['status' => $resp->status(), 'body' => $resp->body()]);
            return [];
        }
        /** @var array<int, array{currency:string,balance:string,locked:string,avg_buy_price?:string}> $data */
        $data = $resp->json();
        $out = [];
        foreach ($data as $row) {
            $asset = strtoupper($row['currency']);
            $free = (float)($row['balance'] ?? 0);
            $locked = (float)($row['locked'] ?? 0);
            $out[] = [
                'asset' => $asset,
                'free' => $free,
                'locked' => $locked,
                'total' => $free + $locked,
            ];
        }
        return $out;
    }

    /**
     * @throws ConnectionException
     */
    public function fetchFree(string $asset): ?float
    {
        $asset = strtoupper($asset);
        foreach ($this->fetchBalances() as $b) {
            if ($b['asset'] === $asset) {
                return $b['free'];
            }
        }
        return null;
    }

    /**
     * Last trade price for the given symbol.
     * @param string $symbol e.g. "BTC/USDT", "USDT-BTC", "KRW-BTC"
     * @throws ConnectionException
     */
    public function fetchLastPrice(string $symbol): ?float
    {
        $market = $this->normalizeSymbol($symbol);
        $resp = Http::timeout($this->timeout)
            ->get($this->baseUrl . '/v1/ticker', ['markets' => $market]);
        if (!$resp->successful()) {
            Log::warning('[UpbitClient] fetchLastPrice failed', ['market' => $market, 'status' => $resp->status(), 'body' => $resp->body()]);
            return null;
        }
        $arr = $resp->json();
        // Response: [{ market: "USDT-BTC", trade_price: 123.45, ... }]
        return isset($arr[0]['trade_price']) ? (float)$arr[0]['trade_price'] : null;
    }

    /**
     * Fetch minute candles (OHLCV) from Upbit.
     * Endpoint: GET /v1/candles/minutes/{unit}
     * @param string $symbol e.g. "BTC/USDT" or "USDT-BTC"
     * @param int $unit Supported: 1,3,5,10,15,30,60,240
     * @param int $count Number of candles (1~200)
     * @param string|null $to ISO8601 end time (exclusive, KST). If null -> most recent.
     * @return array<int, array>
     * @throws ConnectionException
     */
    public function fetchMinuteCandles(string $symbol, int $unit = 1, int $count = 60, ?string $to = null): array
    {
        $market = $this->normalizeSymbol($symbol);
        $count = max(1, min(200, $count));

        $query = ['market' => $market, 'count' => $count];
        if ($to) {
            $query['to'] = $to;
        }

        $resp = Http::timeout($this->timeout)
            ->get($this->baseUrl . "/v1/candles/minutes/{$unit}", $query);

        if (!$resp->successful()) {
            Log::warning('[UpbitClient] fetchMinuteCandles failed', [
                'market' => $market,
                'unit' => $unit,
                'count' => $count,
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);
            return [];
        }

        $data = $resp->json();
        return is_array($data) ? $data : [];
    }

    /**
     * Market buy using quote currency amount (USDT/KRW).
     * Upbit requires ord_type=price with `price` set to the quote amount.
     * Volume should be omitted in this case.
     * @return array{market:string,side:string,price:float,uuid?:string}
     */
    public function createMarketBuy(string $symbol, float $qty): array
    {
        $market = $this->normalizeSymbol($symbol);
        $params = [
            'market' => $market,
            'side' => 'bid',   // buy
            'ord_type' => 'price', // quote amount buy
            'price' => (string)$qty,
        ];

        $resp = $this->signedPost('/v1/orders', $params);
        if (!$resp->successful()) {
            Log::warning('[UpbitClient] createMarketBuy failed', ['market' => $market, 'status' => $resp->status(), 'body' => $resp->body()]);
        }

        $data = $resp->json();
        return [
            'market' => $market,
            'side' => 'buy',
            'price' => $qty,
            'uuid' => is_array($data) && isset($data['uuid']) ? (string)$data['uuid'] : null,
        ];
    }

    /**
     * Market sell by base asset volume.
     * Upbit requires ord_type=market with `volume` set to base amount.
     * @return array{market:string,side:string,volume:float,uuid?:string}
     */
    public function createMarketSell(string $symbol, float $qty): array
    {
        $market = $this->normalizeSymbol($symbol);
        $params = [
            'market' => $market,
            'side' => 'ask',    // sell
            'ord_type' => 'market', // market sell by volume
            'volume' => (string)$qty,
        ];

        $resp = $this->signedPost('/v1/orders', $params);
        if (!$resp->successful()) {
            Log::warning('[UpbitClient] createMarketSell failed', ['market' => $market, 'status' => $resp->status(), 'body' => $resp->body()]);
        }

        $data = $resp->json();
        return [
            'market' => $market,
            'side' => 'sell',
            'volume' => $qty,
            'uuid' => is_array($data) && isset($data['uuid']) ? (string)$data['uuid'] : null,
        ];
    }

    /**
     * Cancel an order by UUID.
     * Upbit: DELETE /v1/order?uuid=...
     */
    public function cancelOrder(string $uuid): bool
    {
        $params = ['uuid' => $uuid];
        $resp = $this->signedDelete('/v1/order', $params);
        if (!$resp->successful()) {
            Log::warning('[UpbitClient] cancelOrder failed', [
                'uuid' => $uuid,
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);
            return false;
        }

        // Successful response returns canceled order info. We consider 2xx as success.
        return true;
    }

    // -------------------------
    // Internal helpers
    // -------------------------

    /** Build Authorization header and GET
     * @throws ConnectionException
     */
    private function signedGet(string $path, array $query = []): PromiseInterface|Response
    {
        $headers = $this->authHeaders($query);
        return Http::timeout($this->timeout)
            ->withHeaders($headers)
            ->get($this->baseUrl . $path, $query);
    }

    /** Build Authorization header and POST (JSON body for private API) */
    private function signedPost(string $path, array $params)
    {
        $headers = $this->authHeaders($params);
        return Http::timeout($this->timeout)
            ->withHeaders($headers)
            ->post($this->baseUrl . $path, $params);
    }

    /** Build Authorization header and DELETE */
    private function signedDelete(string $path, array $params = [])
    {
        $headers = $this->authHeaders($params);
        return Http::timeout($this->timeout)
            ->withHeaders($headers)
            ->delete($this->baseUrl . $path, $params);
    }

    /** Create Upbit JWT Authorization header */
    private function authHeaders(array $params = []): array
    {
        $payload = [
            'access_key' => $this->accessKey,
            'nonce' => $this->uuid4(),
        ];

        if (!empty($params)) {
            $query = http_build_query($params);
            $payload['query_hash'] = hash('sha512', $query);
            $payload['query_hash_alg'] = 'SHA512';
        }

        $jwt = $this->jwtEncode($payload, $this->secretKey);

        return [
            'Authorization' => 'Bearer ' . $jwt,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /** Normalize symbols: accepts "BTC/USDT", "USDT-BTC", "KRW-BTC" -> returns Upbit market string */
    private function normalizeSymbol(string $symbol): string
    {
        $s = strtoupper(trim($symbol));
        if (str_contains($s, '/')) {
            [$base, $quote] = array_map('trim', explode('/', $s, 2));
            // Upbit format is QUOTE-BASE (e.g., USDT-BTC)
            return $quote . '-' . $base;
        }
        // Already Upbit format?
        if (preg_match('/^(USDT|KRW|BTC|ETH)-[A-Z0-9-]+$/', $s)) {
            return $s;
        }
        // Fallback: assume KRW-XXX
        return 'KRW-' . $s;
    }

    /** Simple HS256 JWT encoder (no external deps) */
    private function jwtEncode(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            $this->b64url(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->b64url(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = $this->b64url($signature);
        return implode('.', $segments);
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @throws RandomException
     */
    private function uuid4(): string
    {
        // Simple UUID v4 generator
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
