<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the FIMS Student Finance migrations. The endpoints
 * read from the external `mysql_secondary` schema which is not provisioned
 * in the in-memory test database (same constraint as PortalTest /
 * PurchasingTest), so only the Sanctum auth guard is covered here.
 */
class StudentFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_ptptn_data_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/student-finance/ptptn-data');
    }

    public function test_ptptn_data_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/student-finance/ptptn-data/1');
    }

    public function test_ptptn_data_destroy_requires_authentication(): void
    {
        $this->assertUnauthorized('DELETE', '/api/student-finance/ptptn-data/1');
    }
}
