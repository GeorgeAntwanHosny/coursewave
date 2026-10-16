<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('recognition:run')->dailyAt('02:00');
Schedule::command('ledger:create-partitions')->monthlyOn(25, '03:00');
Schedule::command('payouts:run')->weeklyOn(1, '04:00')->withoutOverlapping(); // Mondays, after the 02:00 recognition run
Schedule::command('refunds:dispatch-pending')->everyTenMinutes()->withoutOverlapping();
