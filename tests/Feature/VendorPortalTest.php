<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the Vendor Portal > Vendor Portal endpoints
 * (PAGEID 1622 / MENUID 1961).
 *
 * The legacy BL `NF_BL_PURCHASING_PORTAL_VENDOR` reads from the
 * external FIMS `mysql_secondary` schema (`vend_customer_supplier`,
 * `vend_category`, `vend_supplier_account`, `vendor_address`,
 * `vendor_jobscope`, `vend_licence_ssm`, `vend_licence_mof`,
 * `vend_licence_others`, `bank_master`, `jobscope`, plus
 * `fims_usr.lookup_details`). None of those are provisioned in the
 * SQLite `:memory:` test database (same rationale as
 * `VendorPoStatusTest`, `StaffProfileTest`, `SponsorLetterTest`).
 *
 * We therefore only assert the `auth:sanctum` contract here — every
 * endpoint must return 401 + envelope `error.code = UNAUTHORIZED` to
 * unauthenticated callers. Behavioural coverage (resolution
 * diagnostics, success rows, search) is deferred until the legacy
 * schema is fixturised in CI.
 */
class VendorPortalTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri, array $body = []): void
    {
        $response = $this->json($method, $uri, $body);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_profile_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/vendor/profile');
    }

    public function test_categories_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/vendor/categories');
    }

    public function test_accounts_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/vendor/accounts');
    }

    public function test_addresses_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/vendor/addresses');
    }

    public function test_jobscopes_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/vendor/jobscopes');
    }

    public function test_ssm_licences_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/vendor/ssm-licences');
    }

    public function test_mof_licences_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/vendor/mof-licences');
    }

    public function test_other_licences_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/vendor/other-licences');
    }

    public function test_profile_update_requires_authentication(): void
    {
        // Phase 2a: PUT /api/portal/vendor/profile mirrors the legacy
        // ?detail_process=1 direct-edit branch. Anonymous callers must
        // fall through to the standard UNAUTHORIZED envelope before any
        // form-request validation runs.
        $this->assertUnauthorized('PUT', '/api/portal/vendor/profile', [
            'vendorName' => 'Acme Sdn Bhd',
            'email' => 'vendor@example.com',
            'telNo' => '0123456789',
            'bumiStatus' => 'B',
            'contactPerson' => 'John Doe',
            'kkRegNo' => 'MOF-001',
            'rosNo' => 'ROS-001',
            'nameApplication' => 'John Doe',
            'telNoApplication' => '0123456789',
        ]);
    }

    public function test_lookups_requires_authentication(): void
    {
        // GET /api/portal/vendor/lookups returns the dropdown option
        // sets the master form needs (Taraf / company category / vendor
        // status / Y-N). Like every other portal endpoint it is gated
        // by auth:sanctum.
        $this->assertUnauthorized('GET', '/api/portal/vendor/lookups');
    }
}
