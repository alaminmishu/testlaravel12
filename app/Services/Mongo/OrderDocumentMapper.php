<?php

namespace App\Services\Mongo;

use App\Models\Order;
use MongoDB\BSON\UTCDateTime;

class OrderDocumentMapper
{
    /**
     * Map a decoded MongoDB order document to Eloquent-ready attributes.
     *
     * @param  array<string, mixed>  $document
     * @return array{order: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function map(array $document): array
    {
        return [
            'order' => $this->mapOrder($document),
            'items' => $this->mapItems($document),
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    protected function mapOrder(array $document): array
    {
        return [
            'uid' => data_get($document, 'uid'),
            'environment' => Order::ENVIRONMENT_MONGO,
            'status' => data_get($document, 'status'),
            'payment_method' => data_get($document, 'payment.type'),
            'payment_status' => data_get($document, 'payment.status'),
            'created_at_external' => $this->toDateTime(data_get($document, 'createdAt')),
            'updated_at_external' => $this->toDateTime(data_get($document, 'updatedAt')),
            'currency' => 'BDT',
            'total_amount' => data_get($document, 'price.total'),
            'customer_email' => data_get($document, 'customer.contact.email'),
            'platform_type' => data_get($document, 'platformType'),
            'device_platform_type' => data_get($document, 'devicePlatformType'),
            'area_uid' => data_get($document, 'receiver.area.uid'),
            'area_name' => data_get($document, 'receiver.area.enName'),
            'zone_uid' => data_get($document, 'receiver.zone.uid'),
            'zone_name' => data_get($document, 'receiver.zone.enName'),
            'division_uid' => data_get($document, 'receiver.division.uid'),
            'division_name' => data_get($document, 'receiver.division.enName'),
            'promo_code' => data_get($document, 'promocodeDetails.code'),
            'promo_discount_amount' => data_get($document, 'promocodeDetails.discount'),
            'raw' => $document,
        ];
    }

    protected function toDateTime(mixed $value): ?string
    {
        if ($value instanceof UTCDateTime) {
            return $value->toDateTime()->format('Y-m-d H:i:s');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<int, array<string, mixed>>
     */
    protected function mapItems(array $document): array
    {
        $products = data_get($document, 'products', []);

        if (! is_array($products)) {
            return [];
        }

        return array_map(fn (array $product): array => [
            'product_uid' => data_get($product, 'uid'),
            'product_name' => data_get($product, 'enName'),
            'category_uid' => data_get($product, 'category.uid'),
            'category_name' => data_get($product, 'category.enName'),
            'seller_uid' => data_get($product, 'seller.uid'),
            'seller_name' => data_get($product, 'seller.enName'),
            'warehouse_uid' => data_get($product, 'wareHouse.uid'),
            'warehouse_name' => data_get($product, 'wareHouse.meta.title'),
            'color_family' => data_get($product, 'variant.colorFamily'),
            'size' => data_get($product, 'variant.size'),
            'quantity' => data_get($product, 'variant.quantity', 1),
            'mrp_price' => data_get($product, 'variant.mrpPrice'),
            'discount_amount' => data_get($product, 'discount.amount'),
            'commission_amount' => data_get($product, 'commission.commissionAmount'),
        ], $products);
    }
}
