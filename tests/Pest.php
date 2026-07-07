<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function authApiHeaders(App\Models\User $user): array
{
    $token = $user->createToken('test')->plainTextToken;

    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

function placeCodOrder(App\Models\User $user, App\Models\Product $product, int $quantity = 1): int
{
    test()->postJson('/api/v1/cart/items', [
        'product_id' => $product->id,
        'quantity' => $quantity,
    ], authApiHeaders($user))->assertCreated();

    $response = test()->postJson('/api/v1/checkout/place-order', [
        'payment_method' => App\Enums\PaymentMethod::Cod->value,
        'name' => $user->name,
        'phone' => $user->phone,
        'address' => 'House 12, Model Town',
        'city' => 'Lahore',
    ], authApiHeaders($user));

    $response->assertCreated();

    return (int) $response->json('data.id');
}

function configurePaymentGateways(): void
{
    config([
        'payments.jazzcash.merchant_id' => 'MC12345',
        'payments.jazzcash.password' => 'test-password',
        'payments.jazzcash.integrity_salt' => 'test-salt-key12',
        'payments.jazzcash.return_url' => 'https://api.nankanadistributors.com/api/v1/payments/callback/jazzcash',
        'payments.easypaisa.store_id' => '12345',
        'payments.easypaisa.hash_key' => '0123456789abcdef',
        'payments.easypaisa.postback_url' => 'https://api.nankanadistributors.com/api/v1/payments/callback/easypaisa',
    ]);
}

function placeOnlineOrder(App\Models\User $user, App\Models\Product $product, App\Enums\PaymentMethod $paymentMethod, int $quantity = 1): int
{
    test()->postJson('/api/v1/cart/items', [
        'product_id' => $product->id,
        'quantity' => $quantity,
    ], authApiHeaders($user))->assertCreated();

    $response = test()->postJson('/api/v1/checkout/place-order', [
        'payment_method' => $paymentMethod->value,
        'name' => $user->name,
        'phone' => $user->phone,
        'address' => 'House 12, Model Town',
        'city' => 'Lahore',
    ], authApiHeaders($user));

    $response->assertCreated();

    return (int) $response->json('data.id');
}

function jazzCashCallbackPayload(array $overrides = [], string $integritySalt = 'test-salt-key12'): array
{
    $fields = array_merge([
        'pp_Amount' => '94900',
        'pp_AuthCode' => 'AUTH123',
        'pp_BankID' => '',
        'pp_BillReference' => 'ORD-1001',
        'pp_Language' => 'EN',
        'pp_MerchantID' => 'MC12345',
        'pp_ResponseCode' => '000',
        'pp_ResponseMessage' => 'Success',
        'pp_RetreivalReferenceNo' => 'RRN123456',
        'pp_TxnCurrency' => 'PKR',
        'pp_TxnDateTime' => '20260101120000',
        'pp_TxnRefNo' => 'JCREF123',
        'pp_TxnType' => 'MWALLET',
        'pp_Version' => '1.1',
    ], $overrides);

    $fields['pp_SecureHash'] = App\Support\Payments\JazzCashSignature::generate($fields, $integritySalt);

    return $fields;
}
