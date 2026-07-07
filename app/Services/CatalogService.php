<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class CatalogService
{
    public function paginateCompanies(int $page, int $perPage): LengthAwarePaginator
    {
        return Company::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function findCompany(int $companyId): Company
    {
        $company = Company::query()
            ->active()
            ->withCount([
                'brands as brands_count' => fn ($query) => $query->active(),
            ])
            ->whereKey($companyId)
            ->first();

        if ($company === null) {
            throw new BusinessException('Company not found.', Response::HTTP_NOT_FOUND);
        }

        return $company;
    }

    public function paginateBrandsForCompany(Company $company, int $page, int $perPage): LengthAwarePaginator
    {
        return Brand::query()
            ->active()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function findBrand(int $brandId): Brand
    {
        $brand = Brand::query()
            ->active()
            ->whereHas('company', fn ($query) => $query->active())
            ->with(['company' => fn ($query) => $query->active()])
            ->withCount([
                'products as products_count' => fn ($query) => $query->active(),
            ])
            ->whereKey($brandId)
            ->first();

        if ($brand === null) {
            throw new BusinessException('Brand not found.', Response::HTTP_NOT_FOUND);
        }

        return $brand;
    }

    public function paginateProductsForBrand(Brand $brand, int $page, int $perPage): LengthAwarePaginator
    {
        return Product::query()
            ->active()
            ->where('brand_id', $brand->id)
            ->whereHas('brand', fn ($query) => $query->active())
            ->whereHas('company', fn ($query) => $query->active())
            ->orderBy('name')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function findProduct(int $productId): Product
    {
        $product = Product::query()
            ->active()
            ->whereHas('brand', fn ($query) => $query->active())
            ->whereHas('company', fn ($query) => $query->active())
            ->with([
                'brand' => fn ($query) => $query->active(),
                'company' => fn ($query) => $query->active(),
                'images' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->whereKey($productId)
            ->first();

        if ($product === null) {
            throw new BusinessException('Product not found.', Response::HTTP_NOT_FOUND);
        }

        return $product;
    }

    public function searchProducts(string $term, int $page, int $perPage): LengthAwarePaginator
    {
        $likeTerm = '%'.addcslashes($term, '%_').'%';

        return Product::query()
            ->active()
            ->whereHas('brand', fn ($query) => $query->active())
            ->whereHas('company', fn ($query) => $query->active())
            ->where(function ($query) use ($likeTerm): void {
                $query->where('name', 'like', $likeTerm)
                    ->orWhere('sku_code', 'like', $likeTerm);
            })
            ->with(['brand' => fn ($query) => $query->active()])
            ->orderBy('name')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function paginateSuggestedProducts(int $page, int $perPage): LengthAwarePaginator
    {
        return Product::query()
            ->active()
            ->where('is_suggested', true)
            ->whereHas('brand', fn ($query) => $query->active())
            ->whereHas('company', fn ($query) => $query->active())
            ->with(['brand' => fn ($query) => $query->active()])
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function findPurchasableProduct(int $productId, bool $lockForUpdate = false): Product
    {
        $query = Product::query()
            ->when($lockForUpdate, fn ($builder) => $builder->lockForUpdate())
            ->active()
            ->whereHas('brand', fn ($builder) => $builder->active())
            ->whereHas('company', fn ($builder) => $builder->active())
            ->whereKey($productId);

        $product = $query->first();

        if ($product === null) {
            throw new BusinessException(
                'Product is not available.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $product;
    }

    public function salePrice(Product $product): string
    {
        return (string) ($product->sale_price ?? $product->regular_price);
    }

    public function assertStockAvailable(Product $product, int $quantity): void
    {
        if ($product->stock_quantity < $quantity) {
            throw new BusinessException(
                "Only {$product->stock_quantity} units of {$product->name} are available.",
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }
}
