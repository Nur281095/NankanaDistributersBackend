<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Catalog\CatalogListRequest;
use App\Http\Requests\Api\Catalog\ProductSearchRequest;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService,
    ) {}

    public function show(Product $product): JsonResponse
    {
        $product = $this->catalogService->findProduct($product->id);

        return $this->success(
            ProductDetailResource::make($product)->resolve(),
        );
    }

    public function search(ProductSearchRequest $request): JsonResponse
    {
        $products = $this->catalogService->searchProducts(
            $request->searchTerm(),
            $request->page(),
            $request->perPage(),
        );

        return $this->paginated($products, ProductResource::class);
    }

    public function suggested(CatalogListRequest $request): JsonResponse
    {
        $products = $this->catalogService->paginateSuggestedProducts(
            $request->page(),
            $request->perPage(),
        );

        return $this->paginated($products, ProductResource::class);
    }
}
