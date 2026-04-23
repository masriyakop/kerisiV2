<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirmation Payment — Petty Cash (PAGEID 1982 / MENUID 2424). Reads from
 * `mysql_secondary`; only auth guards are asserted here.
 */
class PettyCashConfirmPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_awaiting(): void
    {
        $response = $this->getJson('/api/petty-cash/confirm-payment/awaiting');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_list_confirmed(): void
    {
        $response = $this->getJson('/api/petty-cash/confirm-payment/confirmed');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
