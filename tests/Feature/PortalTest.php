<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the FIMS Portal read-only listings (Debtor Profile
 * Update Apps, Tender/Quotation List, Online Registration Fee History).
 * Like the other FIMS features, the endpoints read from the external
 * `mysql_secondary` schema which is not provisioned in the in-memory
 * test database, so only the auth guard is covered here.
 */
class PortalTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_debtor_profile_update_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/debtor/profile-update-applications');
    }

    public function test_portal_tender_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/vendor/tenders');
    }

    public function test_portal_tender_vendor_check_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/vendor/tenders/check-status');
    }

    public function test_portal_registration_fees_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/vendor/registration-fees');
    }

    public function test_portal_debtor_reminders_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/debtor/reminders');
    }

    public function test_portal_debtor_statement_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/portal/debtor/statement');
    }
}
