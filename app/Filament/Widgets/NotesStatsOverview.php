<?php

namespace App\Filament\Widgets;

use App\Models\Note;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NotesStatsOverview extends BaseWidget
{
    /**
     * Sort
     */
    protected static ?int $sort = 1;

    /**
     * Get the stats for the overview
     */
    protected function getStats(): array
    {
        // Get counts
        $totalNotes = Note::count();
        $activeNotes = Note::where('active', true)->count();
        $inactiveNotes = Note::where('active', false)->count();

        // Calculate percentages
        $activePercentage = $totalNotes > 0 ? round(($activeNotes / $totalNotes) * 100, 1) : 0;
        $inactivePercentage = $totalNotes > 0 ? round(($inactiveNotes / $totalNotes) * 100, 1) : 0;

        // Get trend data (last 7 days vs previous 7 days)
        $currentWeekTotal = Note::where('created_at', '>=', now()->subWeek())->count();
        $previousWeekTotal = Note::whereBetween('created_at', [
            now()->subWeeks(2),
            now()->subWeek()
        ])->count();

        $currentWeekActive = Note::where('created_at', '>=', now()->subWeek())
            ->where('active', true)
            ->count();
        $previousWeekActive = Note::whereBetween('created_at', [
            now()->subWeeks(2),
            now()->subWeek()
        ])->where('active', true)->count();

        // Calculate trends
        $totalTrend = $previousWeekTotal > 0
            ? round((($currentWeekTotal - $previousWeekTotal) / $previousWeekTotal) * 100, 1)
            : ($currentWeekTotal > 0 ? 100 : 0);

        $activeTrend = $previousWeekActive > 0
            ? round((($currentWeekActive - $previousWeekActive) / $previousWeekActive) * 100, 1)
            : ($currentWeekActive > 0 ? 100 : 0);

        return [
            Stat::make('Total Notes', number_format($totalNotes))
                ->description($totalTrend >= 0 ? "+{$totalTrend}% from last week" : "{$totalTrend}% from last week")
                ->descriptionIcon($totalTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($totalTrend >= 0 ? 'success' : 'danger')
                ->chart($this->getTotalNotesChart()),

            Stat::make('Active Notes', number_format($activeNotes))
                ->description("{$activePercentage}% of total notes")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart($this->getActiveNotesChart()),

            Stat::make('Inactive Notes', number_format($inactiveNotes))
                ->description("{$inactivePercentage}% of total notes")
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->chart($this->getInactiveNotesChart()),
        ];
    }

    /**
     * Get chart data for total notes (last 7 days)
     */
    private function getTotalNotesChart(): array
    {
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $count = Note::whereDate('created_at', $date)->count();
            $data[] = $count;
        }

        return $data;
    }

    /**
     * Get chart data for active notes (last 7 days)
     */
    private function getActiveNotesChart(): array
    {
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $count = Note::whereDate('created_at', $date)
                ->where('active', true)
                ->count();
            $data[] = $count;
        }

        return $data;
    }

    /**
     * Get chart data for inactive notes (last 7 days)
     */
    private function getInactiveNotesChart(): array
    {
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $count = Note::whereDate('created_at', $date)
                ->where('active', false)
                ->count();
            $data[] = $count;
        }

        return $data;
    }
}
