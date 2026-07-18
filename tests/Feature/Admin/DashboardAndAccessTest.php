<?php

use App\Enums\AdminStatus;
use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Widgets\LowStockProductsWidget;
use App\Filament\Widgets\OrdersByStatusChartWidget;
use App\Filament\Widgets\SalesChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TopProductsWidget;
use App\Models\Admin;
use App\Models\AppNotification;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CustomerAddress;
use App\Models\EmailTemplate;
use App\Models\GuestCustomer;
use App\Models\HomeSection;
use App\Models\InventoryLog;
use App\Models\Offer;
use App\Models\OfferTarget;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Policies\AdminResourcePolicy;
use App\Services\DashboardService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\HomeSectionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        AdminSeeder::class,
        SettingsSeeder::class,
        EmailTemplateSeeder::class,
        DemoCatalogSeeder::class,
        HomeSectionsSeeder::class,
    ]);

    $this->admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();
    $this->product = Product::query()->where('sku_code', 'NIDO-400G')->firstOrFail();
    $this->user = User::factory()->create(['phone' => '03001231234']);
});

describe('Admin access policies', function (): void {
    it('allows active admins across all admin resources', function (): void {
        $models = [
            [Company::class, Company::query()->firstOrFail()],
            [Brand::class, Brand::query()->firstOrFail()],
            [Product::class, $this->product],
            [InventoryLog::class, null],
            [Order::class, null],
            [Payment::class, null],
            [User::class, $this->user],
            [GuestCustomer::class, null],
            [Offer::class, null],
            [OfferTarget::class, null],
            [HomeSection::class, HomeSection::query()->firstOrFail()],
            [EmailTemplate::class, EmailTemplate::query()->firstOrFail()],
            [AppNotification::class, null],
            [Setting::class, Setting::query()->firstOrFail()],
        ];

        foreach ($models as [$modelClass, $record]) {
            expect(Gate::forUser($this->admin)->allows('viewAny', $modelClass))->toBeTrue();

            if ($record !== null) {
                expect(Gate::forUser($this->admin)->allows('view', $record))->toBeTrue();
            }
        }
    });

    it('denies inactive admins across admin resources', function (): void {
        $this->admin->update(['status' => AdminStatus::Inactive]);

        expect(Gate::forUser($this->admin)->allows('viewAny', Product::class))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('viewAny', Order::class))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('viewAny', Offer::class))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('viewAny', Setting::class))->toBeFalse();
    });

    it('registers customer address policy for api use', function (): void {
        $address = CustomerAddress::query()->create([
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'phone' => $this->user->phone,
            'address' => 'House 1',
            'city' => 'Lahore',
            'is_default' => true,
        ]);

        expect(Gate::forUser($this->user)->allows('view', $address))->toBeTrue();
        expect(Gate::forUser(User::factory()->create())->allows('view', $address))->toBeFalse();
    });
});

describe('Dashboard widgets authorization', function (): void {
    it('allows active admins to view dashboard widgets', function (): void {
        $this->actingAs($this->admin, 'admin');

        expect(StatsOverviewWidget::canView())->toBeTrue();
        expect(SalesChartWidget::canView())->toBeTrue();
        expect(OrdersByStatusChartWidget::canView())->toBeTrue();
        expect(TopProductsWidget::canView())->toBeTrue();
        expect(LowStockProductsWidget::canView())->toBeTrue();
    });

    it('denies inactive admins dashboard widgets', function (): void {
        $this->admin->update(['status' => AdminStatus::Inactive]);
        $this->actingAs($this->admin, 'admin');

        expect(StatsOverviewWidget::canView())->toBeFalse();
    });
});

describe('DashboardService metrics', function (): void {
    it('calculates dashboard stats for placed orders', function (): void {
        $orderId = placeCodOrder($this->user, $this->product, 2);

        Order::query()->whereKey($orderId)->update([
            'created_at' => now()->subDay(),
        ]);

        $stats = app(DashboardService::class)->stats([
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        expect($stats['orders_count'])->toBe(1);
        expect((float) $stats['revenue_total'])->toBeGreaterThan(0);
        expect($stats['active_orders_count'])->toBe(1);
        expect($stats['cod_pending_count'])->toBe(1);
    });

    it('builds sales trend and status breakdown data', function (): void {
        placeCodOrder($this->user, $this->product);

        Order::query()->create([
            'order_number' => 'ORD-TEST01',
            'user_id' => $this->user->id,
            'is_guest' => false,
            'customer_name' => $this->user->name,
            'customer_phone' => $this->user->phone,
            'delivery_address' => 'Test address',
            'subtotal' => '100.00',
            'delivery_charges' => '0.00',
            'discount_amount' => '0.00',
            'grand_total' => '100.00',
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => OrderPaymentStatus::CodPending,
            'order_status' => OrderStatus::Cancelled,
            'cancellation_deadline' => now()->addMinutes(5),
            'created_at' => now(),
        ]);

        $service = app(DashboardService::class);
        $filters = [
            'start_date' => now()->subDays(7)->toDateString(),
            'end_date' => now()->toDateString(),
        ];

        $trend = $service->salesTrend($filters);
        expect($trend['labels'])->not->toBeEmpty();
        expect(array_sum($trend['orders']))->toBeGreaterThan(0);

        $statuses = $service->ordersByStatus($filters);
        expect($statuses[OrderStatus::Received->value])->toBe(1);
        expect($statuses[OrderStatus::Cancelled->value])->toBe(1);
    });

    it('lists low stock products using the model scope', function (): void {
        $this->product->update([
            'stock_quantity' => 3,
            'low_stock_threshold' => 10,
        ]);

        expect(app(DashboardService::class)->lowStockCount())->toBeGreaterThan(0);
        expect(app(DashboardService::class)->lowStockProducts()->first()?->id)->toBe($this->product->id);
    });

    it('counts unread admin notifications for navigation badges', function (): void {
        AppNotification::query()->create([
            'admin_id' => $this->admin->id,
            'title' => 'New order',
            'message' => 'An order was placed.',
            'type' => NotificationType::Admin,
            'is_read' => false,
        ]);

        expect(app(DashboardService::class)->unreadAdminNotificationsCount())->toBe(1);
    });
});

describe('AdminResourcePolicy base behavior', function (): void {
    it('exposes dashboard access helper on the base policy', function (): void {
        $policy = new class extends AdminResourcePolicy
        {
            public function checkDashboard(Admin $admin): bool
            {
                return $this->viewDashboard($admin);
            }
        };

        expect($policy->checkDashboard($this->admin))->toBeTrue();

        $this->admin->update(['status' => AdminStatus::Inactive]);

        expect($policy->checkDashboard($this->admin->fresh()))->toBeFalse();
    });
});
