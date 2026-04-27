<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the "AG Rate" endpoints (PAGEID 2647 / MENUID 3199).
 *
 * Reads from / writes to the FIMS schema (mysql_secondary) which is not
 * provisioned in the SQLite in-memory test database, so only the auth guard
 * is covered.
 */
class AgRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_requires_authentication(): void
    {
        $response = $this->getJson('/api/global/ag-rate');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_options_requires_authentication(): void
    {
        $response = $this->getJson('/api/global/ag-rate/options');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_currencies_requires_authentication(): void
    {
        $response = $this->getJson('/api/global/ag-rate/currencies');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_lines_requires_authentication(): void
    {
        $response = $this->getJson('/api/global/ag-rate/lines');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_check_exist_requires_authentication(): void
    {
        $response = $this->getJson('/api/global/ag-rate/check-exist');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/global/ag-rate', []);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_delete_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/global/ag-rate');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
