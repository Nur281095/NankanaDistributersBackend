<?php

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveDateRange(?array $filters = null): array
    {
        $start = Carbon::parse($filters['start_date'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $end = Carbon::parse($filters['end_date'] ?? now()->toDateString())->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{
     *     orders_count: int,
     *     revenue_total: string,
     *     active_orders_count: int,
     *     cod_pending_count: int,
     *     low_stock_count: int,
     *     new_customers_count: int
     * }
     */
    public function stats(?array $filters = null): array
    {
        [$start, $end] = $this->resolveDateRange($filters);

        $ordersQuery = Order::query()->whereBetween('created_at', [$start, $end]);

        $revenueTotal = (clone $ordersQuery)
            ->where('order_status', '!=', OrderStatus::Cancelled)
            ->sum('grand_total');

        return [
            'orders_count' => (clone $ordersQuery)->count(),
            'revenue_total' => number_format((float) $revenueTotal, 2, '.', ''),
            'active_orders_count' => Order::query()
                ->whereIn('order_status', [
                    OrderStatus::Received,
                    OrderStatus::Packed,
                    OrderStatus::OnWay,
                ])
                ->count(),
            'cod_pending_count' => Order::query()
                ->where('payment_status', OrderPaymentStatus::CodPending)
                ->where('order_status', '!=', OrderStatus::Cancelled)
                ->count(),
            'low_stock_count' => Product::query()->lowStock()->count(),
            'new_customers_count' => User::query()
                ->whereBetween('created_at', [$start, $end])
                ->count(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{labels: list<string>, revenue: list<float>, orders: list<int>}
     */
    public function salesTrend(?array $filters = null): array
    {
        [$start, $end] = $this->resolveDateRange($filters);

        $rows = Order::query()
            ->selectRaw('DATE(created_at) as order_date')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN order_status != ? THEN grand_total ELSE 0 END), 0) as revenue_total', [
                OrderStatus::Cancelled->value,
            ])
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get()
            ->keyBy('order_date');

        $labels = [];
        $revenue = [];
        $orders = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);

            $labels[] = $date->format('M j');
            $revenue[] = round((float) ($row->revenue_total ?? 0), 2);
            $orders[] = (int) ($row->orders_count ?? 0);
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'orders' => $orders,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array<string, int>
     */
    public function ordersByStatus(?array $filters = null): array
    {
        [$start, $end] = $this->resolveDateRange($filters);

        $rows = Order::query()
            ->select('order_status', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('order_status')
            ->get();

        $result = [];

        foreach (OrderStatus::cases() as $status) {
            $result[$status->value] = 0;
        }

        foreach ($rows as $row) {
            $status = $row->order_status instanceof OrderStatus
                ? $row->order_status->value
                : (string) $row->order_status;
            $result[$status] = (int) $row->total;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return Collection<int, object{product_name: string, sku_code: string, total_quantity: int, total_revenue: string}>
     */
    public function topProducts(?array $filters = null, int $limit = 5): Collection
    {
        [$start, $end] = $this->resolveDateRange($filters);

        return OrderItem::query()
            ->select([
                'order_items.product_name',
                'order_items.sku_code',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
            ])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where('orders.order_status', '!=', OrderStatus::Cancelled)
            ->groupBy('order_items.product_name', 'order_items.sku_code')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function lowStockProducts(int $limit = 10): Collection
    {
        return Product::query()
            ->lowStock()
            ->orderBy('stock_quantity')
            ->limit($limit)
            ->get();
    }

    public function activeOrdersCount(): int
    {
        return Order::query()
            ->whereIn('order_status', [
                OrderStatus::Received,
                OrderStatus::Packed,
                OrderStatus::OnWay,
            ])
            ->count();
    }

    public function lowStockCount(): int
    {
        return Product::query()->lowStock()->count();
    }

    public function unreadAdminNotificationsCount(): int
    {
        return AppNotification::query()
            ->whereNotNull('admin_id')
            ->where('is_read', false)
            ->count();
    }
}
