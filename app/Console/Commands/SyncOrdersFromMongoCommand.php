<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\SyncState;
use App\Services\Mongo\OrderDocumentMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Telescope;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

class SyncOrdersFromMongoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:sync-mongo {--since=} {--all} {--batch=500}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync orders directly from the configured MongoDB orders collection into the reporting database';

    protected const WATERMARK_KEY = 'mongo_orders_last_synced_at';

    public function handle(OrderDocumentMapper $mapper): int
    {
        // This command upserts tens of thousands of rows per run; leaving Telescope's
        // query/model watchers on fills its storage within minutes.
        Telescope::stopRecording();

        $client = new Client(config('mongo.uri'));

        $collection = $client
            ->selectDatabase(config('mongo.database'))
            ->selectCollection(config('mongo.orders_collection'));

        $since = $this->resolveSince();

        $filter = $since ? ['updatedAt' => ['$gte' => $this->toUtcDateTime($since)]] : [];

        $this->info($since
            ? "Syncing orders updated since {$since->toDateTimeString()}..."
            : 'Syncing all orders (full resync)...');

        $cursor = $collection->find($filter, [
            'sort' => ['updatedAt' => 1],
            'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        ]);

        $batchSize = (int) $this->option('batch');
        $batch = [];
        $processed = 0;
        $lastSeenUpdatedAt = null;

        foreach ($cursor as $document) {
            $batch[] = $document;

            if (count($batch) >= $batchSize) {
                $lastSeenUpdatedAt = $this->processBatch($batch, $mapper) ?? $lastSeenUpdatedAt;
                $processed += count($batch);
                $this->line("Processed {$processed} orders...");
                $batch = [];
            }
        }

        if ($batch !== []) {
            $lastSeenUpdatedAt = $this->processBatch($batch, $mapper) ?? $lastSeenUpdatedAt;
            $processed += count($batch);
        }

        if ($lastSeenUpdatedAt !== null) {
            SyncState::setWatermark(self::WATERMARK_KEY, $lastSeenUpdatedAt);
        }

        $this->info("Done. Synced {$processed} orders.");

        return self::SUCCESS;
    }

    protected function resolveSince(): ?Carbon
    {
        if ($this->option('all')) {
            return null;
        }

        if ($sinceOption = $this->option('since')) {
            return Carbon::parse($sinceOption);
        }

        return SyncState::getWatermark(self::WATERMARK_KEY);
    }

    /**
     * Persist a batch of mapped orders + line items inside a single transaction.
     *
     * @param  array<int, array<string, mixed>>  $documents
     */
    protected function processBatch(array $documents, OrderDocumentMapper $mapper): ?Carbon
    {
        $lastUpdatedAt = null;

        DB::transaction(function () use ($documents, $mapper, &$lastUpdatedAt): void {
            foreach ($documents as $document) {
                $mapped = $mapper->map($document);

                $order = Order::updateOrCreate(
                    ['uid' => $mapped['order']['uid'], 'environment' => Order::ENVIRONMENT_MONGO],
                    $mapped['order']
                );

                $order->items()->delete();

                if ($mapped['items'] !== []) {
                    $order->items()->createMany($mapped['items']);
                }

                if ($order->updated_at_external !== null) {
                    $lastUpdatedAt = $order->updated_at_external;
                }
            }
        });

        return $lastUpdatedAt;
    }

    protected function toUtcDateTime(Carbon $carbon): UTCDateTime
    {
        return new UTCDateTime($carbon->getTimestamp() * 1000 + intdiv($carbon->micro, 1000));
    }
}
