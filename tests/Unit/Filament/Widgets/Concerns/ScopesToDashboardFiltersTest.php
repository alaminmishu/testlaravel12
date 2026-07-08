<?php

namespace Tests\Unit\Filament\Widgets\Concerns;

use App\Filament\Widgets\Concerns\ScopesToDashboardFilters;
use App\Models\SyncState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopesToDashboardFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function widget(?array $pageFilters = null): object
    {
        return new class($pageFilters)
        {
            use ScopesToDashboardFilters;

            public function __construct(?array $pageFilters)
            {
                $this->pageFilters = $pageFilters;
            }

            public function start()
            {
                return $this->filterStart();
            }

            public function end()
            {
                return $this->filterEnd();
            }

            public function key(string $suffix): string
            {
                return $this->dashboardCacheKey($suffix);
            }
        };
    }

    public function test_it_defaults_to_trailing_12_months_when_no_filters_set(): void
    {
        $widget = $this->widget();

        $this->assertTrue($widget->start()->lessThan($widget->end()));
        $this->assertEqualsWithDelta(
            now()->subMonths(11)->startOfMonth()->timestamp,
            $widget->start()->timestamp,
            2
        );
    }

    public function test_it_uses_explicit_page_filter_dates_when_set(): void
    {
        $widget = $this->widget(['startDate' => '2026-01-01', 'endDate' => '2026-01-31']);

        $this->assertSame('2026-01-01', $widget->start()->toDateString());
        $this->assertSame('2026-01-31', $widget->end()->toDateString());
    }

    public function test_cache_key_changes_when_filters_change(): void
    {
        $a = $this->widget(['startDate' => '2026-01-01', 'endDate' => '2026-01-31']);
        $b = $this->widget(['startDate' => '2026-02-01', 'endDate' => '2026-02-28']);

        $this->assertNotSame($a->key('options'), $b->key('options'));
    }

    public function test_cache_key_changes_when_sync_watermark_changes(): void
    {
        $widget = $this->widget(['startDate' => '2026-01-01', 'endDate' => '2026-01-31']);

        $before = $widget->key('options');

        SyncState::setWatermark(\App\Console\Commands\SyncOrdersFromMongoCommand::WATERMARK_KEY, now());

        $after = $widget->key('options');

        $this->assertNotSame($before, $after);
    }
}
