<!doctype html>
<html>
<body>
<h2>Daily Summary ({{ $l->date }})</h2>
<ul>
    <li>Equity Start: {{ $l->equity_start_usdt }}</li>
    <li>Equity End: {{ $l->equity_end_usdt }}</li>
    <li>PNL (USDT): {{ $l->pnl_usdt }}</li>
    <li>PNL (%): {{ $l->pnl_pct }}</li>
    <li>Trades: {{ $l->trades_count }}</li>
    <li>Wins/Losses: {{ $l->wins }} / {{ $l->losses }}</li>
    <li>Generated at: {{ now('Asia/Seoul')->toDateTimeString() }}</li>
</ul>
</body>
</html>
