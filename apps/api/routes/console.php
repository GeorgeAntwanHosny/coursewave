<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('recognition:run')->dailyAt('02:00');
Schedule::command('ledger:create-partitions')->monthlyOn(25, '03:00');
