<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ai:archive-conversations')
    ->yearlyOn(8, 15, '23:59')
    ->description('Annual Archive of AI Copilot conversations on August 15 at 23:59');

Schedule::command('app:generate-monthly-invoices')->monthlyOn(1, '01:00');
Schedule::command('app:send-monthly-invoice-reminders --type=initial')->monthlyOn(1, '09:00');
Schedule::command('app:send-monthly-invoice-reminders --type=reminder')->monthlyOn(5, '10:00');

Schedule::command('app:bill-schools-monthly')->monthlyOn(1, '00:00');
