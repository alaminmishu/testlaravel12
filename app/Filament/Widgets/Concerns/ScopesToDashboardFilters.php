<?php

namespace App\Filament\Widgets\Concerns;

use App\Console\Commands\SyncOrdersFromMongoCommand;
use App\Models\SyncState;
use Closure;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

trait ScopesToDashboardFilters
{
    use InteractsWithPageFilters;

    protected function filterStart(): Carbon
    {
        $startDate = $this->pageFilters['startDate'] ?? null;

        return $startDate
            ? Carbon::parse($startDate)->startOfDay()
            : Carbon::now()->subMonths(11)->startOfMonth();
    }

    protected function filterEnd(): Carbon
    {
        $endDate = $this->pageFilters['endDate'] ?? null;

        return $endDate
            ? Carbon::parse($endDate)->endOfDay()
            : Carbon::now()->endOfDay();
    }

    protected function dashboardCacheKey(string $suffix): string
    {
        $watermark = SyncState::getWatermark(SyncOrdersFromMongoCommand::WATERMARK_KEY)?->timestamp;

        return implode(':', [
            'dashboard',
            class_basename(static::class),
            $suffix,
            $this->filterStart()->timestamp,
            $this->filterEnd()->timestamp,
            $watermark ?? 'none',
        ]);
    }

    protected function rememberForDashboard(string $suffix, Closure $callback): mixed
    {
        return Cache::store('redis')->remember(
            $this->dashboardCacheKey($suffix),
            now()->addMinutes(10),
            $callback,
        );
    }
}
