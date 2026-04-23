<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Auth-guard smoke tests for the Petty Cash Claim Form endpoints
 * (MENUID 1872). Behavioural tests are deferred until the FIMS
 * `mysql_secondary` schema is wired into PHPUnit's in-memory DB.
 */
class PettyCashClaimFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_suggest_request_by(): void
    {
        $this->getJson('/api/petty-cash/claim-form/request-by/suggest?q=alice')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_suggest_pcm(): void
    {
        $this->getJson('/api/petty-cash/claim-form/pcm/suggest?q=smith')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_suggest_account_code(): void
    {
        $this->getJson('/api/petty-cash/claim-form/account-code/suggest?q=11')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_suggest_fund_type(): void
    {
        $this->getJson('/api/petty-cash/claim-form/fund-type/suggest')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_suggest_activity_code(): void
    {
        $this->getJson('/api/petty-cash/claim-form/activity-code/suggest')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_suggest_oun(): void
    {
        $this->getJson('/api/petty-cash/claim-form/oun/suggest')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_suggest_cost_centre(): void
    {
        $this->getJson('/api/petty-cash/claim-form/cost-centre/suggest')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_get_next_seq(): void
    {
        $this->getJson('/api/petty-cash/claim-form/next-seq')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_view_claim(): void
    {
        $this->getJson('/api/petty-cash/claim-form/1')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_save_claim(): void
    {
        $this->postJson('/api/petty-cash/claim-form', [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_submit_claim(): void
    {
        $this->postJson('/api/petty-cash/claim-form/1/submit')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_cancel_claim(): void
    {
        $this->postJson('/api/petty-cash/claim-form/1/cancel', ['cancelReason' => 'test'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_guest_cannot_fetch_process_flow(): void
    {
        $this->getJson('/api/petty-cash/claim-form/1/process-flow')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
