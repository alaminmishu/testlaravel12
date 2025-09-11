<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\KeyValueEntry;

class OrderInfolistLite
{
    /**
     * Configure Schema (pure Filament PHP, no Blade).
     *
     * Note: closures used for ->state(...) use the signature supported by Infolists:
     * - For top-level entries: fn ($record) => ...
     * - For repeatable nested items: fn ($item, $record = null) => ...
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // top-level responsive grid: left = details, right = summary
            Grid::make()
                ->columns(2)
                ->columnSpan(2)
                ->schema([
                    // LEFT column: Order meta + products
                    Section::make()
                        ->compact()
                        ->collapsible()
                        ->description('Order details')
                        ->schema([
                            TextEntry::make('environment')
                                ->hiddenLabel()
                                ->badge(),
                            TextEntry::make('uid')
                                ->hiddenLabel()
                                ->badge()
                                ->icon('heroicon-o-clipboard-document-list')
                                ->copyable()
                                ->copyMessage('Copied!')
                                ->copyMessageDuration(2000),
                            TextEntry::make('customer_email')
                                ->hiddenLabel()
                                ->badge(),
                            TextEntry::make('status')
                                ->hiddenLabel()
                                ->badge(),
                            TextEntry::make('payment_method')
                                ->hiddenLabel()
                                ->badge(),
                            TextEntry::make('created_at')
                                ->hiddenLabel()
                                ->badge()
                                ->dateTime('M j, Y H:i:s'),
                            TextEntry::make('payment_status')
                                ->hiddenLabel()
                                ->badge(),

                            // Repeatable list of products extracted from payload.products (array)
                            RepeatableEntry::make('products')
                                ->label('Products')
                                ->state(fn ($record) => data_get($record->payload, 'products', []))
                                ->schema([
                                    TextEntry::make('product_name')
                                        ->label('Product')
                                        ->state(fn ($item, $record = null) => data_get($item, 'enName', data_get($item, 'name', '-')))
                                        ->html(),

                                    TextEntry::make('product_model')
                                        ->label('Model / SKU')
                                        ->state(fn ($item, $record = null) => trim((string) (data_get($item, 'model', '') . ' · ' . data_get($item, 'variant.posItemCode', ''))))
                                        ->placeholder('-')
                                        ->html(),

                                    TextEntry::make('qty')
                                        ->label('Qty')
                                        ->state(fn ($item, $record = null) => data_get($item, 'variant.quantity', data_get($item, 'quantity', 1)))
                                        ->numeric(),

                                    TextEntry::make('price')
                                        ->label('MRP')
                                        ->state(fn ($item, $record = null) => number_format((float) data_get($item, 'variant.mrpPrice', data_get($item, 'mrp', 0)), 2))
                                        ->numeric(),
                                ])
                        ])->columns(1)->columnSpan(1),

                    // RIGHT column: Addresses / receiver and summary key-values
                    Section::make()->schema([
                        TextEntry::make('receiver_name')
                            ->label('Receiver')
                            ->state(fn ($record) => data_get($record->payload, 'receiver.name', data_get($record->payload, 'customer.name', '-')))
                            ->html(),

                        TextEntry::make('receiver_contact')
                            ->label('Contact')
                            ->state(fn ($record) => data_get($record->payload, 'receiver.phoneNumber', data_get($record->payload, 'customer.contact.phone', '-'))),

                        TextEntry::make('shipping_address')
                            ->label('Shipping Address')
                            ->state(fn ($record) => (string) data_get($record->payload, 'receiver.address', data_get($record->payload, 'shippingAddress.address', '-')))
                            ->html(),

                        // Price summary as key => value pairs
                        KeyValueEntry::make('price_summary')
                            ->label('Summary')
                            ->state(fn ($record) => [
                                'Subtotal' => number_format((float) data_get($record->payload, 'price.subTotal', 0), 2),
                                'Discount' => '-' . number_format((float) data_get($record->payload, 'price.discountedAmount', 0), 2),
                                'Delivery' => number_format((float) data_get($record->payload, 'price.deliveryDiscountAmount', 0), 2),
                                'Total' => number_format((float) data_get($record->payload, 'price.customerPayable', 0), 2),
                            ]),
                    ])->columns(1)->columnSpan(1),
                ]),
        ]);
    }
}
