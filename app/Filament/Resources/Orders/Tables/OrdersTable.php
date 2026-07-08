<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class OrdersTable
{
    /**
     * @return array<string, string>
     */
    protected static function statusOptions(): array
    {
        return [
            'CANCELLED' => 'Cancelled',
            'CONFIRMED' => 'Confirmed',
            'DELIVERED' => 'Delivered',
            'INITIAL' => 'Initial',
            'PENDING' => 'Pending',
            'PROCESSING' => 'Processing',
            'REJECTED' => 'Rejected',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function paymentMethodOptions(): array
    {
        return [
            'BKASH' => 'bKash',
            'COD' => 'Cash on Delivery',
            'DBBL' => 'DBBL',
            'NAGAD' => 'Nagad',
            'SEBL' => 'SEBL',
            'TBL' => 'TBL',
            'UCB' => 'UCB',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function paymentStatusOptions(): array
    {
        return [
            'ACCEPTED' => 'Accepted',
            'INITIAL' => 'Initial',
            'PENDING' => 'Pending',
            'REJECTED' => 'Rejected',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function platformTypeOptions(): array
    {
        return [
            'B2C' => 'B2C',
            'B2B' => 'B2B',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function devicePlatformTypeOptions(): array
    {
        return [
            'WEB' => 'Web',
            'APP' => 'App',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function divisionOptions(): array
    {
        return Order::query()
            ->fromMongo()
            ->whereNotNull('division_name')
            ->distinct()
            ->orderBy('division_name')
            ->pluck('division_name', 'division_name')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function zoneOptions(): array
    {
        return Order::query()
            ->fromMongo()
            ->whereNotNull('zone_name')
            ->distinct()
            ->orderBy('zone_name')
            ->pluck('zone_name', 'zone_name')
            ->all();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uid')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                TextColumn::make('created_at_external')
                    ->label('Order Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->sortable(),
                TextColumn::make('payment_txn_id'),
                TextColumn::make('total_amount')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('customer_email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('zone_name')
                    ->label('Zone'),
                TextColumn::make('area_name')
                    ->label('Area')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('division_name')
                    ->label('Division')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('platform_type')
                    ->label('Platform')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('device_platform_type')
                    ->label('Device')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('promo_code')
                    ->label('Promo Code')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at_external')
                    ->label('Last Updated')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Synced At')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                // Group by ORDER DATE (by day, ignoring time)
                Group::make('created_at_external')
                    ->label('Order date')
                    // Use the date (Y-m-d) as the grouping key so all orders from same day are together
                    ->getKeyFromRecordUsing(fn ($record) => optional($record->created_at_external)->toDateString()
                    )
                    // Human-friendly group title (e.g., "Sep 13, 2024")
                    ->getTitleFromRecordUsing(fn ($record) => optional($record->created_at_external)
                        ? Carbon::parse($record->created_at_external)->format('M j, Y')
                        : '—'
                    )
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->multiple()
                    ->options(self::statusOptions())
                    ->indicateUsing(fn (array $data): ?string => ! empty($data['values']) ? 'Status: '.implode(', ', (array) $data['values']) : null),

                SelectFilter::make('payment_method')
                    ->multiple()
                    ->options(self::paymentMethodOptions())
                    ->indicateUsing(fn (array $data): ?string => ! empty($data['values']) ? 'Payment method: '.implode(', ', (array) $data['values']) : null),

                Filter::make('created_between')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at_external', '>=', $d))
                            ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at_external', '<=', $d));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['from'] ?? null) && ! ($data['until'] ?? null)) {
                            return null;
                        }
                        $from = $data['from'] ? Carbon::parse($data['from'])->toFormattedDateString() : '…';
                        $until = $data['until'] ? Carbon::parse($data['until'])->toFormattedDateString() : '…';

                        return "Date: {$from} → {$until}";
                    }),

                QueryBuilder::make()
                    ->constraints([
                        SelectConstraint::make('status')
                            ->options(self::statusOptions())
                            ->multiple()
                            ->searchable(),
                        SelectConstraint::make('payment_method')
                            ->options(self::paymentMethodOptions())
                            ->multiple()
                            ->searchable(),
                        SelectConstraint::make('payment_status')
                            ->options(self::paymentStatusOptions())
                            ->multiple()
                            ->searchable(),
                        SelectConstraint::make('platform_type')
                            ->label('Platform')
                            ->options(self::platformTypeOptions())
                            ->multiple(),
                        SelectConstraint::make('device_platform_type')
                            ->label('Device')
                            ->options(self::devicePlatformTypeOptions())
                            ->multiple(),
                        SelectConstraint::make('division_name')
                            ->label('Division')
                            ->options(self::divisionOptions())
                            ->multiple()
                            ->searchable(),
                        SelectConstraint::make('zone_name')
                            ->label('Zone')
                            ->options(self::zoneOptions())
                            ->multiple()
                            ->searchable(),
                        NumberConstraint::make('total_amount')
                            ->label('Order Total'),
                        TextConstraint::make('customer_email')
                            ->label('Customer Email'),
                        TextConstraint::make('promo_code')
                            ->label('Promo Code'),
                        DateConstraint::make('created_at_external')
                            ->label('Order Date'),
                    ]),
            ])
            ->headerActions([
                ExportAction::make('export')
                    ->label('Export')
                    ->exports([
                        // Respects the table's current search, filters, column visibility, and sorting
                        ExcelExport::make('orders-filtered')
                            ->label('Current view (filtered)')
                            ->fromTable()
                            ->withFilename('orders-filtered-'.now()->format('d-m-Y_H-i'))
                            ->withWriterType(Excel::XLSX),
                        // Always exports every order, ignoring any active filters/search
                        ExcelExport::make('orders-all')
                            ->label('All orders (ignore filters)')
                            ->withFilename('orders-all-'.now()->format('d-m-Y_H-i'))
                            ->withWriterType(Excel::XLSX),
                    ]),
            ])

            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
