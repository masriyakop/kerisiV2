<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the FIMS Credit Control endpoints (MENUIDs 1809, 3066,
 * 3388, 3397). Like the other FIMS features, these endpoints read from the
 * external `mysql_secondary` schema which is not provisioned in the test
 * SQLite in-memory database, so only the auth guard is covered here — this
 * mirrors CashbookTest / AccountReceivableTest.
 */
class CreditControlTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    // Deposit (MENUID 1809)
    public function test_deposit_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/deposit');
    }

    public function test_deposit_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/deposit/options');
    }

    public function test_deposit_autosuggest_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/deposit/autosuggest');
    }

    // List of Deposit (MENUID 3066)
    public function test_list_of_deposit_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/list-of-deposit');
    }

    public function test_list_of_deposit_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/list-of-deposit/options');
    }

    public function test_list_of_deposit_search_customer_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/list-of-deposit/search-customer');
    }

    // Invoice Balance (MENUID 3388)
    public function test_invoice_balance_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/invoice-balance');
    }

    public function test_invoice_balance_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/invoice-balance/options');
    }

    public function test_invoice_balance_search_customer_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/invoice-balance/search-customer');
    }

    public function test_invoice_balance_search_invoice_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/invoice-balance/search-invoice');
    }

    // Detail of Deposit (MENUID 3397)
    public function test_deposit_form_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/deposit-form/1');
    }

    public function test_deposit_form_details_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/deposit-form/1/details');
    }

    public function test_deposit_form_search_customer_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/credit-control/deposit-form/search-customer');
    }

    public function test_deposit_form_update_requires_authentication(): void
    {
        $this->assertUnauthorized('PUT', '/api/credit-control/deposit-form/1');
    }

    public function test_deposit_form_update_detail_requires_authentication(): void
    {
        $this->assertUnauthorized('PUT', '/api/credit-control/deposit-form/1/detail/2');
    }
}
