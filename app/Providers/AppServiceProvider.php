<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Models\Brand;
use App\Models\CartItem;
use App\Models\Company;
use App\Models\CustomerAddress;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\GuestCustomer;
use App\Models\InventoryLog;
use App\Models\Offer;
use App\Models\OfferTarget;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(CartItem::class, \App\Policies\CartItemPolicy::class);
        Gate::policy(CustomerAddress::class, \App\Policies\CustomerAddressPolicy::class);
        Gate::policy(Order::class, \App\Policies\OrderPolicy::class);
        Gate::policy(Company::class, \App\Policies\CompanyPolicy::class);
        Gate::policy(Brand::class, \App\Policies\BrandPolicy::class);
        Gate::policy(Product::class, \App\Policies\ProductPolicy::class);
        Gate::policy(InventoryLog::class, \App\Policies\InventoryLogPolicy::class);
        Gate::policy(User::class, \App\Policies\UserPolicy::class);
        Gate::policy(GuestCustomer::class, \App\Policies\GuestCustomerPolicy::class);
        Gate::policy(Payment::class, \App\Policies\PaymentPolicy::class);
        Gate::policy(Offer::class, \App\Policies\OfferPolicy::class);
        Gate::policy(OfferTarget::class, \App\Policies\OfferTargetPolicy::class);
        Gate::policy(EmailTemplate::class, \App\Policies\EmailTemplatePolicy::class);
        Gate::policy(EmailLog::class, \App\Policies\EmailLogPolicy::class);
        Gate::policy(AppNotification::class, \App\Policies\AppNotificationPolicy::class);
        Gate::policy(Setting::class, \App\Policies\SettingPolicy::class);

        Route::bind('company', function (string $value): Company {
            return Company::query()
                ->active()
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('brand', function (string $value): Brand {
            return Brand::query()
                ->active()
                ->whereHas('company', fn ($query) => $query->active())
                ->whereKey($value)
                ->firstOrFail();
        });

        Route::bind('product', function (string $value): Product {
            return Product::query()
                ->active()
                ->whereHas('brand', fn ($query) => $query->active())
                ->whereHas('company', fn ($query) => $query->active())
                ->whereKey($value)
                ->firstOrFail();
        });
    }
}
