<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\NotesStatusChart;
use App\Filament\Widgets\NotesTrendChart;
use App\Filament\Widgets\NotesStatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * Get the widgets that should be displayed on the dashboard.
     */
    public function getWidgets(): array
    {
        return [
            NotesStatsOverview::class,
            NotesStatusChart::class,
            NotesTrendChart::class,
        ];
    }

    /**
     * Get the number of columns the widgets should be displayed in.
     */
    public function getColumns(): array|int
    {
        return [
            'sm' => 1,
            'md' => 2,
            'xl' => 3,
        ];
    }
}
