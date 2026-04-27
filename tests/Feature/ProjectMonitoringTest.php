<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Project Monitoring — List of Project (MENUID 1544) and
 * Updated Balance (MENUID 2065).
 *
 * FIMS `capital_project` / `posting_details` live on `mysql_secondary` and
 * are not provisioned in the SQLite in-memory test DB. Only 401
 * auth-guard contracts are asserted here, consistent with
 * {@see AssetInventoryListTest} and the rest of the FIMS read-only
 * API suite.
 */
class ProjectMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/project-monitoring/projects');
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_updated_balance_search_requires_authentication(): void
    {
        $response = $this->getJson('/api/project-monitoring/updated-balance/search');
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_updated_balance_show_requires_authentication(): void
    {
        $response = $this->getJson('/api/project-monitoring/updated-balance/PRJ-001');
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_updated_balance_save_requires_authentication(): void
    {
        $response = $this->postJson('/api/project-monitoring/updated-balance', [
            'info' => ['cpaProjectNo' => 'PRJ-001'],
            'bal' => [],
        ]);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
