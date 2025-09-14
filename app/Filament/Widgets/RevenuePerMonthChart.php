<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class RevenuePerMonthChart extends ApexChartWidget
{
    protected static ?string $heading = 'Revenue (last 12 months)';

    // Load immediately when the dashboard renders; no polling
    protected static bool $deferLoading = false;
//    protected static ?string $pollingInterval = null;

    // app/Filament/Widgets/RevenuePerMonthChart.php

    protected function getOptions(): array
    {

        // Window: last 12 months including current month
        $endOfThisMonth = Carbon::now()->startOfMonth()->endOfMonth();
        $startOfWindow  = $endOfThisMonth->copy()->subMonths(11)->startOfMonth();

        // Build ordered month buckets (YYYY-MM => 0)
        $period   = CarbonPeriod::create($startOfWindow, '1 month', $endOfThisMonth);
        $labels   = [];
        $earnings = [];
        $expenses = [];
        foreach ($period as $m) {
            $key = $m->format('Y-m');
            $labels[] = $m->format('M');
            $earnings[$key] = 0.0; // positive
            $expenses[$key] = 0.0; // will accumulate NEGATIVE values for below-zero stack
        }

        // Pull orders in the window (id, created_at_external, total_amount BDT)
        // If you only have 'amount' in MINOR units, swap the select below accordingly.
        $orders = Order::query()
            ->whereBetween('created_at_external', [$startOfWindow, $endOfThisMonth])
            ->get(['id', 'created_at_external', 'total_amount']); // total_amount in BDT

        foreach ($orders as $o) {
            if ($o->total_amount === null) {
                // Fallback if you only have 'amount' in MINOR units:
                // $orderValueBDT = ((int) $o->amount) / 100;
                // If both missing, skip.
                continue;
            }

            $orderValueBDT = (float) $o->total_amount;

            // --- Deterministic "random" percentages per order (stable across reloads) ---
            // Two independent seeds derived from the order id.
            $revSeed  = crc32($o->id . '|rev');
            $costSeed = crc32($o->id . '|cost');

            // Uniform 0..1 floats
            $uRev  = ($revSeed  % 10000) / 10000; // 0.0000 .. 0.9999
            $uCost = ($costSeed % 10000) / 10000;

            // Revenue ~ 20% with variation: 15%..25%
            $revenuePct = 0.15 + $uRev * 0.10;

            // Cost ≤ 70%, looks realistic: try 50%..70%, but never exceed (1 - revenuePct - 5% headroom)
            $maxCostAllowed = min(0.70, 1.0 - $revenuePct - 0.05); // keep ≥5% for "other"
            $minCost        = 0.50;
            $maxCost        = max($minCost, $maxCostAllowed);       // clamp if tight

            // If headroom got too tight (e.g., edge case), fall back to 50% exactly.
            $costPct = $minCost;
            if ($maxCost > $minCost) {
                $costPct = $minCost + $uCost * ($maxCost - $minCost);
            }

            // Monetary amounts
            $earningBDT = round($orderValueBDT * $revenuePct, 2);     // positive
            $expenseBDT = -round($orderValueBDT * $costPct, 2);       // NEGATIVE for below-zero stack

            $ym = Carbon::parse($o->created_at_external)->format('Y-m');
            if (array_key_exists($ym, $earnings)) {
                $earnings[$ym] += $earningBDT;
                $expenses[$ym] += $expenseBDT;
            }
        }

        // Prepare aligned series
        $seriesEarning = array_values($earnings); // positive bars (top)
        $seriesExpense = array_values($expenses); // negative bars (bottom)

        return [
            'chart' => [
                'type' => 'bar',
                'stacked' => true,     // stacked columns
                'height' => 320,
                'toolbar' => ['show' => false],
            ],
            'plotOptions' => [
                'bar' => [
                    'horizontal'   => false,
                    'columnWidth'  => '45%',
                    'borderRadius' => 6,
                ],
            ],
            'series' => [
                ['name' => 'Earning', 'data' => $seriesEarning],
                ['name' => 'Expense', 'data' => $seriesExpense], // negative values render below zero
            ],
            'xaxis' => [
                'type' => 'category',
                'categories' => $labels,    // ['Jan','Feb',...]
                'labels' => ['rotate' => -45],
            ],
            'yaxis' => [
                [
                    'title' => ['text' => 'BDT'],
                    // Optional money formatter:
                    // 'labels' => ['formatter' => fn ($v) => '৳' . number_format($v, 0)],
                ],
            ],
            'dataLabels' => ['enabled' => false],
            'legend' => [
                'show' => true,
                'position' => 'top',
                'horizontalAlign' => 'right',
            ],
            // Nice soft gradient (optional)
            'fill' => [
                'type' => 'gradient',
                'gradient' => [
                    'shadeIntensity' => 0.35,
                    'opacityFrom' => 0.9,
                    'opacityTo' => 0.9,
                ],
            ],
            // If you want fixed colors, uncomment and choose two:
            // 'colors' => ['#fbbf24', '#f97316'],
            'grid' => ['strokeDashArray' => 3],
        ];
    }

}
