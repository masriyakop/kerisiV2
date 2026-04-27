<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the "Integration - Cost Centre" endpoints
 * (PAGEID 1861 / MENUID 2278).
 *
 * Reads from / writes to the FIMS schema (mysql_secondary) which is not
 * provisioned in the SQLite in-memory test database, so only the auth guard
 * is covered here.
 */
class IntegrationCostCentreTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_requires_authentication(): void
    {
        $response = $this->getJson('/api/integration/cost-centre');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_promote_requires_authentication(): void
    {
        $response = $this->postJson('/api/integration/cost-centre/1/promote', []);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
