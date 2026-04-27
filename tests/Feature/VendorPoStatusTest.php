<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the Vendor Portal > Purchase Order Status endpoint
 * (PAGEID 1664 / MENUID 2015).
 *
 * The underlying `purchase_order_master` and `goods_receive_master`
 * tables live on the external FIMS schema (`mysql_secondary`) which is
 * not provisioned in the SQLite `:memory:` test database. Following the
 * convention used by CashbookTest / AuditSystemTransactionTest and the
 * rest of the FIMS feature suite, we only cover the 401 auth guard
 * here. The vendor-scoping (`vcs_vendor_code = auth user name`) and the
 * 200-path data-shape assertions are validated in staging against the
 * read replica.
 */
class VendorPoStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $response = $this->json('GET', '/api/portal/vendor/po-status');
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
