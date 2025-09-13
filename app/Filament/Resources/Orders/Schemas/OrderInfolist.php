<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        /** @var Order $r */
        $r = $schema->getRecord();

        // --- decode raw once (string or array safe) ---
        $rawAttr = $r?->getAttributes()['raw'] ?? null;
        $raw = is_string($rawAttr) ? json_decode($rawAttr, true) : (array) ($rawAttr ?? []);
        if (! is_array($raw)) $raw = [];

        // --- helpers (preformat so entries stay simple) ---
        $money = static function ($v, ?string $currency = null): string {
            if ($v === null || $v === '') return '—';
            $num = is_numeric($v) ? (float) $v : null;
            $cur = strtoupper($currency ?? 'BDT');
            $pfx = $cur === 'BDT' ? '৳ ' : ($cur . ' ');
            return $num === null ? (string) $v : $pfx . number_format($num, 2);
        };

        $fmt = static function ($dt): string {
            if (!$dt) return '—';
            try {
                $c = $dt instanceof Carbon ? $dt : Carbon::parse($dt);
                return $c->timezone('Asia/Dhaka')->format('M j, Y · g:i A');
            } catch (\Throwable) { return '—'; }
        };

        $addr = static function (?array $a): string {
            if (! $a) return '—';
            $parts = array_filter([
                $a['name'] ?? null,
                $a['address'] ?? null,
                $a['landmark'] ?? null,
                trim(implode(', ', array_filter([
                    $a['area']['enName'] ?? null,
                    $a['zone']['enName'] ?? null,
                ]))) ?: null,
                !empty($a['phoneNumber']) ? ('☎ ' . $a['phoneNumber']) : null,
                !empty($a['addressLabel']) ? ('[' . $a['addressLabel'] . ']') : null,
            ]);
            return implode("\n", $parts);
        };

        // --- meta (DB-first, fallback to raw where helpful) ---
        $uid            = (string) ($r->uid ?? '—');
        $env            = (string) ($r->environment ?? '—');
        $status         = (string) ($r->status ?? '—');
        $statusColor    = match (strtoupper($status)) {
            'PAID','ACCEPTED','SUCCESS' => 'success',
            'PENDING'                   => 'warning',
            'CANCELLED','REJECTED','FAILED' => 'danger',
            default => 'gray',
        };
        $dateText       = $fmt($r->created_at_external ?? ($raw['createdAt'] ?? null));
        $paymentText    = trim(($r->payment_method ?? ($raw['payment']['paymentGatewayCode'] ?? '—')) . (isset($r->payment_status) ? ' · ' . $r->payment_status : (isset($raw['payment']['status']) ? ' · ' . $raw['payment']['status'] : '')));
        $paymentTxn    = trim(($raw['payment']['transactionId'] ?? '—'));
        $emiInfo    = trim(($raw['payment']['emi']['bankName'] . ' - ' . $raw['payment']['emi']['cardType'] . ' - ' . $raw['payment']['emi']['month'] ?? '—'));
        $shippingMethod = (string) ($raw['shippingMethod'] ?? '—');
        $payableShown   = $raw['price']['customerPayable'] ?? '-';
        $payableText    = $money($payableShown, $r->currency);

        // --- items (from raw), capped for safety ---
        $items = [];
        $products = is_array($raw['products'] ?? null) ? $raw['products'] : [];
        foreach (array_slice($products, 0, 100) as $p) {
            $qty   = (int)  ($p['variant']['quantity'] ?? 1);
            $mrpN  = (float)($p['variant']['mrpPrice'] ?? 0);
//            $discN = (float)($p['discount']['calculatedDiscount'] ?? 0);
//            $lineN = ($qty * $mrpN) - abs($discN);

            $items[] = [
                'product'  => (string)($p['enName'] ?? '—'),
//                'model'    => (string)($p['model']  ?? '—'),
                'plaza' => str_replace('Walton Plaza-', '', (string)($p['seller']['enName'] ?? '—')),
//                'plaza'    => (string)($p['seller']['enName'] ?? '—'),
                'plaza_uid'    => (string)($p['seller']['uid'] ?? '—'),
                'qty'      => (string) $qty,
                'mrp'      => $money($mrpN, $r->currency),
//                'discount' => $discN ? ('-' . $money(abs($discN), $r->currency)) : '—',
//                'total'    => $money($lineN, $r->currency),
            ];
        }

        // --- summary (from raw + DB) ---
        $shipCharge = 0.0;
        foreach ((array) ($raw['shippingCharges'] ?? []) as $sc) {
            $shipCharge += (float) ($sc['payableShippingCharge'] ?? 0);
        }
        $summary = [
//            ['k' => 'UID',      'v' => $uid],
//            ['k' => 'Payment',  'v' => $paymentText ?: '—'],
            ['k' => 'Payment Txn',  'v' => $paymentTxn ?: '—'],
            ['k' => 'Emi Info',  'v' => $emiInfo ?: '—'],
            ['k' => 'Shipping', 'v' => $shippingMethod],
            ['k' => 'Promo',    'v' => (string) ($raw['promocodeDetails']['code'] ?? '—')],
            ['k' => 'Subtotal', 'v' => $money($raw['price']['subTotal'] ?? null, $r->currency)],
            ['k' => 'Discount', 'v' => isset($raw['price']['discountedAmount']) ? ('-' . $money(abs($raw['price']['discountedAmount']), $r->currency)) : '—'],
            ['k' => 'Reward Redemption', 'v' => isset($raw['price']['rewardPointDiscount']['discountAmount']) ? ('-' . $money(abs($raw['price']['rewardPointDiscount']['discountAmount']), $r->currency)) : '—'],
            ['k' => 'Delivery Charge',   'v' => $money($shipCharge, $r->currency)],
            ['k' => 'VAT',               'v' => $money($raw['price']['vat'] ?? null, $r->currency)],
            ['k' => 'Total',             'v' => $money($raw['price']['customerPayable'] ?? null, $r->currency)],
        ];

        // --- addresses (from raw) ---
//        $shippingText = $addr(is_array($raw['receiver'] ?? null) ? $raw['receiver'] : null);
//        $billingText  = $addr(is_array($raw['billingAddress'] ?? null) ? $raw['billingAddress'] : null);

        // ----- Address pairs (Shipping & Billing) -----
        $ship = is_array($raw['receiver'] ?? null) ? $raw['receiver'] : [];
        $bill = is_array($raw['billingAddress'] ?? null) ? $raw['billingAddress'] : [];

        $toPairs = static function (array $a): array {
            $get = static fn ($path) => data_get($a, $path);

            return [
                ['k' => 'Name',         'v' => (string) ($get('name') ?? '—')],
                ['k' => 'Phone',        'v' => (string) ($get('phoneNumber') ?? '—')],
//                ['k' => 'Label',        'v' => (string) ($get('addressLabel') ?? '—')],
                ['k' => 'Address',      'v' => (string) ($get('address') ?? '—')],
//                ['k' => 'Landmark',     'v' => (string) ($get('landmark') ?? '—')],
                ['k' => 'Area',         'v' => (string) ($get('area.enName') ?? '—')],
                ['k' => 'District',         'v' => (string) ($get('zone.enName') ?? '—')],
            ];
        };

        $shipPairs = $toPairs($ship);
        $billPairs = $toPairs($bill);


        // === layout: meta → (items | summary) → addresses (boxy, minimal) ===
        return $schema->components([
            Section::make('') // outer card
            ->compact()
                ->columnSpan(2)
                ->schema([
                    // meta row
                    Grid::make(['default' => 1, 'lg' => 12])->schema([
                        TextEntry::make('m_uid')->label('UID')->state($uid)->badge()->copyable()->columnSpan(2),
                        TextEntry::make('m_status')->label('Status')->state($status)->badge()->color($statusColor)->columnSpan(2),
                        TextEntry::make('m_env')->label('Env')->state($env)->badge()->columnSpan(1),
                        TextEntry::make('m_date')->label('Date')->state($dateText)->badge()->columnSpan(3),
                        TextEntry::make('m_payment')->label('Payment')->state($paymentText)->badge()->columnSpan(2),
                        TextEntry::make('m_total')->label('Customer Payable')->state($payableText)->badge()->columnSpan(2),
                    ]),

                    // items + summary
                    Grid::make(['default' => 1, 'lg' => 12])->schema([
                        // ITEMS (left)
                        Section::make('Items')->compact()->columnSpan(9)->schema([
                            // header
                            Grid::make(['default' => 12])->schema([
                                TextEntry::make('h_prod')->hiddenLabel()->state('Product')->columnSpan(4)->alignCenter(),
//                                TextEntry::make('h_model')->hiddenLabel()->state('Model')->columnSpan(2),
                                TextEntry::make('h_plaza')->hiddenLabel()->state('Plaza')->columnSpan(2)->alignCenter(),
                                TextEntry::make('h_plaza_uid')->hiddenLabel()->state('Plaza Uid')->columnSpan(2)->alignCenter(),
                                TextEntry::make('h_qty')->hiddenLabel()->state('Qty')->columnSpan(1)->alignCenter(),
                                TextEntry::make('h_mrp')->hiddenLabel()->state('MRP')->columnSpan(2)->alignCenter(),
//                                TextEntry::make('h_disc')->hiddenLabel()->state('Discount')->columnSpan(2)->alignCenter(),
//                                TextEntry::make('h_tot')->hiddenLabel()->state('Total')->columnSpan(2)->alignCenter(),
                            ]),
                            // rows
                            RepeatableEntry::make('items')->hiddenLabel()->state($items)->columns(12)->schema([
                                TextEntry::make('product')->hiddenLabel()->columnSpan(4)->color('gray')->alignCenter()->wrap()->limit(30)
                                    ->tooltip(fn ($state) => $state)
                                    ->extraAttributes([
                                        'class' => 'whitespace-normal break-words',
                                        'style' => 'max-width:28rem;',
                                    ]),
//                                TextEntry::make('model')->hiddenLabel()->columnSpan(2),
                                TextEntry::make('plaza')->hiddenLabel()->columnSpan(2)->badge()->color('gray')->alignCenter()->wrap()->limit(12)
                                    ->tooltip(fn ($state) => $state)
                                    ->extraAttributes([
                                        'class' => 'whitespace-normal break-words',
                                        'style' => 'max-width:28rem;',
                                    ]),
                                TextEntry::make('plaza_uid')->hiddenLabel()->columnSpan(2)->copyable()->badge()->color('gray')->alignCenter()->wrap(),
                                TextEntry::make('qty')->hiddenLabel()->columnSpan(1)->badge()->color('gray')->alignCenter()->wrap(),
                                TextEntry::make('mrp')->hiddenLabel()->columnSpan(2)->badge()->color('gray')->alignCenter()->wrap(),
//                                TextEntry::make('discount')->hiddenLabel()->columnSpan(2)->badge()->color('gray')->alignCenter()->wrap(),
//                                TextEntry::make('total')->hiddenLabel()->columnSpan(2)->badge()->color('gray')->alignCenter()->wrap(),
                            ]),
                        ]),

                        // SUMMARY (right)
                        Section::make('Order Summary')->compact()->columnSpan(3)->schema([
                            RepeatableEntry::make('summary')->hiddenLabel()->state($summary)->columns(12)->schema([
                                TextEntry::make('k')->hiddenLabel()->columnSpan(5),
                                TextEntry::make('v')->hiddenLabel()->columnSpan(5)->alignRight()->badge()->color('gray')->wrap()
                                    ->tooltip(fn ($state) => $state)
                                    ->extraAttributes([
                                        'class' => 'whitespace-normal break-words',
                                        'style' => 'max-width:28rem;',
                                    ]),
                            ]),
                        ]),
                    ]),

                    // addresses
                    Section::make('')->compact()->schema([
                        Grid::make(['default' => 1, 'lg' => 12])->schema([

                            // Billing (left)
                            Section::make('Billing Address')->compact()->columnSpan(6)->schema([
                                RepeatableEntry::make('billing')
                                    ->hiddenLabel()
                                    ->state($billPairs)      // <-- preformatted pairs
                                    ->columns(12)
                                    ->schema([
                                        TextEntry::make('k')->hiddenLabel()->columnSpan(2),
                                        TextEntry::make('v')->hiddenLabel()->columnSpan(10)->alignRight()->copyable()->badge()->color('gray')->wrap()
                                            ->tooltip(fn ($state) => $state)
                                            ->extraAttributes([
                                                'class' => 'whitespace-normal break-words',
                                                'style' => 'max-width:28rem;',
                                            ]),
                                    ]),
                            ]),
                            // Shipping (right)
                            Section::make('Shipping Address')->compact()->columnSpan(6)->schema([
                                RepeatableEntry::make('shipping')
                                    ->hiddenLabel()
                                    ->state($shipPairs)      // <-- preformatted pairs
                                    ->columns(12)
                                    ->schema([
                                        TextEntry::make('k')->hiddenLabel()->columnSpan(2),
                                        TextEntry::make('v')->hiddenLabel()->columnSpan(10)->alignRight()->copyable()->badge()->color('gray')->wrap()
                                            ->tooltip(fn ($state) => $state)
                                            ->extraAttributes([
                                                'class' => 'whitespace-normal break-words',
                                                'style' => 'max-width:28rem;',
                                            ]),
                                    ]),
                            ]),


                        ]),
                    ]),
                ]),
        ]);
    }
}
