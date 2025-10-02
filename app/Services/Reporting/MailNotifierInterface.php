<?php

namespace App\Services\Reporting;

use App\Models\DailyLedger;

interface MailNotifierInterface
{
    public function sendDailyMail(DailyLedger $ledger): void;
}
