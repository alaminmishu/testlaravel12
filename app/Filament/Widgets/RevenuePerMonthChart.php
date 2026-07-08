<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ScopesToDashboardFilters;
use App\Models\Order;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class RevenuePerMonthChart extends ApexChartWidget
{
    use ScopesToDashboardFilters;

    // Load immediately when the dashboard renders; no polling
    protected static bool $deferLoading = false;

    public function rendering(): void
    {
        $this->updateOptions();
    }

    protected function getHeading(): string|Htmlable|View|null
    {
        return 'Revenue ('.$this->filterStart()->format('M Y').' – '.$this->filterEnd()->format('M Y').')';
    }

    protected function getOptions(): array
    {
        $start = $this->filterStart();
        $end = $this->filterEnd();

        return $this->rememberForDashboard('options', function () use ($start, $end): array {
            // Build ordered month buckets (YYYY-MM => 0)
            $period = CarbonPeriod::create($start->copy()->startOfMonth(), '1 month', $end->copy()->endOfMonth());
            $labels = [];
            $revenueByMonth = [];
            foreach ($period as $m) {
                $key = $m->format('Y-m');
                $labels[] = $m->format('M');
                $revenueByMonth[$key] = 0.0;
            }

            $rows = Order::query()
                ->fromMongo()
                ->whereBetween('created_at_external', [$start, $end])
                ->selectRaw("DATE_FORMAT(created_at_external, '%Y-%m') as ym, SUM(total_amount) as revenue")
                ->groupBy('ym')
                ->pluck('revenue', 'ym');

            foreach ($rows as $ym => $revenue) {
                if (array_key_exists($ym, $revenueByMonth)) {
                    $revenueByMonth[$ym] = (float) $revenue;
                }
            }

            return [
                'chart' => [
                    'type' => 'bar',
                    'height' => 320,
                    'toolbar' => ['show' => false],
                ],
                'plotOptions' => [
                    'bar' => [
                        'horizontal' => false,
                        'columnWidth' => '45%',
                        'borderRadius' => 6,
                    ],
                ],
                'series' => [
                    ['name' => 'Revenue', 'data' => array_values($revenueByMonth)],
                ],
                'xaxis' => [
                    'type' => 'category',
                    'categories' => $labels,
                    'labels' => ['rotate' => -45],
                ],
                'yaxis' => [
                    [
                        'title' => ['text' => 'BDT'],
                    ],
                ],
                'dataLabels' => ['enabled' => false],
                'legend' => [
                    'show' => true,
                    'position' => 'top',
                    'horizontalAlign' => 'right',
                ],
                'fill' => [
                    'type' => 'gradient',
                    'gradient' => [
                        'shadeIntensity' => 0.35,
                        'opacityFrom' => 0.9,
                        'opacityTo' => 0.9,
                    ],
                ],
                'grid' => ['strokeDashArray' => 3],
            ];
        });
    }
}
