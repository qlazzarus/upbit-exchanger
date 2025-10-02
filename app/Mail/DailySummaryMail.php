<?php

namespace App\Mail;

use App\Models\DailyLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DailyLedger $ledger,
        public string      $subjectPrefix = '[Daily PnL]',
    )
    {
    }

    public function build(): self
    {
        $l = $this->ledger;

        $subject = sprintf('%s %s | PnL %.4f USDT (%.2f%%) | W%d/L%d',
            $this->subjectPrefix,
            $l->date,
            (float)$l->pnl_usdt,
            $l->pnl_pct ?? 0,
            (int)$l->wins,
            (int)$l->losses
        );

        return $this->subject($subject)
            ->view('emails.daily_summary')
            ->with(['l' => $l]);
    }
}
