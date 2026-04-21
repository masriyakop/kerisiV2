<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the "List of Account Code (PPI)" endpoints.
 *
 * The endpoints read from the external FIMS schema (mysql_secondary) which is
 * not provisioned in the test SQLite in-memory database, so only the auth
 * guard is covered here — mirroring the absence of integration tests for the
 * sibling FIMS endpoints (FundType, CostCentre, ActivityCode, etc.).
 */
class AccountCodePpiTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_requires_authentication(): void
    {
        $response = $this->getJson('/api/setup/account-code-ppi');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_options_requires_authentication(): void
    {
        $response = $this->getJson('/api/setup/account-code-ppi/options');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
