<?php

use App\Services\Sync\IdempotencyLedger;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The idempotency ledger records every write by every account. Thirty days is
// far longer than any phone would still be retrying an operation, and without
// this the table grows for the life of the application.
Schedule::call(fn (IdempotencyLedger $ledger) => $ledger->prune())
    ->daily()
    ->name('prune-sync-ledger')
    ->withoutOverlapping();
