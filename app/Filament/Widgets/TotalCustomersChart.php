<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class TotalCustomersChart extends ApexChartWidget
{
    protected static ?string $chartId = 'totalCustomersChart';

    protected static ?string $heading = 'Active Customers (last 12 months)';

    protected function getOptions(): array
    {
        $endOfThisMonth = Carbon::now()->startOfMonth()->endOfMonth();
        $startOfWindow = $endOfThisMonth->copy()->subMonths(11)->startOfMonth();

        $period = CarbonPeriod::create($startOfWindow, '1 month', $endOfThisMonth);
        $labels = [];
        $countsByMonth = [];
        foreach ($period as $m) {
            $key = $m->format('Y-m');
            $labels[] = $m->format('M');
            $countsByMonth[$key] = 0;
        }

        $rows = Order::query()
            ->fromMongo()
            ->whereNotNull('customer_email')
            ->whereBetween('created_at_external', [$startOfWindow, $endOfThisMonth])
            ->selectRaw("DATE_FORMAT(created_at_external, '%Y-%m') as ym, COUNT(DISTINCT customer_email) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        foreach ($rows as $ym => $total) {
            if (array_key_exists($ym, $countsByMonth)) {
                $countsByMonth[$ym] = (int) $total;
            }
        }

        return [
            'chart' => [
                'type' => 'line',
                'height' => 300,
            ],
            'series' => [
                [
                    'name' => 'Active Customers',
                    'data' => array_values($countsByMonth),
                ],
            ],
            'xaxis' => [
                'categories' => $labels,
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'colors' => ['#f59e0b'],
            'stroke' => [
                'curve' => 'smooth',
            ],
        ];
    }
}
