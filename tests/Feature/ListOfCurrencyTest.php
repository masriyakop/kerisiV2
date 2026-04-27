<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the "List of Currency" endpoints
 * (PAGEID 2636 / MENUID 3198).
 *
 * Reads from / writes to the FIMS schema (mysql_secondary) which is not
 * provisioned in the SQLite in-memory test database, so only the auth guard
 * is covered.
 */
class ListOfCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_requires_authentication(): void
    {
        $response = $this->getJson('/api/global/currencies');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_country_search_requires_authentication(): void
    {
        $response = $this->getJson('/api/global/currencies/countries');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_show_requires_authentication(): void
    {
        $response = $this->getJson('/api/global/currencies/1');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/global/currencies', []);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_update_requires_authentication(): void
    {
        $response = $this->putJson('/api/global/currencies/1', []);

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_delete_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/global/currencies/1');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
