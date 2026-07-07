<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AddressService
{
    /**
     * @return Collection<int, CustomerAddress>
     */
    public function listForUser(User $user): Collection
    {
        return $user->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();
    }

    public function findForUser(User $user, int $addressId): CustomerAddress
    {
        $address = $user->addresses()->whereKey($addressId)->first();

        if ($address === null) {
            throw new BusinessException(
                'Address not found.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $address;
    }

    /**
     * @param  array{
     *     label?: string|null,
     *     name: string,
     *     phone: string,
     *     address: string,
     *     city?: string|null,
     *     area?: string|null,
     *     is_default?: bool
     * }  $data
     */
    public function create(User $user, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $data): CustomerAddress {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault || $user->addresses()->count() === 0) {
                $this->clearDefaultFlag($user);
                $isDefault = true;
            }

            return $user->addresses()->create([
                'label' => $data['label'] ?? null,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'city' => $data['city'] ?? null,
                'area' => $data['area'] ?? null,
                'is_default' => $isDefault,
            ]);
        });
    }

    /**
     * @param  array{
     *     label?: string|null,
     *     name?: string,
     *     phone?: string,
     *     address?: string,
     *     city?: string|null,
     *     area?: string|null,
     *     is_default?: bool
     * }  $data
     */
    public function update(User $user, CustomerAddress $address, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $address, $data): CustomerAddress {
            if (($data['is_default'] ?? false) === true) {
                $this->clearDefaultFlag($user, $address->id);
            }

            $address->fill([
                'label' => $data['label'] ?? $address->label,
                'name' => $data['name'] ?? $address->name,
                'phone' => $data['phone'] ?? $address->phone,
                'address' => $data['address'] ?? $address->address,
                'city' => array_key_exists('city', $data) ? $data['city'] : $address->city,
                'area' => array_key_exists('area', $data) ? $data['area'] : $address->area,
                'is_default' => $data['is_default'] ?? $address->is_default,
            ]);

            $address->save();

            return $address->fresh();
        });
    }

    public function delete(User $user, CustomerAddress $address): void
    {
        DB::transaction(function () use ($user, $address): void {
            $wasDefault = $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $next = $user->addresses()->orderByDesc('updated_at')->first();

                if ($next !== null) {
                    $next->update(['is_default' => true]);
                }
            }
        });
    }

    private function clearDefaultFlag(User $user, ?int $exceptId = null): void
    {
        $query = $user->addresses()->where('is_default', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }
}
