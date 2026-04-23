<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * List of Voucher Petty Cash (PAGEID 2774 / MENUID 3344). Reads from
 * `mysql_secondary`; only auth guards are asserted here.
 */
class PettyCashVoucherListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_vouchers(): void
    {
        $response = $this->getJson('/api/petty-cash/vouchers');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_read_options(): void
    {
        $response = $this->getJson('/api/petty-cash/vouchers/options');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
