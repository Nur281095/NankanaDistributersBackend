<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Services\HomeService;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function __construct(
        private readonly HomeService $homeService,
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->success([
            'sections' => $this->homeService->buildHomeFeed(),
        ]);
    }
}
