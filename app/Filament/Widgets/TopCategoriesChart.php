<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToDashboardFilters;
use App\Models\OrderItem;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class TopCategoriesChart extends ApexChartWidget
{
    use ScopesToDashboardFilters;

    protected static ?string $chartId = 'topCategoriesChart';

    protected static ?string $heading = 'Top 10 Categories by Revenue';

    public function rendering(): void
    {
        $this->updateOptions();
    }

    protected function getOptions(): array
    {
        $start = $this->filterStart();
        $end = $this->filterEnd();

        return $this->rememberForDashboard('options', function () use ($start, $end): array {
            $rows = OrderItem::query()
                ->whereHas('order', fn ($q) => $q->fromMongo()->whereBetween('created_at_external', [$start, $end]))
                ->selectRaw('category_name, SUM(mrp_price * quantity) as revenue')
                ->groupBy('category_name')
                ->orderByDesc('revenue')
                ->limit(10)
                ->pluck('revenue', 'category_name');

            return [
                'chart' => [
                    'type' => 'bar',
                    'height' => 350,
                ],
                'series' => [
                    [
                        'name' => 'Revenue',
                        'data' => array_map(fn ($v) => (float) $v, array_values($rows->toArray())),
                    ],
                ],
                'xaxis' => [
                    'categories' => array_map(fn (?string $name): string => $name ?? 'Unknown', $rows->keys()->toArray()),
                    'labels' => [
                        'style' => ['fontFamily' => 'inherit'],
                    ],
                ],
                'plotOptions' => [
                    'bar' => [
                        'horizontal' => true,
                        'borderRadius' => 3,
                    ],
                ],
                'colors' => ['#f59e0b'],
            ];
        });
    }
}
