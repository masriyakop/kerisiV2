<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the FIMS Purchasing migrations. The endpoints read
 * from the external `mysql_secondary` schema which is not provisioned in
 * the in-memory test database (same constraint as PortalTest), so only the
 * Sanctum auth guard is covered here.
 */
class PurchasingTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_status_po_pr_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/purchasing/status-po-pr');
    }

    public function test_status_po_pr_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/purchasing/status-po-pr/options');
    }
}
