<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToDashboardFilters;
use App\Models\Order;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class OrdersPerMonthChart extends ApexChartWidget
{
    use ScopesToDashboardFilters;

    protected static ?string $chartId = 'ordersPerMonthChart';

    public function rendering(): void
    {
        $this->updateOptions();
    }

    protected function getHeading(): string|Htmlable|View|null
    {
        return 'Orders ('.$this->filterStart()->format('M Y').' – '.$this->filterEnd()->format('M Y').')';
    }

    protected function getOptions(): array
    {
        $start = $this->filterStart();
        $end = $this->filterEnd();

        return $this->rememberForDashboard('options', function () use ($start, $end): array {
            $period = CarbonPeriod::create($start->copy()->startOfMonth(), '1 month', $end->copy()->endOfMonth());
            $labels = [];
            $countsByMonth = [];
            foreach ($period as $m) {
                $key = $m->format('Y-m');
                $labels[] = $m->format('M');
                $countsByMonth[$key] = 0;
            }

            $rows = Order::query()
                ->fromMongo()
                ->whereBetween('created_at_external', [$start, $end])
                ->selectRaw("DATE_FORMAT(created_at_external, '%Y-%m') as ym, COUNT(*) as total")
                ->groupBy('ym')
                ->pluck('total', 'ym');

            foreach ($rows as $ym => $total) {
                if (array_key_exists($ym, $countsByMonth)) {
                    $countsByMonth[$ym] = (int) $total;
                }
            }

            return [
                'chart' => [
                    'type' => 'bar',
                    'height' => 300,
                ],
                'series' => [
                    [
                        'name' => 'Orders',
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
                'plotOptions' => [
                    'bar' => [
                        'borderRadius' => 3,
                        'horizontal' => true,
                    ],
                ],
            ];
        });
    }
}
