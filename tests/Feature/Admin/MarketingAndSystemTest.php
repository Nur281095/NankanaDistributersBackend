<?php

use App\Enums\AdminStatus;
use App\Enums\CatalogStatus;
use App\Enums\DiscountType;
use App\Enums\NotificationType;
use App\Enums\OfferTargetType;
use App\Enums\SettingType;
use App\Exceptions\BusinessException;
use App\Models\Admin;
use App\Models\AppNotification;
use App\Models\Brand;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\Offer;
use App\Models\OfferTarget;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\OfferService;
use App\Services\SettingsService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        AdminSeeder::class,
        SettingsSeeder::class,
        EmailTemplateSeeder::class,
        DemoCatalogSeeder::class,
    ]);

    $this->admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();
});

describe('Marketing and system admin policies', function (): void {
    it('allows active admins to manage offers and email templates', function (): void {
        $offer = Offer::query()->create([
            'title' => 'Summer Sale',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'status' => CatalogStatus::Active,
        ]);
        $template = EmailTemplate::query()->where('slug', 'order_confirmation')->firstOrFail();

        expect(Gate::forUser($this->admin)->allows('viewAny', Offer::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('create', Offer::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('update', $offer))->toBeTrue();

        expect(Gate::forUser($this->admin)->allows('viewAny', EmailTemplate::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('update', $template))->toBeTrue();
    });

    it('allows viewing notifications and updating settings while blocking create', function (): void {
        $notification = AppNotification::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Order update',
            'message' => 'Your order has been packed.',
            'type' => NotificationType::Order,
        ]);
        $setting = Setting::query()->where('key', 'delivery_charges')->firstOrFail();

        expect(Gate::forUser($this->admin)->allows('viewAny', AppNotification::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('view', $notification))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('create', AppNotification::class))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('update', $notification))->toBeFalse();

        expect(Gate::forUser($this->admin)->allows('viewAny', Setting::class))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('update', $setting))->toBeTrue();
        expect(Gate::forUser($this->admin)->allows('create', Setting::class))->toBeFalse();
    });

    it('denies inactive admins marketing and system access', function (): void {
        $this->admin->update(['status' => AdminStatus::Inactive]);
        $offer = Offer::query()->create([
            'title' => 'Blocked Offer',
            'discount_type' => DiscountType::Fixed,
            'discount_value' => 50,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'status' => CatalogStatus::Active,
        ]);

        expect(Gate::forUser($this->admin)->allows('viewAny', Offer::class))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('update', $offer))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('viewAny', Setting::class))->toBeFalse();
    });
});

describe('OfferService', function (): void {
    it('validates offer discount and date rules', function (): void {
        $service = app(OfferService::class);

        expect(fn () => $service->validate([
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 150,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ]))->toThrow(BusinessException::class, '100%');

        expect(fn () => $service->validate([
            'discount_type' => DiscountType::Fixed,
            'discount_value' => 25,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->toDateString(),
        ]))->toThrow(BusinessException::class, 'end date');
    });

    it('detects currently active offers', function (): void {
        $offer = Offer::query()->create([
            'title' => 'Live Offer',
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 15,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => CatalogStatus::Active,
        ]);

        expect(app(OfferService::class)->isCurrentlyActive($offer))->toBeTrue();

        $offer->update(['status' => CatalogStatus::Inactive]);

        expect(app(OfferService::class)->isCurrentlyActive($offer->fresh()))->toBeFalse();
    });

    it('stores offer targets for companies brands and products', function (): void {
        $company = Company::query()->firstOrFail();
        $brand = Brand::query()->firstOrFail();
        $product = Product::query()->firstOrFail();

        $offer = Offer::query()->create([
            'title' => 'Targeted Offer',
            'discount_type' => DiscountType::Fixed,
            'discount_value' => 100,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => CatalogStatus::Active,
        ]);

        OfferTarget::query()->create([
            'offer_id' => $offer->id,
            'target_type' => OfferTargetType::Company,
            'target_id' => $company->id,
        ]);
        OfferTarget::query()->create([
            'offer_id' => $offer->id,
            'target_type' => OfferTargetType::Brand,
            'target_id' => $brand->id,
        ]);
        OfferTarget::query()->create([
            'offer_id' => $offer->id,
            'target_type' => OfferTargetType::Product,
            'target_id' => $product->id,
        ]);

        expect($offer->targets()->count())->toBe(3);
    });
});

describe('SettingsService admin updates', function (): void {
    it('updates a setting value and clears the public settings cache', function (): void {
        app(SettingsService::class)->publicSettings();
        expect(Cache::has('app.public_settings'))->toBeTrue();

        $setting = Setting::query()->where('key', 'delivery_charges')->firstOrFail();
        expect($setting->type)->toBe(SettingType::Integer);

        app(SettingsService::class)->updateValue($setting, 199);

        expect(Setting::getValue('delivery_charges'))->toBe(199);
        expect(Cache::has('app.public_settings'))->toBeFalse();
    });

    it('serializes boolean settings for storage', function (): void {
        $setting = Setting::query()->where('key', 'cod_enabled')->firstOrFail();

        app(SettingsService::class)->updateValue($setting, false);

        expect(Setting::getValue('cod_enabled'))->toBeFalse();
        expect($setting->fresh()->value)->toBe('0');
    });

    it('keeps auto confirm cod internal to the admin panel', function (): void {
        $setting = Setting::query()->where('key', 'auto_confirm_cod')->firstOrFail();

        expect($setting->type)->toBe(SettingType::Boolean);
        expect(in_array('auto_confirm_cod', SettingsService::PUBLIC_KEYS, true))->toBeFalse();
    });
});

describe('Email templates seed', function (): void {
    it('seeds the expected transactional templates', function (): void {
        expect(EmailTemplate::query()->count())->toBe(8);
        expect(EmailTemplate::query()->where('slug', 'order_confirmation')->exists())->toBeTrue();
        expect(EmailTemplate::query()->where('slug', 'low_stock_alert')->exists())->toBeTrue();
    });
});
