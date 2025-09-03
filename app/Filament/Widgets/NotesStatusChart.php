<?php

namespace App\Filament\Widgets;

use App\Models\Note;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class NotesStatusChart extends ApexChartWidget
{
    /**
     * Chart Id
     */
    protected static ?string $chartId = 'notesStatusChart';

    /**
     * Widget Title
     */
    protected static ?string $heading = 'Notes by Status';

    /**
     * Sort
     */
    protected static ?int $sort = 2;

    /**
     * Widget Height
     */
    protected static ?int $contentHeight = 300;

    /**
     * Chart options (series, labels, types, size, animations...)
     * https://apexcharts.com/docs/options
     */
    protected function getOptions(): array
    {
        $data = $this->getChartData();

        return [
            'chart' => [
                'type' => 'donut',
                'height' => 300,
            ],
            'series' => $data['series'],
            'labels' => $data['labels'],
            'colors' => ['#10B981', '#EF4444'], // Green for active, Red for inactive
            'legend' => [
                'position' => 'bottom',
                'horizontalAlign' => 'center',
            ],
            'plotOptions' => [
                'pie' => [
                    'donut' => [
                        'size' => '70%',
                        'labels' => [
                            'show' => true,
                            'total' => [
                                'show' => true,
                                'showAlways' => false,
                                'label' => 'Total Notes',
                                'fontSize' => '16px',
                                'fontWeight' => 600,
                                'color' => '#373d3f',
                            ]
                        ]
                    ]
                ]
            ],
            'dataLabels' => [
                'enabled' => true,
                'formatter' => 'function (val, opts) {
                    return opts.w.config.series[opts.seriesIndex]
                }',
            ],
            'tooltip' => [
                'enabled' => true,
                'y' => [
                    'formatter' => 'function (val) {
                        return val + " notes"
                    }',
                ],
            ],
            'responsive' => [
                [
                    'breakpoint' => 480,
                    'options' => [
                        'chart' => [
                            'width' => 280
                        ],
                        'legend' => [
                            'position' => 'bottom'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get chart data based on filter
     */
    protected function getChartData(): array
    {
        $activeFilter = $this->filter ?? 'all';

        $query = Note::query();

        // Apply date filters
        match ($activeFilter) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            'year' => $query->where('created_at', '>=', now()->subYear()),
            default => $query, // All time
        };

        $activeNotes = (clone $query)->where('active', true)->count();
        $inactiveNotes = (clone $query)->where('active', false)->count();

        return [
            'series' => [$activeNotes, $inactiveNotes],
            'labels' => ['Active Notes', 'Inactive Notes'],
        ];
    }

    /**
     * Filters for the chart
     */
    protected function getFilters(): ?array
    {
        return [
            'all' => 'All Time',
            'today' => 'Today',
            'week' => 'Last Week',
            'month' => 'Last Month',
            'year' => 'This Year',
        ];
    }
}
