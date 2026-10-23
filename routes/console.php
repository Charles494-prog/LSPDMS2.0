<?php

use Illuminate\Support\Facades\Schedule;

// These run only while a scheduler is active: Windows Task Scheduler calling
// "php artisan schedule:run" every minute, or "php artisan schedule:work".
Schedule::command('messages:cleanup-expired')->daily();
Schedule::command('lspdms:backup:create')->monthlyOn(1, '02:00')->timezone('Asia/Manila')->withoutOverlapping();
