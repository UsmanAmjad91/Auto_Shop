<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks — add ONE cron entry on the server:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
*/
Schedule::command('autoshop:refresh-statuses')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('autoshop:payment-reminders')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('autoshop:backup --scheduled')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('autoshop:prune')->weeklyOn(0, '03:00');
Schedule::command('queue:prune-failed --hours=720')->weekly();
