<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the FIMS Audit Trail / System Transaction endpoints
 * (PAGEID 3 / MENUID 5).
 *
 * The underlying `fims_audit.system_transaction` table lives on the external
 * FIMS schema (`mysql_secondary`) which is not provisioned in the SQLite
 * `:memory:` test database. Following the convention used by `CashbookTest`
 * and the rest of the FIMS feature suite, we only cover the 401 auth guard
 * here.
 *
 * RBAC (`audit.read`) and 200-path data-shape assertions are validated in
 * staging against a read replica because:
 *   - The User model does not use Sanctum `HasApiTokens`, so
 *     `Sanctum::actingAs(...)` is not usable in this project.
 *   - The legacy `system_transaction` ledger cannot be reproduced in CI.
 */
class AuditSystemTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_index_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/audit/system-transactions');
    }

    public function test_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/audit/system-transactions/options');
    }

    public function test_show_sql_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/audit/system-transactions/123/sql');
    }
}
