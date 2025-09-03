<?php

namespace App\Filament\Resources\Notes\Pages;

use App\Exports\NotesExport;
use Filament\Actions;
use App\Filament\Resources\Notes\NoteResource;
//use App\Filament\Widgets\NotesStatusChart;
use Filament\Actions\CreateAction;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Tabs\Tab;

class ListNotes extends ListRecords
{
    protected static string $resource = NoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            // Simple Excel Export
            ExportAction::make()
                ->label('Export All Notes')
                ->color('success')
                ->exports([
                    ExcelExport::make()
                        ->fromTable()
                        ->withFilename('notes-export-' . date('Y-m-d'))
                        ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
                ]),

            // Advanced Export with Custom Options
            Actions\Action::make('advanced_export')
                ->label('Advanced Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->form([
                    \Filament\Forms\Components\Select::make('filter')
                        ->label('Export Filter')
                        ->options([
                            'all' => 'All Notes',
                            'active' => 'Active Notes Only',
                            'inactive' => 'Inactive Notes Only',
                            'today' => 'Created Today',
                            'week' => 'Created This Week',
                            'month' => 'Created This Month',
                        ])
                        ->default('all')
                        ->required(),

                    \Filament\Forms\Components\TextInput::make('filename')
                        ->label('File Name')
                        ->default('notes-report')
                        ->required()
                        ->suffix('.xlsx'),
                ])
                ->action(function (array $data) {
                    $filename = $data['filename'] . '-' . date('Y-m-d') . '.xlsx';

                    return Excel::download(
                        new NotesExport($data['filter']),
                        $filename
                    );
                })
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Export completed')
                        ->body('Your notes have been exported successfully.')
                ),

            // Quick Export Actions
            Actions\Action::make('export_active')
                ->label('Export Active')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    return Excel::download(
                        new NotesExport('active'),
                        'active-notes-' . date('Y-m-d') . '.xlsx'
                    );
                }),

            Actions\Action::make('export_inactive')
                ->label('Export Inactive')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->action(function () {
                    return Excel::download(
                        new NotesExport('inactive'),
                        'inactive-notes-' . date('Y-m-d') . '.xlsx'
                    );
                }),
        ];
    }

//    protected function getHeaderWidgets(): array
//    {
//        return [
//            NotesStatusChart::class,
//        ];
//    }
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(),
            'active' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('active', true)),
            'inactive' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('active', false)),
        ];
    }
}
