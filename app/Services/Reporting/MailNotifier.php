<?php

namespace App\Services\Reporting;

use App\Mail\DailySummaryMail;
use App\Models\DailyLedger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailNotifier implements MailNotifierInterface
{
    public function __construct(
        private ?string $to = null,
        private string  $subjectPrefix = '[Daily PnL]',
    )
    {
        $cfg = config('reporting.mail', []);
        $this->to = $this->to ?: ($cfg['to'] ?? null);
        $this->subjectPrefix = $cfg['subject_prefix'] ?? $this->subjectPrefix;
    }

    public function sendDailyMail(DailyLedger $ledger): void
    {
        if (!$this->to) {
            Log::warning('[MailNotifier] report.mail.to not set');
            return;
        }
        Mail::to($this->to)->send(new DailySummaryMail($ledger, $this->subjectPrefix));
    }
}
