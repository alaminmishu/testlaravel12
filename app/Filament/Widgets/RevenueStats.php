<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $orders = Order::query()->fromMongo();

        $totalRevenue = (float) $orders->clone()->sum('total_amount');
        $orderCount = $orders->clone()->count();
        $averageOrderValue = $orderCount > 0 ? $totalRevenue / $orderCount : 0.0;
        $customerCount = $orders->clone()->distinct()->count('customer_email');

        return [
            Stat::make('Total Revenue', '৳'.number_format($totalRevenue, 0)),
            Stat::make('Total Orders', number_format($orderCount)),
            Stat::make('Average Order Value', '৳'.number_format($averageOrderValue, 0)),
            Stat::make('Unique Customers', number_format($customerCount)),
        ];
    }
}
