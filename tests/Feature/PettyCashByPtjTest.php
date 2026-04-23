<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * List Petty Cash by PTJ (PAGEID 1963 / MENUID 2399).
 *
 * The endpoint reads from the legacy FIMS `mysql_secondary` schema, which is
 * not provisioned in the test SQLite `:memory:` database, so only the auth
 * guard is asserted here.
 */
class PettyCashByPtjTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_petty_cash_by_ptj(): void
    {
        $response = $this->getJson('/api/petty-cash/by-ptj');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
