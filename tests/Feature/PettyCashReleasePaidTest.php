<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * List of Release Paid — Petty Cash (PAGEID 2273 / MENUID 2761). Reads from
 * `mysql_secondary`; only auth guards are asserted here.
 */
class PettyCashReleasePaidTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_applications(): void
    {
        $response = $this->getJson('/api/petty-cash/release-paid/applications');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_list_receipts(): void
    {
        $response = $this->getJson('/api/petty-cash/release-paid/receipts?pms_id=1');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
