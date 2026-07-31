<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AccountReceivablePaymentRouteTest extends TestCase
{
    public function test_payment_routes_are_registered_for_accounts_receivable(): void
    {
        $routes = Route::getRoutes();

        $paymentRoute = $routes->getByName('accounts-receivable.apply-payment');
        $legacyPaymentRoute = collect($routes->getRoutes())
            ->first(fn ($route) => $route->uri() === 'accounts-receivable/{accountReceivable}/payment');

        $this->assertNotNull($paymentRoute);
        $this->assertNotNull($legacyPaymentRoute);
    }
}
