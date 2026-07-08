<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToDashboardFilters;
use App\Models\Order;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class OrdersStatusDonut extends ApexChartWidget
{
    use ScopesToDashboardFilters;

    protected static ?string $chartId = 'ordersStatusDonut';

    protected static ?string $heading = 'Orders by Status';

    public function rendering(): void
    {
        $this->updateOptions();
    }

    protected function getOptions(): array
    {
        $start = $this->filterStart();
        $end = $this->filterEnd();

        return $this->rememberForDashboard('options', function () use ($start, $end): array {
            $counts = Order::query()
                ->fromMongo()
                ->whereBetween('created_at_external', [$start, $end])
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
        });
    }
}
