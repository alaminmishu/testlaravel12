<?php

namespace Tests\Unit\Services\Mongo;

use App\Models\Order;
use App\Services\Mongo\OrderDocumentMapper;
use MongoDB\BSON\UTCDateTime;
use Tests\TestCase;

class OrderDocumentMapperTest extends TestCase
{
    /**
     * A fixture shaped like a real document from the wp_prod_17_may.orders collection.
     *
     * @return array<string, mixed>
     */
    protected function orderDocument(): array
    {
        return [
            'uid' => 'O-NUQ7A5',
            'status' => 'PROCESSING',
            'platformType' => 'B2C',
            'devicePlatformType' => 'WEB',
            'posSyncId' => '20260430241389140',
            'isPosSync' => true,
            'isPhoneOrder' => false,
            'customer' => [
                'contact' => ['email' => 'famina@example.com'],
            ],
            'payment' => [
                'type' => 'COD',
                'status' => 'INITIAL',
            ],
            'price' => [
                'total' => 85,
            ],
            'promocodeDetails' => [
                'code' => 'SAVE10',
                'discount' => 10,
            ],
            'receiver' => [
                'area' => ['uid' => 'A-55JWLW', 'enName' => 'Dhanmondi'],
                'zone' => ['uid' => 'Z-RJM7HJ', 'enName' => 'Dhaka'],
                'division' => ['uid' => 'D-N3FN4Q', 'enName' => 'Dhaka'],
            ],
            'createdAt' => new UTCDateTime(strtotime('2023-05-31T11:31:11.503Z') * 1000),
            'updatedAt' => new UTCDateTime(strtotime('2023-05-31T11:31:38.156Z') * 1000),
            'products' => [
                [
                    'uid' => 'P-BCAGJQ',
                    'enName' => 'Walton Tornado Fan 9" | WTF9S5',
                    'category' => ['uid' => 'C-343AG2', 'enName' => 'Tornado Fan'],
                    'seller' => ['uid' => 'S-7B7EC8', 'enName' => 'Walton Plaza-Multiplan Center, Dhaka'],
                    'wareHouse' => ['uid' => 'WH-NNL553', 'meta' => ['title' => 'Walton Plaza-Multiplan Center, Dhaka']],
                    'variant' => ['colorFamily' => 'Blue', 'size' => 'default', 'mrpPrice' => 10, 'quantity' => 1],
                    'discount' => ['type' => 'NOT_AVAILABLE', 'amount' => 0],
                    'commission' => ['commissionAmount' => 0],
                ],
            ],
        ];
    }

    public function test_it_maps_order_level_fields(): void
    {
        $mapped = (new OrderDocumentMapper)->map($this->orderDocument());

        $this->assertSame('O-NUQ7A5', $mapped['order']['uid']);
        $this->assertSame(Order::ENVIRONMENT_MONGO, $mapped['order']['environment']);
        $this->assertSame('PROCESSING', $mapped['order']['status']);
        $this->assertSame('COD', $mapped['order']['payment_method']);
        $this->assertSame('INITIAL', $mapped['order']['payment_status']);
        $this->assertSame(85, $mapped['order']['total_amount']);
        $this->assertSame('famina@example.com', $mapped['order']['customer_email']);
        $this->assertSame('B2C', $mapped['order']['platform_type']);
        $this->assertSame('WEB', $mapped['order']['device_platform_type']);
        $this->assertSame('Dhanmondi', $mapped['order']['area_name']);
        $this->assertSame('Dhaka', $mapped['order']['zone_name']);
        $this->assertSame('SAVE10', $mapped['order']['promo_code']);
        $this->assertSame(10, $mapped['order']['promo_discount_amount']);
        $this->assertSame('2023-05-31 11:31:11', $mapped['order']['created_at_external']);
        $this->assertSame('20260430241389140', $mapped['order']['pos_sync_id']);
        $this->assertTrue($mapped['order']['is_pos_synced']);
        $this->assertFalse($mapped['order']['is_phone_order']);
    }

    public function test_it_maps_line_items(): void
    {
        $mapped = (new OrderDocumentMapper)->map($this->orderDocument());

        $this->assertCount(1, $mapped['items']);

        $item = $mapped['items'][0];
        $this->assertSame('P-BCAGJQ', $item['product_uid']);
        $this->assertSame('Tornado Fan', $item['category_name']);
        $this->assertSame('Walton Plaza-Multiplan Center, Dhaka', $item['seller_name']);
        $this->assertSame('Walton Plaza-Multiplan Center, Dhaka', $item['warehouse_name']);
        $this->assertSame('Blue', $item['color_family']);
        $this->assertSame(1, $item['quantity']);
        $this->assertSame(10, $item['mrp_price']);
    }

    public function test_it_handles_missing_products_array(): void
    {
        $document = $this->orderDocument();
        unset($document['products']);

        $mapped = (new OrderDocumentMapper)->map($document);

        $this->assertSame([], $mapped['items']);
    }

    public function test_it_defaults_pos_sync_and_phone_order_flags_to_false_when_absent(): void
    {
        $document = $this->orderDocument();
        unset($document['posSyncId'], $document['isPosSync'], $document['isPhoneOrder']);

        $mapped = (new OrderDocumentMapper)->map($document);

        $this->assertNull($mapped['order']['pos_sync_id']);
        $this->assertFalse($mapped['order']['is_pos_synced']);
        $this->assertFalse($mapped['order']['is_phone_order']);
    }
}
