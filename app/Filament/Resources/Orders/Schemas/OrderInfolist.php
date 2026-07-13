<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
            '[&_.fi-in-repeatable-item]:ring-0 [&_.fi-in-repeatable-item]:p-0 [&_.fi-in-repeatable-item]:py-1.5 '.
            '[&_.fi-in-repeatable-item]:border-b [&_.fi-in-repeatable-item]:border-gray-100 '.
            'dark:[&_.fi-in-repeatable-item]:border-white/5 [&_.fi-in-repeatable-item:last-child]:border-b-0';

        // shows a copy icon only on values that actually look like an identifier a user would copy
        // (emails, phone numbers, transaction/promo codes) — not on every row indiscriminately
        $isCopyWorthy = static fn (string $v): bool => (bool) preg_match('/^[\w.+-]+@[\w-]+\.[\w.-]+$/', $v)
            || (bool) preg_match('/^\+?\d{6,}$/', $v)
            || (bool) preg_match('/^(?=.*\d)[A-Z0-9]{6,}$/', $v);

        // icon shown before each row's label, keyed by the label text itself
        $keyIcon = static fn (string $k): ?string => match ($k) {
            'Order Status' => 'heroicon-o-check-circle',
            'Shipping Method' => 'heroicon-o-truck',
            'Payment Txn' => 'heroicon-o-hashtag',
            'EMI Plan' => 'heroicon-o-credit-card',
            'Promo Code' => 'heroicon-o-ticket',
            'Phone Number' => 'heroicon-o-phone',
            'Shipping Address', 'Billing Address' => 'heroicon-o-map-pin',
            'Name' => 'heroicon-o-user',
            'Subtotal' => 'heroicon-o-receipt-percent',
            'Discount' => 'heroicon-o-tag',
            'Shipping Cost' => 'heroicon-o-truck',
            'Tax' => 'heroicon-o-calculator',
            default => null,
        };

        $shipCharge = 0.0;
        foreach ((array) ($raw['shippingCharges'] ?? []) as $sc) {
            $shipCharge += (float) ($sc['payableShippingCharge'] ?? 0);
        }

        $emiMonths = (int) data_get($raw, 'payment.emi.month', 0);
        $itemDiscountTotal = (float) ($raw['price']['discountedAmount'] ?? 0);
        $rewardRedemption = (float) ($raw['price']['rewardPointDiscount']['discountAmount'] ?? 0);
        $vat = (float) ($raw['price']['vat'] ?? 0);
        $totalDiscount = $itemDiscountTotal + (float) ($order->promo_discount_amount ?: 0) + $rewardRedemption;

        // --- items, sourced from the normalized order_items relation ---
        // A product's `discount` object (in the raw payload) only describes a *campaign definition* —
        // it may not have actually been applied to this order (e.g. a reward-points order can carry an
        // unused 10% "Summer Saver" tag while price.discountedAmount is 0). The authoritative discount is
        // the order-level price.discountedAmount, so it's allocated across items by their share of the
        // subtotal rather than trusting each item's own discount.amount — this keeps the Items list and
        // the Payment breakdown always summing to the same numbers.
        $itemsSubtotal = (float) $order->items->sum(fn ($item) => $item->mrp_price * $item->quantity);
        $rawProductsByUid = collect($raw['products'] ?? [])->keyBy(fn ($p) => data_get($p, 'uid'));

        $items = $order->items->map(function ($item) use ($order, $money, $itemDiscountTotal, $itemsSubtotal, $rawProductsByUid) {
            $lineSubtotal = $item->mrp_price * $item->quantity;
            $discountAmount = $itemsSubtotal > 0
                ? round($itemDiscountTotal * ($lineSubtotal / $itemsSubtotal), 2)
                : 0.0;
            $lineTotal = $lineSubtotal - $discountAmount;

            $priceHtml = $discountAmount > 0
                ? '<div class="text-xs text-gray-400 line-through">'.e($money($lineSubtotal, $order->currency)).'</div><div class="font-semibold">'.e($money($lineTotal, $order->currency)).'</div>'
                : '<div class="font-semibold">'.e($money($lineTotal, $order->currency)).'</div>';

            return [
                'image' => data_get($rawProductsByUid->get($item->product_uid), 'variant.images.0.url'),
                'product' => $item->product_name ?? '—',
                'subtitle' => trim(implode(' · ', array_filter([
                    $item->category_name,
                    $item->seller_name ? str_replace('Walton Plaza-', '', $item->seller_name) : null,
                ]))) ?: '—',
                'qty' => (string) $item->quantity,
                'priceHtml' => $priceHtml,
            ];
        })->all();

        // non-monetary order metadata
        $orderInfo = array_values(array_filter([
            ['k' => 'Order Status', 'v' => (string) $order->status],
            ['k' => 'Shipping Method', 'v' => (string) ($raw['shippingMethod'] ?? '—')],
            $order->payment_txn_id ? ['k' => 'Payment Txn', 'v' => $order->payment_txn_id] : null,
            $emiMonths > 0 ? ['k' => 'EMI Plan', 'v' => trim(data_get($raw, 'payment.emi.bankName').' · '.data_get($raw, 'payment.emi.cardType').' · '.$emiMonths.'mo')] : null,
            $order->promo_code ? ['k' => 'Promo Code', 'v' => $order->promo_code] : null,
        ]));

        // compact invoice-style price ladder: subtotal, one combined discount, shipping, tax, total
        $paymentRows = array_values(array_filter([
            ['k' => 'Subtotal', 'v' => $money($raw['price']['subTotal'] ?? null, $order->currency)],
            $totalDiscount > 0 ? ['k' => 'Discount', 'v' => '-'.$money($totalDiscount, $order->currency)] : null,
            ['k' => 'Shipping Cost', 'v' => $money($shipCharge, $order->currency)],
            $vat > 0 ? ['k' => 'Tax', 'v' => $money($vat, $order->currency)] : null,
        ]));

        $total = ['k' => 'Total', 'v' => $money($raw['price']['customerPayable'] ?? $order->total_amount, $order->currency)];

        // --- addresses, collapsed to a single combined address line ---
        $ship = is_array($raw['receiver'] ?? null) ? $raw['receiver'] : [];
        $bill = is_array($raw['billingAddress'] ?? null) ? $raw['billingAddress'] : [];

        $combineAddress = static fn (array $a): string => trim(implode(', ', array_filter([
            data_get($a, 'address'),
            data_get($a, 'area.enName'),
            data_get($a, 'zone.enName'),
        ])));

        $toCompact = static fn (array $a): array => array_values(array_filter([
            filled(data_get($a, 'name')) ? ['k' => 'Name', 'v' => (string) data_get($a, 'name')] : null,
            filled(data_get($a, 'phoneNumber')) ? ['k' => 'Phone Number', 'v' => (string) data_get($a, 'phoneNumber')] : null,
            $combineAddress($a) !== '' ? ['k' => 'Shipping Address', 'v' => $combineAddress($a)] : null,
        ]));

        $shipRows = $toCompact($ship);
        $billRows = array_values(array_filter([
            filled(data_get($bill, 'name')) ? ['k' => 'Name', 'v' => (string) data_get($bill, 'name')] : null,
            filled(data_get($bill, 'phoneNumber')) ? ['k' => 'Phone Number', 'v' => (string) data_get($bill, 'phoneNumber')] : null,
            $combineAddress($bill) !== '' ? ['k' => 'Billing Address', 'v' => $combineAddress($bill)] : null,
        ]));
        $billingSameAsShipping = $bill === [] || $combineAddress($bill) === $combineAddress($ship);

        $customerName = (string) (data_get($ship, 'name') ?: '—');
        $initials = strtoupper(collect(preg_split('/\s+/', trim($customerName)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')) ?: '?';

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
            TextEntry::make('k')->hiddenLabel()->columnSpan(5)->color('gray')->icon(fn (string $state) => $keyIcon($state)),
            TextEntry::make('v')
                ->hiddenLabel()
                ->columnSpan(7)
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

                    Section::make('Order Summary')->compact()->schema([
                        RepeatableEntry::make('order_info')->hiddenLabel()->state($orderInfo)->columns(12)->schema($rowSchema)->contained(false)->extraAttributes(['class' => $flatRows]),
                    ]),

                    Section::make('Customer Info')->compact()->schema([
                        Grid::make(12)->schema([
                            TextEntry::make('avatar')
                                ->hiddenLabel()
                                ->state($initials)
                                ->columnSpan(1)
                                ->extraAttributes(['class' => 'flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 font-semibold']),
                            Grid::make(1)->columnSpan(11)->schema([
                                TextEntry::make('cust_name')->hiddenLabel()->state($customerName)->weight(FontWeight::Bold),
                                TextEntry::make('cust_email')
                                    ->hiddenLabel()
                                    ->state($order->customer_email)
                                    ->color('gray')
                                    ->size('sm')
                                    ->copyable()
                                    ->icon('heroicon-o-envelope')
                                    ->visible((bool) $order->customer_email),
                            ]),
                        ]),
                        RepeatableEntry::make('shipping')->hiddenLabel()->state($shipRows)->columns(12)->schema($rowSchema)->contained(false)->extraAttributes(['class' => $flatRows.' pt-2']),
                        TextEntry::make('billing_note')
                            ->hiddenLabel()
                            ->state('Billing address is the same as delivery.')
                            ->color('gray')
                            ->size('sm')
                            ->visible($billingSameAsShipping),
                        RepeatableEntry::make('billing')
                            ->hiddenLabel()
                            ->state($billRows)
                            ->columns(12)
                            ->schema($rowSchema)
                            ->contained(false)
                            ->extraAttributes(['class' => $flatRows])
                            ->visible(! $billingSameAsShipping),
                    ]),

                    Section::make('Items')->compact()->schema([
                        RepeatableEntry::make('items')->hiddenLabel()->state($items)->columns(12)->schema([
                            ImageEntry::make('image')->hiddenLabel()->columnSpan(2)->size(40)->square()->defaultImageUrl(null),
                            Grid::make(1)->columnSpan(6)->schema([
                                TextEntry::make('product')->hiddenLabel()->weight(FontWeight::Medium)->wrap()->limit(50)->tooltip(fn ($state) => $state),
                                TextEntry::make('subtitle')->hiddenLabel()->color('gray')->size('sm'),
                            ]),
                            TextEntry::make('qty')->hiddenLabel()->columnSpan(1)->badge()->color('gray')->formatStateUsing(fn (string $state) => "×{$state}"),
                            TextEntry::make('priceHtml')->hiddenLabel()->columnSpan(3)->alignRight()->html(),
                        ])->contained(false)->extraAttributes(['class' => $flatRows]),
                    ]),

                    Section::make('Payment')->compact()->schema([
                        RepeatableEntry::make('summary')->hiddenLabel()->state($paymentRows)->columns(12)->schema($rowSchema)->contained(false)->extraAttributes(['class' => $flatRows]),
                        Grid::make(12)->schema([
                            TextEntry::make('total_k')->hiddenLabel()->state($total['k'])->weight(FontWeight::Bold)->columnSpan(5),
                            TextEntry::make('total_v')->hiddenLabel()->state($total['v'])->weight(FontWeight::Bold)->size('lg')->alignRight()->columnSpan(7),
                        ])->extraAttributes(['class' => 'pt-2 border-t border-gray-100 dark:border-white/5']),
                    ]),

                    Section::make('Timeline')->compact()->schema([
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
                    ])->extraAttributes(['class' => 'empty:hidden']),
                ]),
        ]);
    }
}
