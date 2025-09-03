<?php

namespace App\Filament\Widgets;

use App\Models\Note;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Carbon\Carbon;

class NotesTrendChart extends ApexChartWidget
{
    /**
     * Chart Id
     */
    protected static ?string $chartId = 'notesTrendChart';

    /**
     * Widget Title
     */
    protected static ?string $heading = 'Notes Trend (Active vs Inactive)';

    /**
     * Sort
     */
    protected static ?int $sort = 3;

    /**
     * Widget Height
     */
    protected static ?int $contentHeight = 350;

    /**
     * Chart options
     */
    protected function getOptions(): array
    {
        $data = $this->getChartData();

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 350,
                'stacked' => true,
                'toolbar' => [
                    'show' => false,
                ],
                'zoom' => [
                    'enabled' => false,
                ],
            ],
            'series' => [
                [
                    'name' => 'Active Notes',
                    'data' => $data['active'],
                    'color' => '#10B981',
                ],
                [
                    'name' => 'Inactive Notes',
                    'data' => $data['inactive'],
                    'color' => '#EF4444',
                ],
            ],
            'plotOptions' => [
                'bar' => [
                    'horizontal' => false,
                    'borderRadius' => 4,
                    'borderRadiusApplication' => 'end',
                    'columnWidth' => '70%',
                ],
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
            'stroke' => [
                'width' => 1,
                'colors' => ['#fff'],
            ],
            'xaxis' => [
                'categories' => $data['categories'],
                'labels' => [
                    'style' => [
                        'fontSize' => '12px',
                        'fontWeight' => 400,
                    ],
                ],
            ],
            'yaxis' => [
                'title' => [
                    'text' => 'Number of Notes',
                    'style' => [
                        'fontSize' => '14px',
                        'fontWeight' => 500,
                    ],
                ],
                'labels' => [
                    'style' => [
                        'fontSize' => '12px',
                    ],
                ],
            ],
            'colors' => ['#10B981', '#EF4444'],
            'legend' => [
                'position' => 'top',
                'horizontalAlign' => 'right',
                'fontSize' => '14px',
                'fontWeight' => 500,
            ],
            'tooltip' => [
                'shared' => true,
                'intersect' => false,
                'y' => [
                    'formatter' => 'function (val) {
                        return val + " notes"
                    }',
                ],
            ],
            'fill' => [
                'opacity' => 1,
            ],
            'grid' => [
                'borderColor' => '#e0e6ed',
                'strokeDashArray' => 5,
                'xaxis' => [
                    'lines' => [
                        'show' => false,
                    ],
                ],
                'yaxis' => [
                    'lines' => [
                        'show' => true,
                    ],
                ],
            ],
            'responsive' => [
                [
                    'breakpoint' => 600,
                    'options' => [
                        'plotOptions' => [
                            'bar' => [
                                'borderRadius' => 2,
                                'columnWidth' => '80%',
                            ]
                        ],
                        'legend' => [
                            'position' => 'bottom',
                        ],
                    ]
                ]
            ]
        ];
    }

    /**
     * Filters for the chart
     */
    protected function getFilters(): ?array
    {
        return [
            'week' => 'Last 7 Days',
            'month' => 'Last 30 Days',
            'quarter' => 'Last 3 Months',
        ];
    }

    /**
     * Get chart data based on current filter
     */
    protected function getChartData(): array
    {
        $filter = $this->filter ?? 'month';

        return match ($filter) {
            'week' => $this->getWeeklyData(),
            'month' => $this->getMonthlyData(),
            'quarter' => $this->getQuarterlyData(),
            default => $this->getMonthlyData(),
        };
    }

    /**
     * Get data for last 7 days
     */
    private function getWeeklyData(): array
    {
        $categories = [];
        $activeData = [];
        $inactiveData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $categories[] = $date->format('M j');

            $activeCount = Note::whereDate('created_at', $date)
                ->where('active', true)
                ->count();

            $inactiveCount = Note::whereDate('created_at', $date)
                ->where('active', false)
                ->count();

            $activeData[] = $activeCount;
            $inactiveData[] = $inactiveCount;
        }

        return [
            'categories' => $categories,
            'active' => $activeData,
            'inactive' => $inactiveData,
        ];
    }

    /**
     * Get data for last 30 days (grouped by week)
     */
    private function getMonthlyData(): array
    {
        $categories = [];
        $activeData = [];
        $inactiveData = [];

        for ($i = 3; $i >= 0; $i--) {
            $startDate = Carbon::now()->subWeeks($i + 1)->startOfWeek();
            $endDate = Carbon::now()->subWeeks($i)->endOfWeek();

            $categories[] = 'Week ' . (4 - $i);

            $activeCount = Note::whereBetween('created_at', [$startDate, $endDate])
                ->where('active', true)
                ->count();

            $inactiveCount = Note::whereBetween('created_at', [$startDate, $endDate])
                ->where('active', false)
                ->count();

            $activeData[] = $activeCount;
            $inactiveData[] = $inactiveCount;
        }

        return [
            'categories' => $categories,
            'active' => $activeData,
            'inactive' => $inactiveData,
        ];
    }

    /**
     * Get data for last 3 months
     */
    private function getQuarterlyData(): array
    {
        $categories = [];
        $activeData = [];
        $inactiveData = [];

        for ($i = 2; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $categories[] = $date->format('M Y');

            $activeCount = Note::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->where('active', true)
                ->count();

            $inactiveCount = Note::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->where('active', false)
                ->count();

            $activeData[] = $activeCount;
            $inactiveData[] = $inactiveCount;
        }

        return [
            'categories' => $categories,
            'active' => $activeData,
            'inactive' => $inactiveData,
        ];
    }
}
