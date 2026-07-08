<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class TopSellersChart extends ApexChartWidget
{
    protected static ?string $chartId = 'topSellersChart';

    protected static ?string $heading = 'Top 10 Sellers by Revenue';

    protected function getOptions(): array
    {
        $rows = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->fromMongo())
            ->selectRaw('seller_name, SUM(mrp_price * quantity) as revenue')
            ->groupBy('seller_name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->pluck('revenue', 'seller_name');

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
    }
}
