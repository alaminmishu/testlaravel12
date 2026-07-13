<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
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

        // Mongo dates embedded in nested arrays (e.g. csStatuses[].operator.at) survive as
        // {"$date": {"$numberLong": "..."}} through the json_decode round-trip of the raw document.
        $mongoDate = static function (mixed $v) use ($fmt): ?string {
            $ms = data_get($v, '$date.$numberLong');
            if ($ms === null) {
                return null;
            }
            try {
                return $fmt(Carbon::createFromTimestampMs((int) $ms));
            } catch (\Throwable) {
                return null;
            }
        };

        $statusColor = match (strtoupper((string) $order->status)) {
            'DELIVERED', 'CONFIRMED', 'ACCEPTED' => 'success',
            'PENDING', 'INITIAL', 'PROCESSING' => 'warning',
            'CANCELLED', 'REJECTED' => 'danger',
            default => 'gray',
        };

        $statusIcon = match ($statusColor) {
            'success' => 'heroicon-o-check-circle',
            'warning' => 'heroicon-o-clock',
            'danger' => 'heroicon-o-x-circle',
            default => 'heroicon-o-information-circle',
        };

        $headerAccentClass = match ($statusColor) {
            'success' => 'border-l-4 border-l-success-500 bg-success-50/60 dark:bg-success-500/10',
            'warning' => 'border-l-4 border-l-warning-500 bg-warning-50/60 dark:bg-warning-500/10',
            'danger' => 'border-l-4 border-l-danger-500 bg-danger-50/60 dark:bg-danger-500/10',
            default => 'border-l-4 border-l-gray-300 dark:border-l-gray-600',
        };

        // Overrides Filament's default boxed/card look for repeatable rows (rounded, shadowed
        // "fi-in-repeatable-item" cards) with a flat, divided list — the boxed default is what made
        // every key/value pair look like a generic scaffolded repeater field instead of a designed page.
        $flatRows = '[&_.fi-in-repeatable]:gap-0 [&_.fi-in-repeatable-item]:rounded-none '.
            '[&_.fi-in-repeatable-item]:bg-transparent [&_.fi-in-repeatable-item]:shadow-none '.
            '[&_.fi-in-repeatable-item]:ring-0 [&_.fi-in-repeatable-item]:p-0 [&_.fi-in-repeatable-item]:py-2 '.
            '[&_.fi-in-repeatable-item]:border-b [&_.fi-in-repeatable-item]:border-gray-100 '.
            'dark:[&_.fi-in-repeatable-item]:border-white/5 [&_.fi-in-repeatable-item:last-child]:border-b-0';

        // shows a copy icon only on values that actually look like an identifier a user would copy
        // (emails, phone numbers, transaction/promo codes) — not on every row indiscriminately
        $isCopyWorthy = static fn (string $v): bool => (bool) preg_match('/^[\w.+-]+@[\w-]+\.[\w.-]+$/', $v)
            || (bool) preg_match('/^\+?\d{6,}$/', $v)
            || (bool) preg_match('/^[A-Z0-9]{6,}$/', $v);

        $channel = trim(implode(' · ', array_filter([
            $order->platform_type,
            $order->device_platform_type,
        ]))) ?: '—';

        $shipCharge = 0.0;
        foreach ((array) ($raw['shippingCharges'] ?? []) as $sc) {
            $shipCharge += (float) ($sc['payableShippingCharge'] ?? 0);
        }

        $emiMonths = (int) data_get($raw, 'payment.emi.month', 0);
        $itemDiscountTotal = (float) ($raw['price']['discountedAmount'] ?? 0);
        $rewardRedemption = (float) ($raw['price']['rewardPointDiscount']['discountAmount'] ?? 0);
        $vat = (float) ($raw['price']['vat'] ?? 0);

        // --- items, sourced from the normalized order_items relation ---
        // A product's `discount` object (in the raw payload) only describes a *campaign definition* —
        // it may not have actually been applied to this order (e.g. a reward-points order can carry an
        // unused 10% "Summer Saver" tag while price.discountedAmount is 0). The authoritative discount is
        // the order-level price.discountedAmount, so it's allocated across items by their share of the
        // subtotal rather than trusting each item's own discount.amount — this keeps the Items table and
        // the Price Breakdown always summing to the same numbers.
        $itemsSubtotal = (float) $order->items->sum(fn ($item) => $item->mrp_price * $item->quantity);

        $items = $order->items->map(function ($item) use ($order, $money, $itemDiscountTotal, $itemsSubtotal) {
            $lineSubtotal = $item->mrp_price * $item->quantity;
            $discountAmount = $itemsSubtotal > 0
                ? round($itemDiscountTotal * ($lineSubtotal / $itemsSubtotal), 2)
                : 0.0;

            return [
                'product' => $item->product_name ?? '—',
                'category' => $item->category_name ?? '—',
                'seller' => $item->seller_name ? str_replace('Walton Plaza-', '', $item->seller_name) : '—',
                'qty' => (string) $item->quantity,
                'mrp' => $money($item->mrp_price, $order->currency),
                'discount' => $discountAmount > 0 ? ('-'.$money($discountAmount, $order->currency)) : '—',
                'lineTotal' => $money($lineSubtotal - $discountAmount, $order->currency),
            ];
        })->all();

        // non-monetary order metadata, kept separate from the price ladder below
        $orderInfo = array_values(array_filter([
            $order->payment_txn_id ? ['k' => 'Payment Txn', 'v' => $order->payment_txn_id] : null,
            ['k' => 'Shipping Method', 'v' => (string) ($raw['shippingMethod'] ?? '—')],
            $emiMonths > 0 ? ['k' => 'EMI Plan', 'v' => trim(data_get($raw, 'payment.emi.bankName').' · '.data_get($raw, 'payment.emi.cardType').' · '.$emiMonths.'mo')] : null,
            $order->promo_code ? ['k' => 'Promo Code', 'v' => $order->promo_code] : null,
            ['k' => 'Channel', 'v' => $channel],
        ]));

        // top-to-bottom invoice-style price ladder: subtotal, then every discount, then additions, then total
        $priceBreakdown = array_values(array_filter([
            ['k' => 'Subtotal', 'v' => $money($raw['price']['subTotal'] ?? null, $order->currency)],
            $itemDiscountTotal > 0 ? ['k' => 'Item Discount', 'v' => '-'.$money($itemDiscountTotal, $order->currency)] : null,
            ($order->promo_code && $order->promo_discount_amount > 0) ? ['k' => 'Promo Discount', 'v' => '-'.$money($order->promo_discount_amount, $order->currency)] : null,
            $rewardRedemption > 0 ? ['k' => 'Reward Redemption', 'v' => '-'.$money($rewardRedemption, $order->currency)] : null,
            ['k' => 'Delivery Charge', 'v' => $money($shipCharge, $order->currency)],
            $vat > 0 ? ['k' => 'VAT', 'v' => $money($vat, $order->currency)] : null,
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

        $shipPairs = array_values(array_filter([
            $order->customer_email ? ['k' => 'Email', 'v' => $order->customer_email] : null,
            ...$toPairs($ship),
        ]));
        $billPairs = $toPairs($bill);
        $billingSameAsShipping = $bill === [] || $billPairs == $toPairs($ship);

        // --- status history, sourced from the raw csStatuses audit trail ---
        $timelineRows = collect($raw['csStatuses'] ?? [])
            ->map(fn ($s) => [
                'action' => (string) ($s['action'] ?? ''),
                'at_raw' => data_get($s, 'operator.at'),
            ])
            ->filter(fn ($s) => $s['action'] !== '' && $mongoDate($s['at_raw']) !== null)
            ->sortBy(fn ($s) => data_get($s['at_raw'], '$date.$numberLong'))
            ->values()
            ->map(fn ($s) => [
                'label' => str($s['action'])->replace('_', ' ')->title()->toString(),
                'at' => $mongoDate($s['at_raw']),
            ])
            ->all();

        $timelineIcon = static fn (string $label): string => match (true) {
            str_contains($label, 'Delivered') => 'heroicon-o-check-badge',
            str_contains($label, 'Cancel') => 'heroicon-o-x-circle',
            str_contains($label, 'Call') => 'heroicon-o-phone',
            str_contains($label, 'Accepted'), str_contains($label, 'Confirm') => 'heroicon-o-check-circle',
            str_contains($label, 'Assigned') => 'heroicon-o-user-plus',
            default => 'heroicon-o-flag',
        };

        $rowSchema = [
            TextEntry::make('k')->hiddenLabel()->columnSpan(4)->color('gray'),
            TextEntry::make('v')
                ->hiddenLabel()
                ->columnSpan(8)
                ->alignRight()
                ->weight(FontWeight::Medium)
                ->copyable(fn (string $state): bool => $isCopyWorthy($state))
                ->icon(fn (string $state): ?string => $isCopyWorthy($state) ? 'heroicon-o-clipboard-document' : null)
                ->iconPosition(IconPosition::After),
        ];

        return $schema->components([
            Section::make()
                ->columnSpan(2)
                ->compact()
                ->schema([
                    // --- header strip ---
                    Section::make()
                        ->compact()
                        ->extraAttributes(['class' => $headerAccentClass])
                        ->schema([
                            Grid::make(['default' => 2, 'lg' => 12])->schema([
                                TextEntry::make('uid')->label('Order')->badge()->copyable()->icon('heroicon-o-clipboard-document')->iconPosition(IconPosition::After)->columnSpan(3),
                                TextEntry::make('status')->badge()->color($statusColor)->icon($statusIcon)->columnSpan(2),
                                TextEntry::make('date')->label('Order Date')->icon('heroicon-o-calendar-days')->state($fmt($order->created_at_external))->columnSpan(3),
                                TextEntry::make('payment')->label('Payment')->icon('heroicon-o-credit-card')->state(trim(($order->payment_method ?? '—').' · '.($order->payment_status ?? '—')))->columnSpan(2),
                                TextEntry::make('total')->label('Customer Payable')->icon('heroicon-o-banknotes')->state($money($raw['price']['customerPayable'] ?? $order->total_amount, $order->currency))->weight(FontWeight::Bold)->size('lg')->columnSpan(2),
                            ]),
                        ]),

                    Tabs::make('Order Details')->tabs([
                        Tab::make('Items')
                            ->icon('heroicon-o-shopping-bag')
                            ->badge((string) count($items))
                            ->schema([
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
                                ])->contained(false)->extraAttributes(['class' => $flatRows]),
                            ]),

                        Tab::make('Customer & Delivery')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 12])->schema([
                                    Section::make('Delivery Address')->compact()->columnSpan(7)->schema([
                                        RepeatableEntry::make('shipping')->hiddenLabel()->state($shipPairs)->columns(12)->schema($rowSchema)->contained(false)->extraAttributes(['class' => $flatRows]),
                                        TextEntry::make('billing_note')
                                            ->hiddenLabel()
                                            ->state('Billing address is the same as delivery.')
                                            ->color('gray')
                                            ->visible($billingSameAsShipping),
                                        ...($billingSameAsShipping ? [] : [
                                            Section::make('Billing Address')->compact()->schema([
                                                RepeatableEntry::make('billing')->hiddenLabel()->state($billPairs)->columns(12)->schema($rowSchema)->contained(false)->extraAttributes(['class' => $flatRows]),
                                            ]),
                                        ]),
                                    ]),

                                    Section::make('Order Summary')->compact()->columnSpan(5)->schema([
                                        RepeatableEntry::make('order_info')
                                            ->hiddenLabel()
                                            ->state($orderInfo)
                                            ->columns(12)
                                            ->schema($rowSchema)
                                            ->contained(false)
                                            ->extraAttributes(['class' => $flatRows])
                                            ->visible($orderInfo !== []),
                                        TextEntry::make('price_breakdown_label')
                                            ->hiddenLabel()
                                            ->state('Price Breakdown')
                                            ->size('xs')
                                            ->color('gray')
                                            ->weight(FontWeight::SemiBold)
                                            ->extraAttributes(['class' => 'uppercase tracking-wide pt-2 '.($orderInfo !== [] ? 'border-t border-gray-100 dark:border-white/5' : '')]),
                                        RepeatableEntry::make('summary')->hiddenLabel()->state($priceBreakdown)->columns(12)->schema($rowSchema)->contained(false)->extraAttributes(['class' => $flatRows]),
                                        Grid::make(12)->schema([
                                            TextEntry::make('total_k')->hiddenLabel()->state($total['k'])->weight(FontWeight::Bold)->columnSpan(4),
                                            TextEntry::make('total_v')->hiddenLabel()->state($total['v'])->weight(FontWeight::Bold)->size('lg')->alignRight()->columnSpan(8),
                                        ])->extraAttributes(['class' => 'pt-2 border-t border-gray-100 dark:border-white/5']),
                                    ]),
                                ]),
                            ]),

                        Tab::make('Timeline')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                RepeatableEntry::make('timeline')
                                    ->hiddenLabel()
                                    ->state($timelineRows)
                                    ->columns(12)
                                    ->schema([
                                        TextEntry::make('label')->hiddenLabel()->columnSpan(8)->icon(fn (string $state) => $timelineIcon($state))->weight(FontWeight::Medium),
                                        TextEntry::make('at')->hiddenLabel()->columnSpan(4)->alignRight()->color('gray')->size('sm'),
                                    ])
                                    ->contained(false)
                                    ->extraAttributes(['class' => $flatRows])
                                    ->visible($timelineRows !== []),
                                TextEntry::make('no_timeline')
                                    ->hiddenLabel()
                                    ->state('No status history available for this order.')
                                    ->color('gray')
                                    ->visible($timelineRows === []),
                            ]),
                    ]),
                ]),
        ]);
    }
}
