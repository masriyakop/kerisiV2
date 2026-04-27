<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the "Budget Not Exists" endpoint
 * (PAGEID 2200 / MENUID 2657).
 *
 * Reads from the FIMS schema (mysql_secondary) which is not provisioned in
 * the SQLite in-memory test database, so only the auth guard is covered.
 */
class BudgetNotExistsTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_requires_authentication(): void
    {
        $response = $this->getJson('/api/general-ledger/budget-not-exists');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
