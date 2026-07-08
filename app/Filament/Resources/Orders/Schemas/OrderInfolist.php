<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Carbon;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        /** @var Order $order */
        $order = $schema->getRecord();

        $rawAttr = $order?->getAttributes()['raw'] ?? null;
        $raw = is_string($rawAttr) ? json_decode($rawAttr, true) : (array) ($rawAttr ?? []);
        if (! is_array($raw)) {
            $raw = [];
        }

        $money = static function ($v, ?string $currency = null): string {
            if ($v === null || $v === '') {
                return '—';
            }
            $num = is_numeric($v) ? (float) $v : null;
            $cur = strtoupper($currency ?? 'BDT');
            $pfx = $cur === 'BDT' ? '৳ ' : ($cur.' ');

            return $num === null ? (string) $v : $pfx.number_format($num, 2);
        };

        $fmt = static function ($dt): string {
            if (! $dt) {
                return '—';
            }
            try {
                $c = $dt instanceof Carbon ? $dt : Carbon::parse($dt);

                return $c->timezone('Asia/Dhaka')->format('M j, Y · g:i A');
            } catch (\Throwable) {
                return '—';
            }
        };

        $statusColor = match (strtoupper((string) $order->status)) {
            'DELIVERED', 'CONFIRMED', 'ACCEPTED' => 'success',
            'PENDING', 'INITIAL', 'PROCESSING' => 'warning',
            'CANCELLED', 'REJECTED' => 'danger',
            default => 'gray',
        };

        $channel = trim(implode(' · ', array_filter([
            $order->platform_type,
            $order->device_platform_type,
        ]))) ?: '—';

        $location = trim(implode(', ', array_filter([
            $order->area_name,
            $order->zone_name,
            $order->division_name,
        ]))) ?: '—';

        // --- items, sourced from the normalized order_items relation ---
        $items = $order->items->map(fn ($item) => [
            'product' => $item->product_name ?? '—',
            'category' => $item->category_name ?? '—',
            'seller' => $item->seller_name ? str_replace('Walton Plaza-', '', $item->seller_name) : '—',
            'qty' => (string) $item->quantity,
            'mrp' => $money($item->mrp_price, $order->currency),
            'discount' => $item->discount_amount > 0 ? ('-'.$money($item->discount_amount, $order->currency)) : '—',
            'lineTotal' => $money(($item->mrp_price * $item->quantity) - $item->discount_amount, $order->currency),
        ])->all();

        $shipCharge = 0.0;
        foreach ((array) ($raw['shippingCharges'] ?? []) as $sc) {
            $shipCharge += (float) ($sc['payableShippingCharge'] ?? 0);
        }

        $emiMonths = (int) data_get($raw, 'payment.emi.month', 0);

        $summary = array_values(array_filter([
            $order->payment_txn_id ? ['k' => 'Payment Txn', 'v' => $order->payment_txn_id] : null,
            $emiMonths > 0 ? ['k' => 'EMI Plan', 'v' => trim(data_get($raw, 'payment.emi.bankName').' · '.data_get($raw, 'payment.emi.cardType').' · '.$emiMonths.'mo')] : null,
            ['k' => 'Shipping Method', 'v' => (string) ($raw['shippingMethod'] ?? '—')],
            $order->promo_code ? ['k' => 'Promo Code', 'v' => $order->promo_code] : null,
            $order->promo_code ? ['k' => 'Promo Discount', 'v' => '-'.$money($order->promo_discount_amount, $order->currency)] : null,
            ['k' => 'Subtotal', 'v' => $money($raw['price']['subTotal'] ?? null, $order->currency)],
            isset($raw['price']['discountedAmount']) ? ['k' => 'Total Discount', 'v' => '-'.$money(abs($raw['price']['discountedAmount']), $order->currency)] : null,
            isset($raw['price']['rewardPointDiscount']['discountAmount']) ? ['k' => 'Reward Redemption', 'v' => '-'.$money(abs($raw['price']['rewardPointDiscount']['discountAmount']), $order->currency)] : null,
            ['k' => 'Delivery Charge', 'v' => $money($shipCharge, $order->currency)],
            ['k' => 'VAT', 'v' => $money($raw['price']['vat'] ?? null, $order->currency)],
        ]));

        $total = ['k' => 'Total', 'v' => $money($raw['price']['customerPayable'] ?? $order->total_amount, $order->currency)];

        // --- addresses ---
        $ship = is_array($raw['receiver'] ?? null) ? $raw['receiver'] : [];
        $bill = is_array($raw['billingAddress'] ?? null) ? $raw['billingAddress'] : [];

        $toPairs = static fn (array $a): array => array_values(array_filter([
            filled(data_get($a, 'name')) ? ['k' => 'Name', 'v' => (string) data_get($a, 'name')] : null,
            filled(data_get($a, 'phoneNumber')) ? ['k' => 'Phone', 'v' => (string) data_get($a, 'phoneNumber')] : null,
            filled(data_get($a, 'address')) ? ['k' => 'Address', 'v' => (string) data_get($a, 'address')] : null,
            filled(data_get($a, 'area.enName')) ? ['k' => 'Area', 'v' => (string) data_get($a, 'area.enName')] : null,
            filled(data_get($a, 'zone.enName')) ? ['k' => 'District', 'v' => (string) data_get($a, 'zone.enName')] : null,
        ]));

        $shipPairs = $toPairs($ship);
        $billPairs = $toPairs($bill);
        $billingSameAsShipping = $bill === [] || $billPairs == $shipPairs;

        $rowSchema = [
            TextEntry::make('k')->hiddenLabel()->columnSpan(4)->color('gray'),
            TextEntry::make('v')->hiddenLabel()->columnSpan(8)->alignRight()->weight(FontWeight::Medium)->copyable(),
        ];

        return $schema->components([
            Section::make()
                ->columnSpan(2)
                ->compact()
                ->schema([
                    // --- header strip ---
                    Grid::make(['default' => 2, 'lg' => 12])->schema([
                        TextEntry::make('uid')->label('Order')->badge()->copyable()->columnSpan(2),
                        TextEntry::make('status')->badge()->color($statusColor)->columnSpan(2),
                        TextEntry::make('date')->label('Order Date')->state($fmt($order->created_at_external))->columnSpan(3),
                        TextEntry::make('payment')->label('Payment')->state(trim(($order->payment_method ?? '—').' · '.($order->payment_status ?? '—')))->columnSpan(2),
                        TextEntry::make('channel')->label('Channel')->state($channel)->columnSpan(1),
                        TextEntry::make('location')->label('Location')->state($location)->columnSpan(2),
                        TextEntry::make('total')->label('Customer Payable')->state($money($raw['price']['customerPayable'] ?? $order->total_amount, $order->currency))->weight(FontWeight::Bold)->size('lg')->columnSpan(2),
                    ]),

                    // --- items (full width) ---
                    Section::make('Items')->compact()->schema([
                        Grid::make(['default' => 12])->schema([
                            TextEntry::make('h_prod')->hiddenLabel()->state('Product')->columnSpan(4),
                            TextEntry::make('h_cat')->hiddenLabel()->state('Category')->columnSpan(2),
                            TextEntry::make('h_seller')->hiddenLabel()->state('Seller')->columnSpan(2),
                            TextEntry::make('h_qty')->hiddenLabel()->state('Qty')->columnSpan(1)->alignCenter(),
                            TextEntry::make('h_mrp')->hiddenLabel()->state('MRP')->columnSpan(1)->alignRight(),
                            TextEntry::make('h_disc')->hiddenLabel()->state('Discount')->columnSpan(1)->alignRight(),
                            TextEntry::make('h_total')->hiddenLabel()->state('Line Total')->columnSpan(1)->alignRight(),
                        ]),
                        RepeatableEntry::make('items')->hiddenLabel()->state($items)->columns(12)->schema([
                            TextEntry::make('product')->hiddenLabel()->columnSpan(4)->wrap()->limit(40)->tooltip(fn ($state) => $state),
                            TextEntry::make('category')->hiddenLabel()->columnSpan(2)->color('gray'),
                            TextEntry::make('seller')->hiddenLabel()->columnSpan(2)->color('gray'),
                            TextEntry::make('qty')->hiddenLabel()->columnSpan(1)->alignCenter(),
                            TextEntry::make('mrp')->hiddenLabel()->columnSpan(1)->alignRight(),
                            TextEntry::make('discount')->hiddenLabel()->columnSpan(1)->alignRight()->color('danger'),
                            TextEntry::make('lineTotal')->hiddenLabel()->columnSpan(1)->alignRight()->weight(FontWeight::Medium),
                        ])->contained(false),
                    ])->extraAttributes(['class' => 'empty:hidden']),

                    // --- customer/delivery + order summary ---
                    Grid::make(['default' => 1, 'lg' => 12])->schema([
                        Section::make('Delivery Address')->compact()->columnSpan(7)->schema([
                            RepeatableEntry::make('shipping')->hiddenLabel()->state($shipPairs)->columns(12)->schema($rowSchema),
                            TextEntry::make('billing_note')
                                ->hiddenLabel()
                                ->state('Billing address is the same as delivery.')
                                ->color('gray')
                                ->visible($billingSameAsShipping),
                            ...($billingSameAsShipping ? [] : [
                                Section::make('Billing Address')->compact()->schema([
                                    RepeatableEntry::make('billing')->hiddenLabel()->state($billPairs)->columns(12)->schema($rowSchema),
                                ]),
                            ]),
                        ]),

                        Section::make('Order Summary')->compact()->columnSpan(5)->schema([
                            RepeatableEntry::make('summary')->hiddenLabel()->state($summary)->columns(12)->schema($rowSchema),
                            Grid::make(12)->schema([
                                TextEntry::make('total_k')->hiddenLabel()->state($total['k'])->weight(FontWeight::Bold)->columnSpan(4),
                                TextEntry::make('total_v')->hiddenLabel()->state($total['v'])->weight(FontWeight::Bold)->size('lg')->alignRight()->columnSpan(8),
                            ])->extraAttributes(['class' => 'pt-2 border-t']),
                        ]),
                    ]),
                ]),
        ]);
    }
}
