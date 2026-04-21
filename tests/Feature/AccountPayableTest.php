<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the FIMS Account Payable endpoints:
 *   - Payee Registration       (MENUID 1711)  read-only
 *   - Utility Registration     (MENUID 3466)  list + modal CRUD
 *   - Account Bank by Payee    (MENUID 2751)  read-only, payee-type driven
 *
 * These endpoints read from the external FIMS schema (mysql_secondary) which
 * is not provisioned in the test SQLite in-memory database, so only the auth
 * guard is covered here — mirroring CashbookTest and the rest of the FIMS
 * feature tests.
 */
class AccountPayableTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_payee_registration_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-payable/payee-registration');
    }

    public function test_payee_registration_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-payable/payee-registration/options');
    }

    public function test_utility_registration_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-payable/utility-registration');
    }

    public function test_utility_registration_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-payable/utility-registration/1');
    }

    public function test_utility_registration_store_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-payable/utility-registration');
    }

    public function test_utility_registration_update_requires_authentication(): void
    {
        $this->assertUnauthorized('PUT', '/api/account-payable/utility-registration/1');
    }

    public function test_account_bank_by_payee_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-payable/account-bank-by-payee');
    }

    public function test_account_bank_by_payee_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-payable/account-bank-by-payee/options');
    }
}
