<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the "Integration - PTJ" endpoints (PAGEID 1860 / MENUID 2277).
 *
 * Reads from / writes to the FIMS schema (mysql_secondary) which is not
 * provisioned in the SQLite in-memory test database, so only the auth guard
 * is covered here — same convention as AccountCodePpiTest, FundType, etc.
 */
class IntegrationPtjTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_requires_authentication(): void
    {
        $response = $this->getJson('/api/integration/ptj');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_options_requires_authentication(): void
    {
        $response = $this->getJson('/api/integration/ptj/options');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_parents_requires_authentication(): void
    {
        $response = $this->getJson('/api/integration/ptj/parents');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_promote_requires_authentication(): void
    {
        $response = $this->postJson('/api/integration/ptj/1/promote', []);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
