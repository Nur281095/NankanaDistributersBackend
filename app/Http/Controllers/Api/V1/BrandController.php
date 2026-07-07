<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Catalog\CatalogListRequest;
use App\Http\Resources\BrandResource;
use App\Http\Resources\ProductResource;
use App\Models\Brand;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService,
    ) {}

    public function show(Brand $brand): JsonResponse
    {
        $brand = $this->catalogService->findBrand($brand->id);

        return $this->success(
            BrandResource::make($brand)->resolve(),
        );
    }

    public function products(Brand $brand, CatalogListRequest $request): JsonResponse
    {
        $brand = $this->catalogService->findBrand($brand->id);

        $products = $this->catalogService->paginateProductsForBrand(
            $brand,
            $request->page(),
            $request->perPage(),
        );

        return $this->paginated($products, ProductResource::class);
    }
}
