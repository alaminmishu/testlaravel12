<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class RevenueByZoneChart extends ApexChartWidget
{
    protected static ?string $chartId = 'revenueByZoneChart';

    protected static ?string $heading = 'Revenue by Zone';

    protected function getOptions(): array
    {
        $rows = Order::query()
            ->fromMongo()
            ->whereNotNull('zone_name')
            ->selectRaw('zone_name, SUM(total_amount) as revenue')
            ->groupBy('zone_name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->pluck('revenue', 'zone_name');

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
                'categories' => $rows->keys()->toArray(),
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
    }
}
