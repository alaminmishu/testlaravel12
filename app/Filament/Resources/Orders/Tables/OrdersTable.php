<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
                TextColumn::make('total_amount'),
                TextColumn::make('customer_email'),
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
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
