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
use App\Models\HomeBanner;
use App\Models\HomeSection;
use App\Models\HomeSliderSlide;
use App\Models\InventoryLog;
use App\Models\Offer;
use App\Models\OfferTarget;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Policies\AppNotificationPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CartItemPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CustomerAddressPolicy;
use App\Policies\EmailLogPolicy;
use App\Policies\EmailTemplatePolicy;
use App\Policies\GuestCustomerPolicy;
use App\Policies\HomeBannerPolicy;
use App\Policies\HomeSectionPolicy;
use App\Policies\HomeSliderSlidePolicy;
use App\Policies\InventoryLogPolicy;
use App\Policies\OfferPolicy;
use App\Policies\OfferTargetPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SettingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();

        Gate::policy(CartItem::class, CartItemPolicy::class);
        Gate::policy(CustomerAddress::class, CustomerAddressPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(InventoryLog::class, InventoryLogPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(GuestCustomer::class, GuestCustomerPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Offer::class, OfferPolicy::class);
        Gate::policy(OfferTarget::class, OfferTargetPolicy::class);
        Gate::policy(EmailTemplate::class, EmailTemplatePolicy::class);
        Gate::policy(EmailLog::class, EmailLogPolicy::class);
        Gate::policy(AppNotification::class, AppNotificationPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(HomeSection::class, HomeSectionPolicy::class);
        Gate::policy(HomeSliderSlide::class, HomeSliderSlidePolicy::class);
        Gate::policy(HomeBanner::class, HomeBannerPolicy::class);

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

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            if ($this->app->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        RateLimiter::for('payments-callback', function (Request $request) {
            if ($this->app->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
