<?php

use App\Http\Controllers\Api\AccountBankByPayeeController;
use App\Http\Controllers\Api\AccountBankUpdatedController;
use App\Http\Controllers\Api\AccountCodeController;
use App\Http\Controllers\Api\AccountCodePpiController;
use App\Http\Controllers\Api\ActivityCodeController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthorizedReceiptingController;
use App\Http\Controllers\Api\AuthorizedReceiptingFormController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\BankMasterController;
use App\Http\Controllers\Api\BankSetupController;
use App\Http\Controllers\Api\BudgetClosingController;
use App\Http\Controllers\Api\BudgetInitialController;
use App\Http\Controllers\Api\BudgetMonitoringController;
use App\Http\Controllers\Api\BudgetMovementController;
use App\Http\Controllers\Api\CascadeStructureController;
use App\Http\Controllers\Api\CashbookListController;
use App\Http\Controllers\Api\CashbookPtjController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckErrorController;
use App\Http\Controllers\Api\CostCentreController;
use App\Http\Controllers\Api\CreditNoteController;
use App\Http\Controllers\Api\CreditNoteFormController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DebitNoteController;
use App\Http\Controllers\Api\DebitNoteFormController;
use App\Http\Controllers\Api\DebtorController;
use App\Http\Controllers\Api\DebtorProfileUpdateController;
use App\Http\Controllers\Api\DebtorReminderController;
use App\Http\Controllers\Api\DebtorStatementController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\DepositFormController;
use App\Http\Controllers\Api\DevelopersGuideController;
use App\Http\Controllers\Api\DiscountNoteController;
use App\Http\Controllers\Api\DiscountNoteFormController;
use App\Http\Controllers\Api\FundTypeController;
use App\Http\Controllers\Api\InvoiceBalanceController;
use App\Http\Controllers\Api\LetterPhraseController;
use App\Http\Controllers\Api\ListOfDepositController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PayeeRegistrationController;
use App\Http\Controllers\Api\PettyCashApplicationListController;
use App\Http\Controllers\Api\PettyCashBillController;
use App\Http\Controllers\Api\PettyCashClaimFormController;
use App\Http\Controllers\Api\PettyCashByPtjController;
use App\Http\Controllers\Api\PettyCashConfirmPaymentController;
use App\Http\Controllers\Api\PettyCashRecoupController;
use App\Http\Controllers\Api\PettyCashReleasePaidController;
use App\Http\Controllers\Api\PettyCashRequestListController;
use App\Http\Controllers\Api\PettyCashVoucherListController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PtjCodeController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SetupBudgetStructureSearchController;
use App\Http\Controllers\Api\TenderQuotationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UtilityRegistrationController;
use App\Http\Controllers\Api\VcTncController;
use App\Http\Controllers\Api\VendorRegistrationFeeHistoryController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth)
Route::prefix('public')->group(function () {
    Route::get('/site', [PublicController::class, 'site']);
    Route::get('/pages/frontpage', [PublicController::class, 'frontpage']);
    Route::get('/pages/{slug}', [PublicController::class, 'pageBySlug']);
});

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me', [AuthController::class, 'updateProfile']);
        Route::post('/password', [AuthController::class, 'changePassword']);
        Route::post('/avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('/avatar', [AuthController::class, 'removeAvatar']);
    });
});

// Settings GET is public (used by SPA before auth)
Route::get('/settings', [SettingController::class, 'index']);

// Protected admin routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('posts', PostController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('pages', PageController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::get('/fund-types/options', [FundTypeController::class, 'formOptions']);
    Route::get('/fund-types', [FundTypeController::class, 'index']);
    Route::get('/fund-types/{id}', [FundTypeController::class, 'show']);
    Route::post('/fund-types', [FundTypeController::class, 'store']);
    Route::put('/fund-types/{id}', [FundTypeController::class, 'update']);
    Route::get('/setup/activity-code', [ActivityCodeController::class, 'index']);
    Route::post('/setup/activity-code/group', [ActivityCodeController::class, 'storeGroup']);
    Route::put('/setup/activity-code/group/{code}', [ActivityCodeController::class, 'updateGroup']);
    Route::delete('/setup/activity-code/group/{code}', [ActivityCodeController::class, 'deleteGroup']);
    Route::post('/setup/activity-code/subgroup', [ActivityCodeController::class, 'storeSubgroup']);
    Route::put('/setup/activity-code/subgroup/{code}', [ActivityCodeController::class, 'updateSubgroup']);
    Route::delete('/setup/activity-code/subgroup/{code}', [ActivityCodeController::class, 'deleteSubgroup']);
    Route::post('/setup/activity-code/subsiri', [ActivityCodeController::class, 'storeSubsiri']);
    Route::put('/setup/activity-code/subsiri/{code}', [ActivityCodeController::class, 'updateSubsiri']);
    Route::delete('/setup/activity-code/subsiri/{code}', [ActivityCodeController::class, 'deleteSubsiri']);
    Route::post('/setup/activity-code/activity-type', [ActivityCodeController::class, 'storeActivityType']);
    Route::put('/setup/activity-code/activity-type/{id}', [ActivityCodeController::class, 'updateActivityType']);
    Route::delete('/setup/activity-code/activity-type/{id}', [ActivityCodeController::class, 'deleteActivityType']);
    Route::get('/setup/account-code/activity', [AccountCodeController::class, 'listActivity']);
    Route::post('/setup/account-code/activity', [AccountCodeController::class, 'storeActivity']);
    Route::put('/setup/account-code/activity/{id}', [AccountCodeController::class, 'updateActivity']);
    Route::delete('/setup/account-code/activity/{id}', [AccountCodeController::class, 'deleteActivity']);
    Route::get('/setup/account-code', [AccountCodeController::class, 'index']);
    Route::post('/setup/account-code', [AccountCodeController::class, 'store']);
    Route::put('/setup/account-code/{code}', [AccountCodeController::class, 'update']);
    Route::delete('/setup/account-code/{code}', [AccountCodeController::class, 'destroy']);
    Route::get('/setup/account-code-ppi/options', [AccountCodePpiController::class, 'options']);
    Route::get('/setup/account-code-ppi', [AccountCodePpiController::class, 'index']);
    Route::get('/setup/ptj-code', [PtjCodeController::class, 'index']);
    Route::post('/setup/ptj-code', [PtjCodeController::class, 'store']);
    Route::put('/setup/ptj-code/{code}', [PtjCodeController::class, 'update']);
    Route::delete('/setup/ptj-code/{code}', [PtjCodeController::class, 'destroy']);
    Route::get('/setup/cost-centre/options', [CostCentreController::class, 'options']);
    Route::get('/setup/cost-centre', [CostCentreController::class, 'index']);
    Route::get('/setup/cost-centre/{id}', [CostCentreController::class, 'show']);
    Route::post('/setup/cost-centre', [CostCentreController::class, 'store']);
    Route::put('/setup/cost-centre/{id}', [CostCentreController::class, 'update']);
    // FIMS Budget (Increment / Decrement / Virement) list screens. Read-only; the
    // add/edit/cancel actions live on editor pages that are not yet migrated.
    Route::get('/budget/movements/{type}/options', [BudgetMovementController::class, 'options'])
        ->whereIn('type', ['increment', 'decrement', 'virement']);
    Route::get('/budget/movements/{type}', [BudgetMovementController::class, 'index'])
        ->whereIn('type', ['increment', 'decrement', 'virement']);
    Route::get('/budget/movements/show/{id}', [BudgetMovementController::class, 'show']);

    // FIMS Budget Monitoring (PAGEID 1201 / MENUID 1471) – read-only aggregated list.
    Route::get('/budget/monitoring/options', [BudgetMonitoringController::class, 'options']);
    Route::get('/budget/monitoring', [BudgetMonitoringController::class, 'index']);

    // FIMS Budget Initial V2 (PAGEID 1264 / MENUID 1541) – documented stub; legacy
    // BL SWS_DT_BUDGET_INITIAL_V2 was not shipped in the migration export.
    Route::get('/budget/initial/options', [BudgetInitialController::class, 'options']);
    Route::get('/budget/initial', [BudgetInitialController::class, 'index']);

    // FIMS Budget Closing (PAGEID 1953 / MENUID 2389) – filter + Start/Reverse
    // Process buttons. Server-side BL NAD_API_BUDGET_BUDGETCLOSING is not ported;
    // the process/reverse endpoints return 501 with an explanatory payload.
    Route::get('/budget/closing/options', [BudgetClosingController::class, 'options']);
    Route::post('/budget/closing/process', [BudgetClosingController::class, 'process']);
    Route::post('/budget/closing/reverse', [BudgetClosingController::class, 'reverse']);

    // FIMS Cashbook (Bank Setup / Bank Master / Bank Account / List Of Cashbook)
    // — see app/Http/Controllers/Api/{BankSetupController,BankMasterController,
    // BankAccountController,CashbookListController}.php for the per-page details.
    Route::get('/cashbook/bank-setup/options', [BankSetupController::class, 'options']);
    Route::get('/cashbook/bank-setup', [BankSetupController::class, 'index']);
    Route::get('/cashbook/bank-setup/{code}', [BankSetupController::class, 'show']);
    Route::post('/cashbook/bank-setup', [BankSetupController::class, 'store']);
    Route::put('/cashbook/bank-setup/{code}', [BankSetupController::class, 'update']);

    Route::get('/cashbook/bank-master/options', [BankMasterController::class, 'options']);
    Route::get('/cashbook/bank-master', [BankMasterController::class, 'index']);
    Route::get('/cashbook/bank-master/{id}', [BankMasterController::class, 'show'])->whereNumber('id');
    Route::post('/cashbook/bank-master', [BankMasterController::class, 'store']);
    Route::put('/cashbook/bank-master/{id}', [BankMasterController::class, 'update'])->whereNumber('id');

    Route::get('/cashbook/bank-account/options', [BankAccountController::class, 'options']);
    Route::get('/cashbook/bank-account', [BankAccountController::class, 'index']);
    Route::get('/cashbook/bank-account/{id}', [BankAccountController::class, 'show'])->whereNumber('id');
    Route::post('/cashbook/bank-account', [BankAccountController::class, 'store']);
    Route::put('/cashbook/bank-account/{id}', [BankAccountController::class, 'update'])->whereNumber('id');

    Route::get('/cashbook/list/{type}/options', [CashbookListController::class, 'options'])
        ->whereIn('type', ['daily', 'monthly', 'DAILY', 'MONTHLY']);
    Route::get('/cashbook/list/{type}', [CashbookListController::class, 'index'])
        ->whereIn('type', ['daily', 'monthly', 'DAILY', 'MONTHLY']);

    // FIMS Account Payable — Payee Registration (read-only), Utility Registration
    // (list + inline add/edit via popup modal), Account Bank by Payee (read-only,
    // payee-type driven), Account Bank Updated (payee-driven list of bills/vouchers
    // whose bank details drift from the payee master + bulk re-sync). See the
    // respective controllers for full details.
    Route::get('/account-payable/payee-registration/options', [PayeeRegistrationController::class, 'options']);
    Route::get('/account-payable/payee-registration', [PayeeRegistrationController::class, 'index']);

    // Petty Cash Recoup list (PAGEID 1255 / MENUID 1532). Legacy BL API_PETTYCASH_PETTYCASHRECOUP.
    Route::get('/petty-cash/recoup', [PettyCashRecoupController::class, 'index']);

    // Petty Cash Recoup form view (PAGEID 1256 / MENUID 1534). Legacy BL
    // API_PETTYCASH_PETTYCASHRECOUPFORM — PettyCashBatchMaster + PettyCashRecoupDetailSelected_dt.
    Route::get('/petty-cash/recoup/{pcb_id}', [PettyCashRecoupController::class, 'show'])->whereNumber('pcb_id');

    // List of Petty Cash Application (PAGEID 1217 / MENUID 1490). Legacy BL API_PETTYCASH_LISTAPPLICATIONPETTYCASH.
    Route::get('/petty-cash/applications/options', [PettyCashApplicationListController::class, 'options']);
    Route::get('/petty-cash/applications', [PettyCashApplicationListController::class, 'index']);
    Route::get('/petty-cash/applications/{id}', [PettyCashApplicationListController::class, 'show']);

    // Petty Cash Claim Form (PAGEID 1544 / MENUID 1872). Legacy BL MM_API_PETTYCASH_PETTYCASHCLAIMFORM.
    Route::get('/petty-cash/claim-form/request-by/suggest', [PettyCashClaimFormController::class, 'suggestRequestBy']);
    Route::get('/petty-cash/claim-form/pcm/suggest', [PettyCashClaimFormController::class, 'suggestPcm']);
    Route::get('/petty-cash/claim-form/account-code/suggest', [PettyCashClaimFormController::class, 'suggestAccountCode']);
    Route::get('/petty-cash/claim-form/fund-type/suggest', [PettyCashClaimFormController::class, 'suggestFundType']);
    Route::get('/petty-cash/claim-form/activity-code/suggest', [PettyCashClaimFormController::class, 'suggestActivityCode']);
    Route::get('/petty-cash/claim-form/oun/suggest', [PettyCashClaimFormController::class, 'suggestOun']);
    Route::get('/petty-cash/claim-form/cost-centre/suggest', [PettyCashClaimFormController::class, 'suggestCostCentre']);
    Route::get('/petty-cash/claim-form/next-seq', [PettyCashClaimFormController::class, 'nextDetailSeq']);
    Route::get('/petty-cash/claim-form/{id}', [PettyCashClaimFormController::class, 'show'])->whereNumber('id');
    Route::post('/petty-cash/claim-form', [PettyCashClaimFormController::class, 'saveDraft']);
    Route::post('/petty-cash/claim-form/{id}/submit', [PettyCashClaimFormController::class, 'submit'])->whereNumber('id');
    Route::post('/petty-cash/claim-form/{id}/cancel', [PettyCashClaimFormController::class, 'cancel'])->whereNumber('id');
    Route::get('/petty-cash/claim-form/{id}/process-flow', [PettyCashClaimFormController::class, 'processFlow'])->whereNumber('id');

    // List Petty Cash by PTJ (PAGEID 1963 / MENUID 2399). Legacy BL NAD_API_PC_PETTYCASHBYPTJ.
    Route::get('/petty-cash/by-ptj', [PettyCashByPtjController::class, 'index']);

    // Bill Petty Cash (PAGEID 1964 / MENUID 2400). Legacy BL NAD_API_PC_PETTYCASHBILL.
    Route::get('/petty-cash/bills', [PettyCashBillController::class, 'index']);

    // Confirmation Payment — Petty Cash (PAGEID 1982 / MENUID 2424). Legacy BL NAD_API_PC_CONFIRMATIONPAYMENT.
    Route::get('/petty-cash/confirm-payment/awaiting', [PettyCashConfirmPaymentController::class, 'awaiting']);
    Route::get('/petty-cash/confirm-payment/confirmed', [PettyCashConfirmPaymentController::class, 'confirmed']);

    // Request Petty Cash list (PAGEID 2010 / MENUID 2456). Legacy BL NAD_API_PC_REQUESTPETTYCASH.
    Route::get('/petty-cash/requests', [PettyCashRequestListController::class, 'index']);

    // List of Release Paid — Petty Cash (PAGEID 2273 / MENUID 2761). Legacy BL NAD_API_PC_LISTOFRELEASEPAID.
    Route::get('/petty-cash/release-paid/applications', [PettyCashReleasePaidController::class, 'applications']);
    Route::get('/petty-cash/release-paid/receipts', [PettyCashReleasePaidController::class, 'receipts']);

    // List of Voucher Petty Cash (PAGEID 2774 / MENUID 3344). Legacy BL NAD_API_PC_LISTOFVOUCHERPETTYCASH.
    Route::get('/petty-cash/vouchers/options', [PettyCashVoucherListController::class, 'options']);
    Route::get('/petty-cash/vouchers', [PettyCashVoucherListController::class, 'index']);

    Route::get('/account-payable/utility-registration', [UtilityRegistrationController::class, 'index']);
    Route::get('/account-payable/utility-registration/{id}', [UtilityRegistrationController::class, 'show'])->whereNumber('id');
    Route::post('/account-payable/utility-registration', [UtilityRegistrationController::class, 'store']);
    Route::put('/account-payable/utility-registration/{id}', [UtilityRegistrationController::class, 'update'])->whereNumber('id');

    Route::get('/account-payable/account-bank-by-payee/options', [AccountBankByPayeeController::class, 'options']);
    Route::get('/account-payable/account-bank-by-payee', [AccountBankByPayeeController::class, 'index']);

    Route::get('/account-payable/account-bank-updated/options', [AccountBankUpdatedController::class, 'options']);
    Route::get('/account-payable/account-bank-updated/bills', [AccountBankUpdatedController::class, 'listBills']);
    Route::get('/account-payable/account-bank-updated/vouchers', [AccountBankUpdatedController::class, 'listVouchers']);
    Route::post('/account-payable/account-bank-updated/bills/process', [AccountBankUpdatedController::class, 'processBills']);
    Route::post('/account-payable/account-bank-updated/vouchers/process', [AccountBankUpdatedController::class, 'processVouchers']);

    // FIMS Account Receivable — Debtor (PAGEID 1415 / MENUID 1727): datatable with
    // smart filter (Status) + cascade delete across vend_customer_supplier,
    // vend_supplier_account and vendor_address. Cashbook PTJ (PAGEID 2048 /
    // MENUID 1049): read-only UNION of offline and preprinted receipts grouped
    // by staff + counter. See DebtorController / CashbookPtjController.
    Route::get('/account-receivable/debtor/options', [DebtorController::class, 'options']);
    Route::get('/account-receivable/debtor', [DebtorController::class, 'index']);
    Route::delete('/account-receivable/debtor/{id}', [DebtorController::class, 'destroy'])->whereNumber('id');

    Route::get('/account-receivable/cashbook-ptj', [CashbookPtjController::class, 'index']);

    // FIMS Account Receivable — Note lists (PAGEID 1459/1461/1463, MENUID
    // 1041/1042/1043) all follow the same BL shape: list + search over coded
    // + JSON-extended desc columns, delete cascades through *_details first.
    // Scoped by `{c,d,dc}nm_system_id` sentinels (AR_CN / AR_DN / AR_DC).
    Route::get('/account-receivable/credit-note', [CreditNoteController::class, 'index']);
    Route::delete('/account-receivable/credit-note/{id}', [CreditNoteController::class, 'destroy']);

    Route::get('/account-receivable/debit-note', [DebitNoteController::class, 'index']);
    Route::delete('/account-receivable/debit-note/{id}', [DebitNoteController::class, 'destroy']);

    Route::get('/account-receivable/discount-note', [DiscountNoteController::class, 'index']);
    Route::delete('/account-receivable/discount-note/{id}', [DiscountNoteController::class, 'destroy']);

    // Credit Note Form (PAGEID 1474 / MENUID 1782) — BL DT_AR_CREDIT_NOTE_FORM.
    // The legacy single-endpoint `?action=` switch is split into REST
    // endpoints. `submit` / `cancel` / `process-flow` are workflow stubs
    // (status transition + reason logging) because the FIMS workflow SPs
    // and `wf_task` tables are not yet migrated — see controller docblock.
    // Shared AR note-form lookups (Customer Type / Debtor Type dropdown, and
    // autosuggest searches for Debtor Name and Invoice No).
    Route::get('/account-receivable/lookup/customer-type', [CreditNoteFormController::class, 'customerTypes']);
    Route::get('/account-receivable/credit-note-form/search-debtor', [CreditNoteFormController::class, 'searchDebtors']);
    Route::get('/account-receivable/credit-note-form/search-invoice', [CreditNoteFormController::class, 'searchInvoices']);
    Route::get('/account-receivable/credit-note-form/invoice-lines', [CreditNoteFormController::class, 'invoiceLines']);
    Route::get('/account-receivable/credit-note-form/{id}', [CreditNoteFormController::class, 'show']);
    Route::post('/account-receivable/credit-note-form', [CreditNoteFormController::class, 'saveDraft']);
    Route::post('/account-receivable/credit-note-form/{id}/submit', [CreditNoteFormController::class, 'submit']);
    Route::post('/account-receivable/credit-note-form/{id}/cancel', [CreditNoteFormController::class, 'cancel']);
    Route::get('/account-receivable/credit-note-form/{id}/process-flow', [CreditNoteFormController::class, 'processFlow']);

    // Debit Note Form (PAGEID 1476 / MENUID 1783) — BL DT_AR_DEBIT_NOTE_FORM.
    // Same Wave-B stubbing rationale as the credit-note form above.
    Route::get('/account-receivable/debit-note-form/invoice-lines', [DebitNoteFormController::class, 'invoiceLines']);
    Route::get('/account-receivable/debit-note-form/{id}', [DebitNoteFormController::class, 'show']);
    Route::post('/account-receivable/debit-note-form', [DebitNoteFormController::class, 'saveDraft']);
    Route::post('/account-receivable/debit-note-form/{id}/submit', [DebitNoteFormController::class, 'submit']);
    Route::post('/account-receivable/debit-note-form/{id}/cancel', [DebitNoteFormController::class, 'cancel']);
    Route::get('/account-receivable/debit-note-form/{id}/process-flow', [DebitNoteFormController::class, 'processFlow']);

    // Authorized Receipting list (PAGEID 1613 / MENUID 1952). Legacy BL scoped
    // to logged-in staff OR UUM_UNIT_TERIMAAN group; we expose a global admin
    // list with an optional ?staff_id= emulation (see controller docblock).
    Route::get('/account-receivable/authorized-receipting/options', [AuthorizedReceiptingController::class, 'options']);
    Route::get('/account-receivable/authorized-receipting', [AuthorizedReceiptingController::class, 'index']);
    Route::delete('/account-receivable/authorized-receipting/{id}', [AuthorizedReceiptingController::class, 'destroy']);

    // Discount Note Form (MENUID 1784) — BL DT_AR_DISCOUNT_NOTE_FORM.
    // Same Wave-B stubbing rationale as credit-/debit-note forms above.
    Route::get('/account-receivable/discount-note-form/discount-policies', [DiscountNoteFormController::class, 'discountPolicies']);
    Route::get('/account-receivable/discount-note-form/invoice-lines', [DiscountNoteFormController::class, 'invoiceLines']);
    Route::get('/account-receivable/discount-note-form/{id}', [DiscountNoteFormController::class, 'show']);
    Route::post('/account-receivable/discount-note-form', [DiscountNoteFormController::class, 'saveDraft']);
    Route::post('/account-receivable/discount-note-form/{id}/submit', [DiscountNoteFormController::class, 'submit']);
    Route::post('/account-receivable/discount-note-form/{id}/cancel', [DiscountNoteFormController::class, 'cancel']);
    Route::get('/account-receivable/discount-note-form/{id}/process-flow', [DiscountNoteFormController::class, 'processFlow']);

    // Authorized Receipting Form (MENUID 1953) — BL V2_AUTHORIZED_RECEIPTING_FORM_API.
    // Same Wave-B stubbing rationale; the legacy two-branch workflow (PTJ
    // same vs different) is deferred — see controller docblock.
    Route::get('/account-receivable/authorized-receipting-form/current-staff', [AuthorizedReceiptingFormController::class, 'currentStaff']);
    Route::get('/account-receivable/authorized-receipting-form/search-event', [AuthorizedReceiptingFormController::class, 'searchEvents']);
    Route::get('/account-receivable/authorized-receipting-form/search-staff', [AuthorizedReceiptingFormController::class, 'searchStaff']);
    Route::get('/account-receivable/authorized-receipting-form/{id}', [AuthorizedReceiptingFormController::class, 'show']);
    Route::post('/account-receivable/authorized-receipting-form', [AuthorizedReceiptingFormController::class, 'saveDraft']);
    Route::post('/account-receivable/authorized-receipting-form/{id}/submit', [AuthorizedReceiptingFormController::class, 'submit']);
    Route::post('/account-receivable/authorized-receipting-form/{id}/cancel', [AuthorizedReceiptingFormController::class, 'cancel']);
    Route::get('/account-receivable/authorized-receipting-form/{id}/process-flow', [AuthorizedReceiptingFormController::class, 'processFlow']);

    // ---------------------------------------------------------------- Credit Control

    // Deposit listing (PAGEID 1445 / MENUID 1809). Legacy BL
    // ZR_CREDITCONTROL_DEPOSIT_API — joined deposit_master + deposit_details
    // with global / top / smart filters and signed-amount footer.
    Route::get('/credit-control/deposit/options', [DepositController::class, 'options']);
    Route::get('/credit-control/deposit/autosuggest', [DepositController::class, 'autosuggest']);
    Route::get('/credit-control/deposit', [DepositController::class, 'index']);

    // List of Deposit (PAGEID 2159 / MENUID 3066). Legacy BL
    // SNA_API_CC_LISTOFDEPOSIT — restricted to account_main subsidiary+deposit
    // rows, with customer-type / customer-id / PTJ filters.
    Route::get('/credit-control/list-of-deposit/options', [ListOfDepositController::class, 'options']);
    Route::get('/credit-control/list-of-deposit/search-customer', [ListOfDepositController::class, 'searchCustomer']);
    Route::get('/credit-control/list-of-deposit', [ListOfDepositController::class, 'index']);

    // Invoice Balance (PAGEID 2561 / MENUID 3388). Legacy BL
    // MZS_API_CC_INVOICE_BALANCE — read-only aggregated outstanding invoices
    // computed from rep_aging_debtor as-of tf_end_date.
    Route::get('/credit-control/invoice-balance/options', [InvoiceBalanceController::class, 'options']);
    Route::get('/credit-control/invoice-balance/search-customer', [InvoiceBalanceController::class, 'searchCustomer']);
    Route::get('/credit-control/invoice-balance/search-invoice', [InvoiceBalanceController::class, 'searchInvoice']);
    Route::get('/credit-control/invoice-balance', [InvoiceBalanceController::class, 'index']);

    // Detail of Deposit (PAGEID 2688 / MENUID 3397). Legacy BL
    // NAD_API_CC_DEPOSIT_DETAILS — master form + detail datatable + popup
    // modal. Only updates are supported; no new-record flow exists in legacy.
    Route::get('/credit-control/deposit-form/search-customer', [DepositFormController::class, 'searchCustomer']);
    Route::get('/credit-control/deposit-form/{id}/details', [DepositFormController::class, 'details'])->whereNumber('id');
    Route::get('/credit-control/deposit-form/{id}', [DepositFormController::class, 'show'])->whereNumber('id');
    Route::put('/credit-control/deposit-form/{id}', [DepositFormController::class, 'update'])->whereNumber('id');
    Route::put('/credit-control/deposit-form/{id}/detail/{detailId}', [DepositFormController::class, 'updateDetail'])->whereNumber('id')->whereNumber('detailId');

    // Portal pages — read-only vendor/debtor self-service listings.
    // Debtor Portal > List of Profile Update Application (PAGEID 2155 / MENUID 2608).
    // Legacy BL: MZ_BL_DEBTOR_PORTAL_LIST (?dtListing=1).
    Route::get('/portal/debtor/profile-update-applications', [DebtorProfileUpdateController::class, 'index']);

    // Debtor Portal > Financial Information > Reminder (MENUID 2584).
    // Legacy BL: NF_BL_DEBTOR_PORTAL_REMINDER.
    Route::get('/portal/debtor/reminders', [DebtorReminderController::class, 'index']);

    // Debtor Portal > Financial Information > Debtors Statement (MENUID 2267).
    // Legacy BL: NF_BL_DP_DEBTORS_STATEMENT (running-balance AR ledger).
    Route::get('/portal/debtor/statement', [DebtorStatementController::class, 'index']);

    // Vendor Portal > Tender/Quotation List (PAGEID 2278 / MENUID 2767).
    // Legacy BL: NF_BL_PURCHASING_VENDOR_PORTAL_TENDER (?ListOfTender=1, ?check=1).
    Route::get('/portal/vendor/tenders', [TenderQuotationController::class, 'index']);
    Route::get('/portal/vendor/tenders/check-status', [TenderQuotationController::class, 'checkVendorStatus']);

    // Vendor Portal > Online Registration Fee History (PAGEID 1654 / MENUID 2003).
    // Legacy BL: NF_BL_VENDOR_ONLINE_PAYMENT (source not available); read-only
    // listing reconstructed from the frontend column spec + commented legacy
    // joins embedded in BL NF_BL_PURCHASING_VENDOR_PORTAL_TENDER.
    Route::get('/portal/vendor/registration-fees', [VendorRegistrationFeeHistoryController::class, 'index']);

    Route::get('/setup/cascade-structure/options', [CascadeStructureController::class, 'options']);
    Route::get('/setup/cascade-structure', [CascadeStructureController::class, 'index']);
    Route::get('/setup/cascade-structure/{id}', [CascadeStructureController::class, 'show']);
    Route::post('/setup/cascade-structure', [CascadeStructureController::class, 'store']);
    Route::put('/setup/cascade-structure/{id}', [CascadeStructureController::class, 'update']);

    // Letter Phrase setup (PAGEID 2911 / MENUID 3506). Legacy BL
    // SZ_SETUPANDMAINTENANCE_LETTERPHRASE_API only supports list + update;
    // delete was rendered client-side without server support.
    Route::get('/setup/letter-phrase', [LetterPhraseController::class, 'index']);
    Route::get('/setup/letter-phrase/{lpmValue}', [LetterPhraseController::class, 'show']);
    Route::put('/setup/letter-phrase/{lpmValue}', [LetterPhraseController::class, 'update']);

    // HOD, VC & TNC setup (PAGEID 1715 / MENUID 2073). Legacy BL
    // API_VC_TNC_SETUP — list organization units with their head/VC staff,
    // fetch one record for edit, and persist superior changes.
    Route::get('/setup/vc-tnc/options', [VcTncController::class, 'options']);
    Route::get('/setup/vc-tnc', [VcTncController::class, 'index']);
    Route::get('/setup/vc-tnc/{id}', [VcTncController::class, 'show']);
    Route::put('/setup/vc-tnc/{id}', [VcTncController::class, 'update']);

    // "Cek yang mungkin error" diagnostic screen (PAGEID 2253 / MENUID 2740).
    // Seven read-only datatables derived from MM_API_MAINTANANCE_CEKERROR.
    Route::prefix('setup/check-error')->group(function () {
        Route::get('/bill-master', [CheckErrorController::class, 'billMaster']);
        Route::get('/voucher-detail', [CheckErrorController::class, 'voucherDetail']);
        Route::get('/voucher-master', [CheckErrorController::class, 'voucherMaster']);
        Route::get('/payment-record-pelik', [CheckErrorController::class, 'paymentRecordPelik']);
        Route::get('/payment-record-pelik2', [CheckErrorController::class, 'paymentRecord2Pelik']);
        Route::get('/url-brf-hilang', [CheckErrorController::class, 'urlBrfHilang']);
        Route::get('/resit-no-allocate', [CheckErrorController::class, 'resitNoAllocate']);
    });

    // Setup Carian Structure Budget (PAGEID 2664 / MENUID 3224). Legacy BL
    // MM_API_GLOBAL_SETUPCARIANSBG — two datatables (setup_budget_structure_search
    // and bills_setup) plus forms for Semi-Strict column/level config and the
    // CustomWF bill setup sequence.
    Route::prefix('setup/budget-structure-search')->group(function () {
        Route::get('/options', [SetupBudgetStructureSearchController::class, 'options']);
        Route::get('/forms', [SetupBudgetStructureSearchController::class, 'forms']);
        Route::get('/jenis-carian', [SetupBudgetStructureSearchController::class, 'indexJenisCarian']);
        Route::get('/jenis-carian/{id}', [SetupBudgetStructureSearchController::class, 'showJenisCarian']);
        Route::put('/jenis-carian/{id}', [SetupBudgetStructureSearchController::class, 'updateJenisCarian']);
        Route::get('/bills-setup', [SetupBudgetStructureSearchController::class, 'indexBillsSetup']);
        Route::get('/bills-setup/{id}', [SetupBudgetStructureSearchController::class, 'showBillsSetup']);
        Route::put('/bills-setup/{id}', [SetupBudgetStructureSearchController::class, 'updateBillsSetup']);
        Route::put('/semi-strict', [SetupBudgetStructureSearchController::class, 'saveSemiStrict']);
        Route::put('/custom-wf', [SetupBudgetStructureSearchController::class, 'saveBillsCustomWf']);
    });

    Route::get('/media', [MediaController::class, 'index']);
    Route::post('/media/upload', [MediaController::class, 'upload']);
    Route::put('/media/{media}', [MediaController::class, 'update']);
    Route::delete('/media/{media}', [MediaController::class, 'destroy']);

    Route::put('/settings', [SettingController::class, 'update']);
    Route::get('/settings/admin-menu-prefs', [SettingController::class, 'adminMenuPrefs']);
    Route::put('/settings/admin-menu-prefs', [SettingController::class, 'updateAdminMenuPrefs']);
    Route::get('/settings/storefront-menu', [SettingController::class, 'storefrontMenu']);
    Route::put('/settings/storefront-menu', [SettingController::class, 'updateStorefrontMenu']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    Route::get('/developers-guide', [DevelopersGuideController::class, 'show']);
    Route::put('/developers-guide', [DevelopersGuideController::class, 'update']);
});
