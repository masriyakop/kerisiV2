<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ActivityCodeController;
use App\Http\Controllers\Api\AccountBankByPayeeController;
use App\Http\Controllers\Api\AccountCodeController;
use App\Http\Controllers\Api\AccountCodePpiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\BankMasterController;
use App\Http\Controllers\Api\BankSetupController;
use App\Http\Controllers\Api\BudgetClosingController;
use App\Http\Controllers\Api\BudgetInitialController;
use App\Http\Controllers\Api\BudgetMonitoringController;
use App\Http\Controllers\Api\BudgetMovementController;
use App\Http\Controllers\Api\CascadeStructureController;
use App\Http\Controllers\Api\CashbookListController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CostCentreController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DevelopersGuideController;
use App\Http\Controllers\Api\FundTypeController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PayeeRegistrationController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PtjCodeController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UtilityRegistrationController;
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
    // payee-type driven). See the respective controllers for full details. The
    // "Account Bank Updated" page (MENUID 2078) was intentionally skipped per
    // scope decision.
    Route::get('/account-payable/payee-registration/options', [PayeeRegistrationController::class, 'options']);
    Route::get('/account-payable/payee-registration', [PayeeRegistrationController::class, 'index']);

    Route::get('/account-payable/utility-registration', [UtilityRegistrationController::class, 'index']);
    Route::get('/account-payable/utility-registration/{id}', [UtilityRegistrationController::class, 'show'])->whereNumber('id');
    Route::post('/account-payable/utility-registration', [UtilityRegistrationController::class, 'store']);
    Route::put('/account-payable/utility-registration/{id}', [UtilityRegistrationController::class, 'update'])->whereNumber('id');

    Route::get('/account-payable/account-bank-by-payee/options', [AccountBankByPayeeController::class, 'options']);
    Route::get('/account-payable/account-bank-by-payee', [AccountBankByPayeeController::class, 'index']);

    Route::get('/setup/cascade-structure/options', [CascadeStructureController::class, 'options']);
    Route::get('/setup/cascade-structure', [CascadeStructureController::class, 'index']);
    Route::get('/setup/cascade-structure/{id}', [CascadeStructureController::class, 'show']);
    Route::post('/setup/cascade-structure', [CascadeStructureController::class, 'store']);
    Route::put('/setup/cascade-structure/{id}', [CascadeStructureController::class, 'update']);

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
