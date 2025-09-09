<?php

namespace App\Console\Commands;

use App\Jobs\SyncOrdersJob;
use Illuminate\Console\Command;

class SyncOrdersCommand extends Command
{
    protected $signature = 'orders:sync {date?} {env=dev}';
    protected $description = 'Sync orders for a given date (Asia/Dhaka) and environment (dev|prod)';

    public function handle(): int
    {
        $date = $this->argument('date') ?? now('Asia/Dhaka')->toDateString();
        $env  = $this->argument('env');

        $this->info("Dispatching sync for {$date} ({$env})...");
        SyncOrdersJob::dispatch($date, $env);
        $this->info("Job queued. Check your queue worker.");
        return self::SUCCESS;
    }
}
