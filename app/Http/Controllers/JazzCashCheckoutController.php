<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class JazzCashCheckoutController extends Controller
{
    public function show(string $token): View
    {
        /** @var array<string, string>|null $fields */
        $fields = Cache::pull('jazzcash_payment_form:'.$token);

        if ($fields === null) {
            throw new GoneHttpException('This JazzCash payment session has expired or was already used.');
        }

        if ($fields === []) {
            throw new NotFoundHttpException('JazzCash payment session is invalid.');
        }

        return view('payments.jazzcash-redirect', [
            'actionUrl' => (string) config('payments.jazzcash.form_url'),
            'fields' => $fields,
        ]);
    }
}
