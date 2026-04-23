<?php

namespace Tests\Feature;

use App\Http\Requests\StoreGlYearMonthRequest;
use App\Http\Requests\UpdateGlYearMonthRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Contract tests for the FIMS General Ledger migrations. The endpoints
 * read from the external `mysql_secondary` schema which is not
 * provisioned in the in-memory test database (same constraint as
 * PortalTest / PurchasingTest / StudentFinanceTest), so only the Sanctum
 * auth guard is covered here.
 */
class GeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_journal_listing_index_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/journal-listing');
    }

    public function test_journal_listing_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/journal-listing/options');
    }

    public function test_journal_listing_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/journal-listing/1');
    }

    public function test_journal_listing_destroy_requires_authentication(): void
    {
        $this->assertUnauthorized('DELETE', '/api/general-ledger/journal-listing/1');
    }

    public function test_year_month_index_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/year-month');
    }

    public function test_year_month_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/year-month/options');
    }

    public function test_year_month_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/year-month/1');
    }

    public function test_year_month_store_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/general-ledger/year-month');
    }

    public function test_year_month_update_requires_authentication(): void
    {
        $this->assertUnauthorized('PUT', '/api/general-ledger/year-month/1');
    }

    /**
     * Regression guard for the `preg_match(): No ending delimiter '/' found`
     * bug: if any rule that contains a regex with `|` is expressed in the
     * string `rule1|rule2|regex:/.../` form, Laravel splits on `|` inside
     * the pattern and validation blows up at runtime.
     *
     * Both Store/Update requests MUST express these rules as arrays.
     */
    public function test_year_month_store_rules_accept_valid_payload(): void
    {
        $rules = (new StoreGlYearMonthRequest)->rules();

        $valid = Validator::make([
            'gym_year' => '2026',
            'gym_month' => '04',
            'gym_status' => 'OPEN',
            'gym_remark' => 'Quarterly open',
        ], $rules);

        $this->assertTrue($valid->passes(), 'Valid payload should pass: '.$valid->errors()->first());

        $invalid = Validator::make([
            'gym_year' => '12',
            'gym_month' => '13',
            'gym_status' => 'PENDING',
        ], $rules);

        $this->assertTrue($invalid->fails());
        $this->assertArrayHasKey('gym_year', $invalid->errors()->toArray());
        $this->assertArrayHasKey('gym_month', $invalid->errors()->toArray());
        $this->assertArrayHasKey('gym_status', $invalid->errors()->toArray());
    }

    public function test_year_month_update_rules_accept_valid_payload(): void
    {
        $rules = (new UpdateGlYearMonthRequest)->rules();

        $valid = Validator::make([
            'gym_year' => '2026',
            'gym_month' => '12',
            'gym_status' => 'CLOSE',
            'gym_remark' => null,
        ], $rules);

        $this->assertTrue($valid->passes(), 'Valid payload should pass: '.$valid->errors()->first());
    }

    public function test_posting_to_tb_index_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/posting-to-tb');
    }

    public function test_posting_to_tb_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/posting-to-tb/options');
    }

    public function test_posting_to_tb_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/posting-to-tb/1');
    }

    public function test_general_ledger_listing_index_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/general-ledger-listing');
    }

    public function test_general_ledger_listing_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/general-ledger-listing/options');
    }

    public function test_manual_journal_index_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/manual-journal');
    }

    public function test_manual_journal_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/manual-journal/options');
    }

    public function test_manual_journal_destroy_requires_authentication(): void
    {
        $this->assertUnauthorized('DELETE', '/api/general-ledger/manual-journal/1');
    }

    public function test_manual_journal_listing_pdf_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/manual-journal/listing-pdf');
    }

    public function test_manual_journal_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/general-ledger/manual-journal/1');
    }
}
