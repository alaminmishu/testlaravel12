<?php

namespace App\Filament\Resources\Notes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Carbon\Carbon;
class NotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Example 2: Date Range with custom format and separator
                DateRangeFilter::make('created_at')
                    ->label('Creation Period')
                    ->displayFormat('d M Y')
                    ->ranges([
                        'Today' => [Carbon::today(), Carbon::today()],
                        'Yesterday' => [Carbon::yesterday(), Carbon::yesterday()],
                        'This Week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
                        'Last Week' => [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()],
                        'This Month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
                        'Last Month' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
                        'This Year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()],
                        'Last 30 Days' => [Carbon::now()->subDays(30), Carbon::now()],
                        'Last 90 Days' => [Carbon::now()->subDays(90), Carbon::now()],
                    ]),
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
