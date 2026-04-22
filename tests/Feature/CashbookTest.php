<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the FIMS Cashbook endpoints:
 *   - Bank Setup  (lookup_bank_main, MENUID 3246)
 *   - Bank Master (bank_master,     MENUID 2036)
 *   - Bank Account (bank_detl,      MENUID 2097)
 *   - List Of Cashbook Daily / Monthly (cashbook_details_recon, MENUID 1702 / 2471)
 *
 * These endpoints read from the external FIMS schema (mysql_secondary) which
 * is not provisioned in the test SQLite in-memory database, so only the auth
 * guard is covered here — mirroring AccountCodePpiTest and the rest of the
 * FIMS feature tests.
 */
class CashbookTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_bank_setup_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/cashbook/bank-setup');
    }

    public function test_bank_setup_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/cashbook/bank-setup/options');
    }

    public function test_bank_setup_store_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/cashbook/bank-setup');
    }

    public function test_bank_master_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/cashbook/bank-master');
    }

    public function test_bank_master_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/cashbook/bank-master/options');
    }

    public function test_bank_master_store_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/cashbook/bank-master');
    }

    public function test_bank_account_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/cashbook/bank-account');
    }

    public function test_bank_account_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/cashbook/bank-account/options');
    }

    public function test_bank_account_store_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/cashbook/bank-account');
    }

    public function test_cashbook_daily_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/cashbook/list/daily');
    }

    public function test_cashbook_daily_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/cashbook/list/daily/options');
    }

    public function test_cashbook_monthly_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/cashbook/list/monthly');
    }

    public function test_cashbook_monthly_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/cashbook/list/monthly/options');
    }
}
