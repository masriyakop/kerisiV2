<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PettyCashRecoupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_petty_cash_recoup(): void
    {
        $response = $this->getJson('/api/petty-cash/recoup');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_show_petty_cash_recoup_batch(): void
    {
        $response = $this->getJson('/api/petty-cash/recoup/1');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
