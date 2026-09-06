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

// Generational depth is stored, not derived on read: a person can be fourteen
// generations from the founder down one line and sixteen down another, and
// answering that per request means walking the graph every time.
//
// Nothing recomputed it. The command existed and was never scheduled and never
// triggered, so the numbers were correct exactly once — at seeding — and drifted
// the moment anybody added a relative. A grandchild added today showed the same
// generation as their parent, and the person who added them had no generation
// at all.
//
// Hourly rather than on every write: it walks a whole branch, and a family tree
// that is an hour out of date on one label is not the problem that recomputing
// on every edit would be.
Schedule::command('genealogy:recompute-lineage')
    ->hourly()
    ->name('recompute-lineage-depths')
    ->withoutOverlapping();
