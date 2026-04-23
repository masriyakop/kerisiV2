<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Request Petty Cash list (PAGEID 2010 / MENUID 2456). Reads from
 * `mysql_secondary`; auth guard is the only case covered here.
 */
class PettyCashRequestListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_petty_cash_requests(): void
    {
        $response = $this->getJson('/api/petty-cash/requests');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
