<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the Portal > List of Letter endpoints (PAGEID 2330
 * / MENUID 2823).
 *
 * The catalog (`lookup_details`) and history (`lv_sequence_letter`)
 * live on the external FIMS schema (`mysql_secondary`) which is not
 * provisioned in the SQLite test database — see VendorPoStatusTest doc
 * comment for the project-wide rationale. Only 401 auth-guard contracts
 * are asserted here.
 */
class SponsorLetterTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_catalog_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/letter/catalog');
    }

    public function test_history_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/letter/history');
    }

    public function test_download_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/portal/letter/CFL/download');
    }
}
