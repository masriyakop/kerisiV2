<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the FIMS Investment module migrations. The endpoints
 * read from the external `mysql_secondary` schema which is not provisioned
 * in the in-memory test database (same constraint as StudentFinanceTest /
 * PortalTest / PurchasingTest), so only the Sanctum auth guard is covered
 * here.
 */
class InvestmentTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_list_of_accrual_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/list-of-accrual');
    }

    public function test_list_of_accrual_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/list-of-accrual/options');
    }

    public function test_summary_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/summary-list');
    }

    public function test_summary_list_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/summary-list/options');
    }

    public function test_list_of_investments_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/list');
    }

    public function test_list_of_investments_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/list/options');
    }

    public function test_investment_to_be_withdrawn_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/withdrawn');
    }

    public function test_investment_to_be_withdrawn_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/withdrawn/options');
    }

    public function test_investment_to_be_withdrawn_modal_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/withdrawn/1/modal');
    }

    public function test_investment_to_be_withdrawn_withdraw_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/investment/withdrawn/1/withdraw');
    }

    public function test_investment_accrual_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/accrual');
    }

    public function test_investment_accrual_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/accrual/options');
    }

    public function test_investment_accrual_post_to_tb_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/investment/accrual/post-to-tb');
    }

    public function test_investment_generate_schedule_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/generate-schedule');
    }

    public function test_investment_generate_schedule_generate_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/investment/generate-schedule/generate');
    }

    public function test_investment_monitoring_batches_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/monitoring/batches');
    }

    public function test_investment_monitoring_investments_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/monitoring/investments');
    }

    public function test_investment_monitoring_summary_pdf_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/investment/monitoring/summary-pdf');
    }
}
