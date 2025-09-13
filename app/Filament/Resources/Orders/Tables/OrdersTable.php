<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Maatwebsite\Excel\Excel;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uid')
                ->searchable(),
                TextColumn::make('status')
                ->searchable(),
                TextColumn::make('created_at'),
                TextColumn::make('payment_method'),
                TextColumn::make('payment_status'),
                TextColumn::make('payment_txn_id'),
                TextColumn::make('total_amount'),
//                TextColumn::make('customer_email'),
            ])
            ->groups([
                // Group by ORDER DATE (by day, ignoring time)
                Group::make('created_at_external')
                    ->label('Order date')
                    // Use the date (Y-m-d) as the grouping key so all orders from same day are together
                    ->getKeyFromRecordUsing(fn ($record) =>
                    optional($record->created_at_external)->toDateString()
                    )
                    // Human-friendly group title (e.g., "Sep 13, 2024")
                    ->getTitleFromRecordUsing(fn ($record) =>
                    optional($record->created_at_external)
                        ? Carbon::parse($record->created_at_external)->format('M j, Y')
                        : '—'
                    )
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->multiple()
                    ->options([
                        'PENDING' => 'Pending',
                        'PROCESSING' => 'Processing',
                        'DELIVERED' => 'Delivered',
                        'REJECTED' => 'Rejected',
                    ])
                    ->indicateUsing(fn (array $data): ?string =>
                    ! empty($data['values']) ? 'Status: ' . implode(', ', (array) $data['values']) : null),

                SelectFilter::make('payment_method')
                    ->multiple()
                    ->options([
                        'COD' => 'COD',
                        'BKASH' => 'bKASH',
                        'EMI' => 'EMI',
                        'UCB-VISA' => 'UCB_VISA',
                    ])
                    ->indicateUsing(fn (array $data): ?string =>
                    ! empty($data['values']) ? 'Status: ' . implode(', ', (array) $data['values']) : null),
                Filter::make('created_between')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from']  ?? null, fn (Builder $q, $d) => $q->whereDate('created_at_external', '>=', $d))
                            ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at_external', '<=', $d));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!($data['from'] ?? null) && !($data['until'] ?? null)) return null;
                        $from  = $data['from']  ? Carbon::parse($data['from'])->toFormattedDateString()  : '…';
                        $until = $data['until'] ? Carbon::parse($data['until'])->toFormattedDateString() : '…';
                        return "Date: {$from} → {$until}";
                    }),
            ])
            ->headerActions([
                ExportAction::make('export')
                    ->label('Export')
                    ->exports([
                        // Export the current table (respects search, filters, column visibility, sorting, and pagination scope)
                        ExcelExport::make('orders-visible')
                            ->fromTable()
                            ->withFilename('orders-' . now()->format('d-m-Y_H-i'))
                            ->withWriterType(Excel::XLSX)
//                            ->askForFilename()   // let user rename
//                            ->askForWriterType() // XLSX/CSV/TSV/ODS...
//                         ->queue()         // uncomment for large exports (uses your queue/Horizon)
                    ]),
            ])

            ->recordActions([
                ViewAction::make(),
//                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
//                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
