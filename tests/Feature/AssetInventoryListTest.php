<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the Asset > List of Asset endpoint (PAGEID 1271 /
 * MENUID 1548).
 *
 * The `asset_inventory_main` table and its companion lookups
 * (organization_unit, cost_centre, fund_type, activity, account_main)
 * live on the external FIMS schema (`mysql_secondary`) which is not
 * provisioned in the SQLite test database — see VendorPoStatusTest doc
 * comment for the project-wide rationale. Only 401 auth-guard contracts
 * are asserted here.
 */
class AssetInventoryListTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->json('GET', '/api/asset/list-of-asset');
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
