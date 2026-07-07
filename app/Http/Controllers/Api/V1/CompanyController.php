<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Api\Catalog\CatalogListRequest;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService,
    ) {}

    public function index(CatalogListRequest $request): JsonResponse
    {
        $companies = $this->catalogService->paginateCompanies(
            $request->page(),
            $request->perPage(),
        );

        return $this->paginated($companies, CompanyResource::class);
    }

    public function show(Company $company): JsonResponse
    {
        $company = $this->catalogService->findCompany($company->id);

        return $this->success(
            CompanyResource::make($company)->resolve(),
        );
    }

    public function brands(Company $company, CatalogListRequest $request): JsonResponse
    {
        $company = $this->catalogService->findCompany($company->id);

        $brands = $this->catalogService->paginateBrandsForCompany(
            $company,
            $request->page(),
            $request->perPage(),
        );

        return $this->paginated($brands, BrandResource::class);
    }
}
