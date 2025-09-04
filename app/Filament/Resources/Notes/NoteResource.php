<?php

namespace App\Filament\Resources\Notes;

use App\Filament\Resources\Notes\Pages\CreateNote;
use App\Filament\Resources\Notes\Pages\EditNote;
use App\Filament\Resources\Notes\Pages\ListNotes;
use App\Filament\Resources\Notes\Pages\ViewNote;
use App\Filament\Resources\Notes\Schemas\NoteForm;
use App\Filament\Resources\Notes\Schemas\NoteInfolist;
use App\Filament\Resources\Notes\Tables\NotesTable;
use App\Models\Note;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    // Add navigation badge to show total count
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return NoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotesTable::configure($table)
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    // Basic Excel Export
                    ExportBulkAction::make()
                        ->label('Export to Excel')
                        ->color('success'),

                    // Custom Excel Export with specific columns
                    ExportBulkAction::make('export_detailed')
                        ->label('Export Detailed Report')
                        ->color('primary')
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withFilename(fn () => 'notes-report-' . date('Y-m-d'))
                                ->withWriterType(\Maatwebsite\Excel\Excel::XLSX)
                                ->withColumns([
                                    Column::make('id')
                                        ->heading('ID'),
                                    Column::make('title')
                                        ->heading('Note Title'),
                                    Column::make('content')
                                        ->heading('Content'),
                                    Column::make('active')
                                        ->heading('Status')
                                        ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),
                                    Column::make('created_at')
                                        ->heading('Created Date')
                                        ->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i:s')),
                                    Column::make('updated_at')
                                        ->heading('Updated Date')
                                        ->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i:s')),
                                ])
                        ]),

                    // Export filtered results only
                    ExportBulkAction::make('export_filtered')
                        ->label('Export Filtered Results')
                        ->color('warning')
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->only(['title', 'content', 'active', 'created_at'])
                                ->withFilename(fn () => 'filtered-notes-' . date('Y-m-d-H-i'))
                        ]),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotes::route('/'),
            'create' => CreateNote::route('/create'),
            'view' => ViewNote::route('/{record}'),
            'edit' => EditNote::route('/{record}/edit'),
        ];
    }

    // Add global search capability
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'content'];
    }
}
