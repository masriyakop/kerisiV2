<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the "Integration - Activity" endpoints
 * (PAGEID 2003 / MENUID 2444).
 *
 * Reads from the FIMS schema (mysql_secondary) which is not provisioned in
 * the SQLite in-memory test database, so only the auth guard is covered.
 */
class IntegrationActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_requires_authentication(): void
    {
        $response = $this->getJson('/api/integration/activity');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_show_requires_authentication(): void
    {
        $response = $this->getJson('/api/integration/activity/1');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
