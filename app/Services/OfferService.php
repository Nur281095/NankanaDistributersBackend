<?php

namespace App\Services;

use App\Enums\CatalogStatus;
use App\Enums\DiscountType;
use App\Exceptions\BusinessException;
use App\Models\Offer;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class OfferService
{
    /**
     * @param  array{
     *     discount_type?: DiscountType|string,
     *     discount_value?: float|int|string,
     *     start_date?: string|Carbon,
     *     end_date?: string|Carbon
     * }  $data
     */
    public function validate(array $data): void
    {
        $discountType = $data['discount_type'] instanceof DiscountType
            ? $data['discount_type']
            : DiscountType::from((string) $data['discount_type']);

        $discountValue = (float) ($data['discount_value'] ?? 0);

        if ($discountValue <= 0) {
            throw new BusinessException(
                'Discount value must be greater than zero.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($discountType === DiscountType::Percentage && $discountValue > 100) {
            throw new BusinessException(
                'Percentage discount cannot exceed 100%.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $startDate = Carbon::parse($data['start_date'] ?? now());
        $endDate = Carbon::parse($data['end_date'] ?? now());

        if ($endDate->lt($startDate)) {
            throw new BusinessException(
                'Offer end date must be on or after the start date.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }

    public function isCurrentlyActive(Offer $offer, ?Carbon $onDate = null): bool
    {
        if ($offer->status !== CatalogStatus::Active) {
            return false;
        }

        $date = ($onDate ?? now())->startOfDay();

        return $date->betweenIncluded(
            $offer->start_date->startOfDay(),
            $offer->end_date->startOfDay(),
        );
    }
}
