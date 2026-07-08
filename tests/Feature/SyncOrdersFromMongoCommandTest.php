<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SyncState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Database;
use Tests\TestCase;

class SyncOrdersFromMongoCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Database $mongoDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $testDatabaseName = 'testlaravel12_sync_test_'.uniqid();
        config(['mongo.database' => $testDatabaseName]);

        $this->mongoDatabase = (new Client(config('mongo.uri')))->selectDatabase($testDatabaseName);
    }

    protected function tearDown(): void
    {
        $this->mongoDatabase->drop();

        parent::tearDown();
    }

    protected function insertFixtureOrder(string $uid, string $updatedAt): void
    {
        $this->mongoDatabase->selectCollection(config('mongo.orders_collection'))->insertOne([
            'uid' => $uid,
            'status' => 'PROCESSING',
            'platformType' => 'B2C',
            'devicePlatformType' => 'WEB',
            'customer' => ['contact' => ['email' => "{$uid}@example.com"]],
            'payment' => ['type' => 'COD', 'status' => 'INITIAL'],
            'price' => ['total' => 100],
            'receiver' => [
                'area' => ['uid' => 'A-1', 'enName' => 'Dhanmondi'],
                'zone' => ['uid' => 'Z-1', 'enName' => 'Dhaka'],
                'division' => ['uid' => 'D-1', 'enName' => 'Dhaka'],
            ],
            'createdAt' => new UTCDateTime(strtotime($updatedAt) * 1000),
            'updatedAt' => new UTCDateTime(strtotime($updatedAt) * 1000),
            'products' => [
                [
                    'uid' => 'P-1',
                    'enName' => 'Test Product',
                    'category' => ['uid' => 'C-1', 'enName' => 'Test Category'],
                    'seller' => ['uid' => 'S-1', 'enName' => 'Test Seller'],
                    'variant' => ['colorFamily' => 'Red', 'size' => 'default', 'mrpPrice' => 100, 'quantity' => 1],
                ],
            ],
        ]);
    }

    public function test_it_imports_orders_and_line_items_from_mongo(): void
    {
        $this->insertFixtureOrder('O-TEST-1', '2026-01-01T00:00:00Z');
        $this->insertFixtureOrder('O-TEST-2', '2026-01-02T00:00:00Z');

        $this->artisan('orders:sync-mongo', ['--all' => true])->assertSuccessful();

        $this->assertSame(2, Order::query()->fromMongo()->count());
        $this->assertSame(2, OrderItem::query()->count());

        $order = Order::query()->fromMongo()->where('uid', 'O-TEST-1')->firstOrFail();
        $this->assertSame('O-TEST-1@example.com', $order->customer_email);
        $this->assertSame('Dhanmondi', $order->area_name);
        $this->assertSame(1, $order->items()->count());

        $watermark = SyncState::getWatermark('mongo_orders_last_synced_at');
        $this->assertNotNull($watermark);
        $this->assertSame('2026-01-02 00:00:00', $watermark->toDateTimeString());
    }

    public function test_rerunning_the_sync_does_not_duplicate_line_items(): void
    {
        $this->insertFixtureOrder('O-TEST-1', '2026-01-01T00:00:00Z');

        $this->artisan('orders:sync-mongo', ['--all' => true])->assertSuccessful();
        $this->artisan('orders:sync-mongo', ['--all' => true])->assertSuccessful();

        $this->assertSame(1, Order::query()->fromMongo()->count());
        $this->assertSame(1, OrderItem::query()->count());
    }

    public function test_incremental_sync_only_picks_up_orders_after_the_watermark(): void
    {
        $this->insertFixtureOrder('O-TEST-OLD', '2026-01-01T00:00:00Z');
        $this->artisan('orders:sync-mongo', ['--all' => true])->assertSuccessful();

        $this->insertFixtureOrder('O-TEST-NEW', '2026-01-03T00:00:00Z');
        $this->artisan('orders:sync-mongo')->assertSuccessful();

        $this->assertSame(2, Order::query()->fromMongo()->count());
    }
}
