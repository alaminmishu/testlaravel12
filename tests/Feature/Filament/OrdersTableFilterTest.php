<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersTableFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_filters_orders_by_pos_sync_status(): void
    {
        $synced = Order::factory()->create(['is_pos_synced' => true]);
        $notSynced = Order::factory()->create(['is_pos_synced' => false]);

        Livewire::test(ListOrders::class)
            ->filterTable('is_pos_synced', false)
            ->assertCanSeeTableRecords([$notSynced])
            ->assertCanNotSeeTableRecords([$synced]);
    }

    public function test_it_filters_orders_by_phone_order_flag(): void
    {
        $phoneOrder = Order::factory()->create(['is_phone_order' => true]);
        $regularOrder = Order::factory()->create(['is_phone_order' => false]);

        Livewire::test(ListOrders::class)
            ->filterTable('is_phone_order', true)
            ->assertCanSeeTableRecords([$phoneOrder])
            ->assertCanNotSeeTableRecords([$regularOrder]);
    }
}
