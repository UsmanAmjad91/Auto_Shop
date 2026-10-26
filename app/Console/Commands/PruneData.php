<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\IntegrationLog;
use App\Models\LoginHistory;
use App\Models\OtpCode;
use Illuminate\Console\Command;

class PruneData extends Command
{
    protected $signature = 'autoshop:prune';

    protected $description = 'Apply data-retention settings (audit logs, login history, integration logs, expired OTP codes)';

    public function handle(): int
    {
        $a = AuditLog::where('created_at', '<', now()->subDays((int) settings('retention.audit_logs_days', 730)))->delete();
        $l = LoginHistory::where('created_at', '<', now()->subDays((int) settings('retention.login_history_days', 365)))->delete();
        $i = IntegrationLog::where('created_at', '<', now()->subDays((int) settings('retention.integration_logs_days', 90)))->delete();
        $o = OtpCode::where('expires_at', '<', now()->subDay())->delete();
        $this->info("Pruned {$a} audit, {$l} login, {$i} integration log rows and {$o} OTP codes.");

        return self::SUCCESS;
    }
}
