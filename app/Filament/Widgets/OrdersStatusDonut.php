<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class OrdersStatusDonut extends ApexChartWidget
{
    protected static ?string $chartId = 'ordersStatusDonut';

    protected static ?string $heading = 'Orders by Status';

    protected function getOptions(): array
    {
        $counts = Order::query()
            ->fromMongo()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->pluck('total', 'status');

        return [
            'chart' => [
                'type' => 'donut',
                'height' => 300,
            ],
            'series' => array_values($counts->toArray()),
            'labels' => array_map(fn (?string $status): string => $status ?? 'Unknown', $counts->keys()->toArray()),
            'legend' => [
                'labels' => [
                    'fontFamily' => 'inherit',
                ],
            ],
        ];
    }
}
