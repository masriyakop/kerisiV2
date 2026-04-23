<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PettyCashApplicationListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_petty_cash_applications(): void
    {
        $response = $this->getJson('/api/petty-cash/applications');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_access_petty_cash_application_options(): void
    {
        $response = $this->getJson('/api/petty-cash/applications/options');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
