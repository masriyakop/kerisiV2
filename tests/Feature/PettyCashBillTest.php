<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bill Petty Cash list (PAGEID 1964 / MENUID 2400). Reads from `mysql_secondary`;
 * auth guard is the only case covered here.
 */
class PettyCashBillTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_petty_cash_bills(): void
    {
        $response = $this->getJson('/api/petty-cash/bills');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
