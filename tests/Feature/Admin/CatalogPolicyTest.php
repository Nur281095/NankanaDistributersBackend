<?php

use App\Enums\AdminStatus;
use App\Enums\CatalogStatus;
use App\Models\Admin;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Product;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Support\Facades\Gate;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        AdminSeeder::class,
        DemoCatalogSeeder::class,
    ]);
});

describe('Admin catalog policies', function (): void {
    it('allows active admins to manage catalog resources', function (): void {
        $admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();
        $company = Company::query()->firstOrFail();
        $brand = Brand::query()->firstOrFail();
        $product = Product::query()->firstOrFail();

        expect(Gate::forUser($admin)->allows('viewAny', Company::class))->toBeTrue();
        expect(Gate::forUser($admin)->allows('update', $company))->toBeTrue();
        expect(Gate::forUser($admin)->allows('update', $brand))->toBeTrue();
        expect(Gate::forUser($admin)->allows('update', $product))->toBeTrue();
    });

    it('denies inactive admins catalog access', function (): void {
        $admin = Admin::query()->where('email', 'admin@nankanadistributors.com')->firstOrFail();
        $admin->update(['status' => AdminStatus::Inactive]);

        expect(Gate::forUser($admin)->allows('viewAny', Product::class))->toBeFalse();
    });
});

describe('Catalog visibility rules', function (): void {
    it('keeps inactive catalog records out of the public api', function (): void {
        $company = Company::query()->where('slug', 'nestle')->firstOrFail();
        $company->update(['status' => CatalogStatus::Inactive]);

        $this->getJson('/api/v1/companies/'.$company->id)->assertNotFound();
    });

    it('still allows admins to load inactive companies in filament query scope', function (): void {
        $company = Company::query()->where('slug', 'nestle')->firstOrFail();
        $company->update(['status' => CatalogStatus::Inactive]);

        expect(Company::query()->whereKey($company->id)->exists())->toBeTrue();
    });
});
