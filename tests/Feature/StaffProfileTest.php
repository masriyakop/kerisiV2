<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the Portal > Staff Profile endpoints (PAGEID 1581
 * / MENUID 1914).
 *
 * The legacy BL `API_PORTAL_SALARYPROFILEINFORMATION` reads / writes a
 * dozen tables on the external FIMS schema (`mysql_secondary`):
 * `staff`, `staff_service`, `staff_account`, `staff_salary`,
 * `staff_allowance_deduction`, `service_scheme`, `staff_address`,
 * `staff_spouse`, `staff_children`, `lookup_details`,
 * `monthly_payroll_detl_his`, `income_type`, `organization_unit`.
 *
 * None of those are provisioned in the SQLite test database (same
 * rationale as VendorPoStatusTest / SponsorLetterTest). We therefore
 * only assert the `auth:sanctum` contract here — every endpoint must
 * return 401 + envelope `error.code = UNAUTHORIZED` to unauthenticated
 * callers. Behavioural coverage (success + validation + RBAC) is
 * deferred until the legacy schema is fixturised in CI.
 */
class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri, array $body = []): void
    {
        $response = $this->json($method, $uri, $body);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_master_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/staff-profile');
    }

    public function test_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/staff-profile/options');
    }

    public function test_address_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/staff-profile/address');
    }

    public function test_address_update_requires_authentication(): void
    {
        $this->assertUnauthorized('PUT', '/api/portal/staff-profile/address', [
            'saAddress1' => '1, Jalan Contoh',
        ]);
    }

    public function test_marital_update_requires_authentication(): void
    {
        $this->assertUnauthorized('PUT', '/api/portal/staff-profile/marital-status', [
            'maritalStatus' => '1',
        ]);
    }

    public function test_children_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/staff-profile/children');
    }

    public function test_spouses_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/staff-profile/spouses');
    }

    public function test_spouse_children_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/staff-profile/spouses/1/children');
    }
}
