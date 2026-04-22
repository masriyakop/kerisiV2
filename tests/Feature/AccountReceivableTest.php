<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract tests for the FIMS Account Receivable endpoints:
 *   - Debtor                 (MENUID 1727) datatable + smart filter + delete cascade
 *   - Cashbook PTJ           (MENUID 1049) read-only UNION of offline/preprinted receipts
 *   - Credit Note            (MENUID 1041) note list + cascading delete
 *   - Debit Note             (MENUID 1042) note list + cascading delete
 *   - Discount Note          (MENUID 1043) note list + cascading delete
 *   - Authorized Receipting  (MENUID 1952) workflow applications list + delete
 *
 * These endpoints read from the external FIMS schema (mysql_secondary) which
 * is not provisioned in the test SQLite in-memory database, so only the auth
 * guard is covered here — mirroring AccountPayableTest and CashbookTest.
 */
class AccountReceivableTest extends TestCase
{
    use RefreshDatabase;

    private function assertUnauthorized(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_debtor_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/debtor/options');
    }

    public function test_debtor_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/debtor');
    }

    public function test_debtor_destroy_requires_authentication(): void
    {
        $this->assertUnauthorized('DELETE', '/api/account-receivable/debtor/1');
    }

    public function test_cashbook_ptj_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/cashbook-ptj');
    }

    public function test_credit_note_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/credit-note');
    }

    public function test_credit_note_destroy_requires_authentication(): void
    {
        $this->assertUnauthorized('DELETE', '/api/account-receivable/credit-note/1');
    }

    public function test_debit_note_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/debit-note');
    }

    public function test_debit_note_destroy_requires_authentication(): void
    {
        $this->assertUnauthorized('DELETE', '/api/account-receivable/debit-note/1');
    }

    public function test_discount_note_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/discount-note');
    }

    public function test_discount_note_destroy_requires_authentication(): void
    {
        $this->assertUnauthorized('DELETE', '/api/account-receivable/discount-note/1');
    }

    public function test_authorized_receipting_options_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/authorized-receipting/options');
    }

    public function test_authorized_receipting_list_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/authorized-receipting');
    }

    public function test_authorized_receipting_destroy_requires_authentication(): void
    {
        $this->assertUnauthorized('DELETE', '/api/account-receivable/authorized-receipting/1');
    }

    public function test_credit_note_form_invoice_lines_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/credit-note-form/invoice-lines?invoice_id=abc');
    }

    public function test_ar_lookup_customer_type_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/lookup/customer-type');
    }

    public function test_credit_note_form_search_debtor_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/credit-note-form/search-debtor?q=abc');
    }

    public function test_credit_note_form_search_invoice_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/credit-note-form/search-invoice?cust_id=abc');
    }

    public function test_credit_note_form_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/credit-note-form/1');
    }

    public function test_credit_note_form_save_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/credit-note-form');
    }

    public function test_credit_note_form_submit_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/credit-note-form/1/submit');
    }

    public function test_credit_note_form_cancel_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/credit-note-form/1/cancel');
    }

    public function test_credit_note_form_process_flow_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/credit-note-form/1/process-flow');
    }

    public function test_debit_note_form_invoice_lines_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/debit-note-form/invoice-lines?invoice_id=abc');
    }

    public function test_debit_note_form_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/debit-note-form/1');
    }

    public function test_debit_note_form_save_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/debit-note-form');
    }

    public function test_debit_note_form_submit_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/debit-note-form/1/submit');
    }

    public function test_debit_note_form_cancel_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/debit-note-form/1/cancel');
    }

    public function test_debit_note_form_process_flow_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/debit-note-form/1/process-flow');
    }

    public function test_discount_note_form_discount_policies_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/discount-note-form/discount-policies');
    }

    public function test_discount_note_form_invoice_lines_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/discount-note-form/invoice-lines?invoice_id=abc&policy_id=1');
    }

    public function test_discount_note_form_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/discount-note-form/1');
    }

    public function test_discount_note_form_save_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/discount-note-form');
    }

    public function test_discount_note_form_submit_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/discount-note-form/1/submit');
    }

    public function test_discount_note_form_cancel_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/discount-note-form/1/cancel');
    }

    public function test_discount_note_form_process_flow_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/discount-note-form/1/process-flow');
    }

    public function test_authorized_receipting_form_show_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/authorized-receipting-form/1');
    }

    public function test_authorized_receipting_form_save_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/authorized-receipting-form');
    }

    public function test_authorized_receipting_form_submit_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/authorized-receipting-form/1/submit');
    }

    public function test_authorized_receipting_form_cancel_requires_authentication(): void
    {
        $this->assertUnauthorized('POST', '/api/account-receivable/authorized-receipting-form/1/cancel');
    }

    public function test_authorized_receipting_form_process_flow_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/authorized-receipting-form/1/process-flow');
    }

    public function test_authorized_receipting_form_current_staff_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/authorized-receipting-form/current-staff');
    }

    public function test_authorized_receipting_form_search_event_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/authorized-receipting-form/search-event');
    }

    public function test_authorized_receipting_form_search_staff_requires_authentication(): void
    {
        $this->assertUnauthorized('GET', '/api/account-receivable/authorized-receipting-form/search-staff');
    }
}
