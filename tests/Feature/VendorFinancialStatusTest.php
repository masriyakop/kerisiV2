<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the Vendor Portal > Financial Status endpoints
 * (PAGEID 1714 / MENUID 2072).
 *
 * Three vendor-scoped read-only datatables (billings / vouchers /
 * payments). The underlying tables (bills_master, voucher_master,
 * payment_record + details) live on the external FIMS schema
 * (`mysql_secondary`) which is not provisioned in the SQLite test
 * database — see VendorPoStatusTest doc comment for the project-wide
 * rationale. Only 401 auth-guard contracts are asserted here.
 */
class VendorFinancialStatusTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $uri): void
    {
        $response = $this->json('GET', $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_billings_requires_authentication(): void
    {
        $this->assertUnauthorized('/api/portal/vendor/financial-status/billings');
    }

    public function test_vouchers_requires_authentication(): void
    {
        $this->assertUnauthorized('/api/portal/vendor/financial-status/vouchers');
    }

    public function test_payments_requires_authentication(): void
    {
        $this->assertUnauthorized('/api/portal/vendor/financial-status/payments');
    }
}
