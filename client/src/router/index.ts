import { createRouter, createWebHistory } from "vue-router";
import type { RouteLocationGeneric, RouteRecordRaw } from "vue-router";

import DashboardView from "@/views/DashboardView.vue";
import MainDashboardView from "@/views/MainDashboardView.vue";
import KitchenChartsView from "@/views/KitchenChartsView.vue";
import KitchenFormsView from "@/views/KitchenFormsView.vue";
import LoginView from "@/views/LoginView.vue";
import MediaLibraryView from "@/views/MediaLibraryView.vue";
import KitchenSinkView from "@/views/KitchenSinkView.vue";
import KitchenSinkPatternsView from "@/views/KitchenSinkPatternsView.vue";
import PageEditorView from "@/views/PageEditorView.vue";
import PagesListView from "@/views/PagesListView.vue";
import PostEditorView from "@/views/PostEditorView.vue";
import PostsListView from "@/views/PostsListView.vue";
import CategoriesListView from "@/views/CategoriesListView.vue";
import CategoryEditorView from "@/views/CategoryEditorView.vue";
import DatabaseSchemaView from "@/views/DatabaseSchemaView.vue";
import DevelopersGuideView from "@/views/DevelopersGuideView.vue";
import ApiManagementView from "@/views/ApiManagementView.vue";
import MenusView from "@/views/MenusView.vue";
import StorefrontMenuView from "@/views/StorefrontMenuView.vue";
import WebfrontSettingsView from "@/views/WebfrontSettingsView.vue";
import AuditLogsView from "@/views/AuditLogsView.vue";
import QueueMonitorView from "@/views/QueueMonitorView.vue";
import ComingSoonView from "@/views/ComingSoonView.vue";
import RolesView from "@/views/RolesView.vue";
import SettingsView from "@/views/SettingsView.vue";
import SystemInfoView from "@/views/SystemInfoView.vue";
import UsersView from "@/views/UsersView.vue";
import UserEditView from "@/views/UserEditView.vue";
import FundTypeView from "@/views/FundTypeView.vue";
import ActivityCodeView from "@/views/ActivityCodeView.vue";
import AccountCodeView from "@/views/AccountCodeView.vue";
import PtjCodeView from "@/views/PtjCodeView.vue";
import CostCentreView from "@/views/CostCentreView.vue";
import CascadeStructureView from "@/views/CascadeStructureView.vue";
import AccountCodePpiView from "@/views/AccountCodePpiView.vue";
import BudgetMovementView from "@/views/BudgetMovementView.vue";
import BudgetMonitoringView from "@/views/BudgetMonitoringView.vue";
import BudgetInitialView from "@/views/BudgetInitialView.vue";
import BudgetClosingView from "@/views/BudgetClosingView.vue";
import BankSetupView from "@/views/BankSetupView.vue";
import BankMasterView from "@/views/BankMasterView.vue";
import BankAccountView from "@/views/BankAccountView.vue";
import CashbookListView from "@/views/CashbookListView.vue";
import PayeeRegistrationView from "@/views/PayeeRegistrationView.vue";
import UtilityRegistrationView from "@/views/UtilityRegistrationView.vue";
import AccountBankByPayeeView from "@/views/AccountBankByPayeeView.vue";
import AccountBankUpdatedView from "@/views/AccountBankUpdatedView.vue";
import DebtorView from "@/views/DebtorView.vue";
import CashbookPtjView from "@/views/CashbookPtjView.vue";
import CreditNoteView from "@/views/CreditNoteView.vue";
import CreditNoteFormView from "@/views/CreditNoteFormView.vue";
import DebitNoteView from "@/views/DebitNoteView.vue";
import DebitNoteFormView from "@/views/DebitNoteFormView.vue";
import DiscountNoteView from "@/views/DiscountNoteView.vue";
import DiscountNoteFormView from "@/views/DiscountNoteFormView.vue";
import AuthorizedReceiptingView from "@/views/AuthorizedReceiptingView.vue";
import AuthorizedReceiptingFormView from "@/views/AuthorizedReceiptingFormView.vue";
import DepositView from "@/views/DepositView.vue";
import ListOfDepositView from "@/views/ListOfDepositView.vue";
import InvoiceBalanceView from "@/views/InvoiceBalanceView.vue";
import DepositFormView from "@/views/DepositFormView.vue";
import DebtorProfileUpdateView from "@/views/DebtorProfileUpdateView.vue";
import TenderQuotationView from "@/views/TenderQuotationView.vue";
import VendorRegistrationFeeHistoryView from "@/views/VendorRegistrationFeeHistoryView.vue";
import DebtorReminderView from "@/views/DebtorReminderView.vue";
import DebtorStatementView from "@/views/DebtorStatementView.vue";
import LetterPhraseView from "@/views/LetterPhraseView.vue";
import PettyCashApplicationListView from "@/views/PettyCashApplicationListView.vue";
import PettyCashClaimFormView from "@/views/PettyCashClaimFormView.vue";
import PettyCashBillView from "@/views/PettyCashBillView.vue";
import PettyCashByPtjView from "@/views/PettyCashByPtjView.vue";
import PettyCashConfirmPaymentView from "@/views/PettyCashConfirmPaymentView.vue";
import PettyCashRecoupFormView from "@/views/PettyCashRecoupFormView.vue";
import PettyCashRecoupView from "@/views/PettyCashRecoupView.vue";
import PettyCashReleasePaidView from "@/views/PettyCashReleasePaidView.vue";
import PettyCashRequestListView from "@/views/PettyCashRequestListView.vue";
import PettyCashVoucherListView from "@/views/PettyCashVoucherListView.vue";
import VcTncView from "@/views/VcTncView.vue";
import CheckErrorView from "@/views/CheckErrorView.vue";
import BudgetStructureSearchView from "@/views/BudgetStructureSearchView.vue";
import StorefrontHomeView from "@/views/StorefrontHomeView.vue";
import StorefrontPageView from "@/views/StorefrontPageView.vue";
import { useAuthStore } from "@/stores/auth";
import { useSiteStore } from "@/stores/site";

const legacyAdminPaths = [
  "/login",
  "/portal/dashboard",
  "/posts",
  "/posts/new",
  "/posts/:id",
  "/categories",
  "/categories/new",
  "/categories/:id",
  "/pages",
  "/pages/new",
  "/pages/:id",
  "/media",
  "/menus",
  "/webfront-menu",
  "/webfront-settings",
  "/storefront-menu",
  "/kitchen-sink",
  "/kitchen-sink/forms",
  "/kitchen-sink/charts",
  "/kitchen-sink/patterns",
  "/development/database-schema",
  "/development/api-management",
  "/profile",
  "/settings",
  "/settings/users",
  "/settings/users/new",
  "/settings/users/:id",
  "/settings/roles",
  "/settings/audit-logs",
  "/settings/queue-monitor",
  "/settings/system",
];

// Backward-compat redirects: old /admin/settings/* → new /admin/platform/* paths
const settingsRedirects: RouteRecordRaw[] = [
  { path: "/admin/settings/users", redirect: "/admin/platform/identity/users" },
  { path: "/admin/settings/users/new", redirect: "/admin/platform/identity/users/new" },
  { path: "/admin/settings/users/:id", redirect: (to: RouteLocationGeneric) => `/admin/platform/identity/users/${String(to.params.id ?? "")}` },
  { path: "/admin/settings/roles", redirect: "/admin/platform/identity/roles" },
  { path: "/admin/settings/audit-logs", redirect: "/admin/platform/observability/audit-trail" },
  { path: "/admin/settings/queue-monitor", redirect: "/admin/platform/queue" },
];

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/admin/login", name: "login", component: LoginView, meta: { guestOnly: true, title: "Login" } },
    { path: "/admin", name: "main-dashboard", component: MainDashboardView, meta: { requiresAuth: true, title: "Main Dashboard" } },
    { path: "/admin/portal/dashboard", name: "dashboard", component: DashboardView, meta: { requiresAuth: true, title: "Dashboard" } },
    { path: "/admin/posts", name: "posts", component: PostsListView, meta: { requiresAuth: true, title: "Posts" } },
    { path: "/admin/posts/new", name: "post-create", component: PostEditorView, meta: { requiresAuth: true, title: "New Post" } },
    { path: "/admin/posts/:id", name: "post-edit", component: PostEditorView, meta: { requiresAuth: true, title: "Edit Post" } },
    { path: "/admin/categories", name: "categories", component: CategoriesListView, meta: { requiresAuth: true, title: "Categories" } },
    { path: "/admin/categories/new", name: "category-create", component: CategoryEditorView, meta: { requiresAuth: true, title: "New Category" } },
    { path: "/admin/categories/:id", name: "category-edit", component: CategoryEditorView, meta: { requiresAuth: true, title: "Edit Category" } },
    { path: "/admin/pages", name: "pages", component: PagesListView, meta: { requiresAuth: true, title: "Pages" } },
    { path: "/admin/pages/new", name: "page-create", component: PageEditorView, meta: { requiresAuth: true, title: "New Page" } },
    { path: "/admin/pages/:id", name: "page-edit", component: PageEditorView, meta: { requiresAuth: true, title: "Edit Page" } },
    { path: "/admin/media", name: "media", component: MediaLibraryView, meta: { requiresAuth: true, title: "Media" } },
    { path: "/admin/webfront-menu", name: "storefront-menu", component: StorefrontMenuView, meta: { requiresAuth: true, title: "Menus" } },
    { path: "/admin/storefront-menu", redirect: "/admin/webfront-menu" },
    { path: "/admin/webfront-settings", name: "webfront-settings", component: WebfrontSettingsView, meta: { requiresAuth: true, title: "Settings" } },
    { path: "/admin/menus", name: "menus", component: MenusView, meta: { requiresAuth: true, title: "Menus" } },
    { path: "/admin/kerisi/m/1551", name: "kerisi-fund-type", component: FundTypeView, meta: { requiresAuth: true, title: "Fund Type" } },
    { path: "/admin/kerisi/m/1552", name: "kerisi-account-code", component: AccountCodeView, meta: { requiresAuth: true, title: "Account Code" } },
    { path: "/admin/kerisi/m/1566", name: "kerisi-activity-code", component: ActivityCodeView, meta: { requiresAuth: true, title: "Activity Code" } },
    { path: "/admin/kerisi/m/1887", name: "kerisi-cost-centre", component: CostCentreView, meta: { requiresAuth: true, title: "Cost Centre" } },
    { path: "/admin/kerisi/m/2295", name: "kerisi-ptj-code", component: PtjCodeView, meta: { requiresAuth: true, title: "PTJ Code" } },
    { path: "/admin/kerisi/m/1546", name: "kerisi-cascade-structure", component: CascadeStructureView, meta: { requiresAuth: true, title: "Cascade Structure" } },
    // "List of ..." listing menus (FLC_SETUP&MAINTAINANCE) — reuse the setup-screen components.
    { path: "/admin/kerisi/m/2330", name: "kerisi-list-fund-type", component: FundTypeView, meta: { requiresAuth: true, title: "List of Fund Type" } },
    { path: "/admin/kerisi/m/1874", name: "kerisi-list-activity-code", component: ActivityCodeView, meta: { requiresAuth: true, title: "List of Activity Code" } },
    { path: "/admin/kerisi/m/1886", name: "kerisi-list-ptj-code", component: PtjCodeView, meta: { requiresAuth: true, title: "List of PTJ Code" } },
    { path: "/admin/kerisi/m/2360", name: "kerisi-list-cost-centre", component: CostCentreView, meta: { requiresAuth: true, title: "List of Cost Centre" } },
    { path: "/admin/kerisi/m/2167", name: "kerisi-list-cascade-structure", component: CascadeStructureView, meta: { requiresAuth: true, title: "List of Cascade Structure" } },
    { path: "/admin/kerisi/m/3453", name: "kerisi-list-account-code-ppi", component: AccountCodePpiView, meta: { requiresAuth: true, title: "List of Account Code (PPI)" } },
    // FIMS Budget — Increment / Decrement / Virement list pages. Editor pages
    // (menuID 1557/1558/1559) are not migrated yet; the row actions are
    // rendered for visual parity but are no-ops.
    { path: "/admin/kerisi/m/1554", name: "kerisi-budget-increment", component: BudgetMovementView, props: { type: "increment" }, meta: { requiresAuth: true, title: "Budget Increment" } },
    { path: "/admin/kerisi/m/1555", name: "kerisi-budget-decrement", component: BudgetMovementView, props: { type: "decrement" }, meta: { requiresAuth: true, title: "Budget Decrement" } },
    { path: "/admin/kerisi/m/1556", name: "kerisi-budget-virement", component: BudgetMovementView, props: { type: "virement" }, meta: { requiresAuth: true, title: "Budget Virement" } },
    // FIMS Budget — Monitoring (PAGEID 1201 / MENUID 1471), Initial V2
    // (PAGEID 1264 / MENUID 1541), and Closing (PAGEID 1953 / MENUID 2389
    // primary + 3154 alias) pages.
    { path: "/admin/kerisi/m/1471", name: "kerisi-budget-monitoring", component: BudgetMonitoringView, meta: { requiresAuth: true, title: "Budget Monitoring" } },
    { path: "/admin/kerisi/m/1541", name: "kerisi-budget-initial", component: BudgetInitialView, meta: { requiresAuth: true, title: "Budget Initial" } },
    { path: "/admin/kerisi/m/2389", name: "kerisi-budget-closing", component: BudgetClosingView, meta: { requiresAuth: true, title: "Budget Closing" } },
    { path: "/admin/kerisi/m/3154", name: "kerisi-budget-closing-alias", component: BudgetClosingView, meta: { requiresAuth: true, title: "Budget Closing" } },
    // FIMS Cashbook — Bank Setup (PAGEID 2680), Bank Master (PAGEID 1682),
    // Bank Account (PAGEID 1736), List of Cashbook Daily (PAGEID 1397) and
    // Monthly (PAGEID 2024). Daily/Monthly reuse CashbookListView via prop.
    { path: "/admin/kerisi/m/3246", name: "kerisi-bank-setup", component: BankSetupView, meta: { requiresAuth: true, title: "Bank Setup" } },
    { path: "/admin/kerisi/m/2036", name: "kerisi-bank-master", component: BankMasterView, meta: { requiresAuth: true, title: "Bank Master" } },
    { path: "/admin/kerisi/m/2097", name: "kerisi-bank-account", component: BankAccountView, meta: { requiresAuth: true, title: "Bank Account" } },
    { path: "/admin/kerisi/m/1702", name: "kerisi-cashbook-daily", component: CashbookListView, props: { type: "DAILY" }, meta: { requiresAuth: true, title: "List Of CashBook (Daily)" } },
    { path: "/admin/kerisi/m/2471", name: "kerisi-cashbook-monthly", component: CashbookListView, props: { type: "MONTHLY" }, meta: { requiresAuth: true, title: "List Of Cashbook (Monthly)" } },
    // FIMS Account Payable — Payee Registration (MENUID 1711), Utility
    // Registration (MENUID 3466), Account Bank by Payee (MENUID 2751),
    // Account Bank Updated (MENUID 2078).
    { path: "/admin/kerisi/m/1711", name: "kerisi-ap-payee-registration", component: PayeeRegistrationView, meta: { requiresAuth: true, title: "Payee Registration" } },
    { path: "/admin/kerisi/m/3466", name: "kerisi-ap-utility-registration", component: UtilityRegistrationView, meta: { requiresAuth: true, title: "Utility Registration" } },
    { path: "/admin/kerisi/m/2751", name: "kerisi-ap-account-bank-by-payee", component: AccountBankByPayeeView, meta: { requiresAuth: true, title: "Account Bank By Payee" } },
    { path: "/admin/kerisi/m/2078", name: "kerisi-ap-account-bank-updated", component: AccountBankUpdatedView, meta: { requiresAuth: true, title: "Account Bank Updated" } },
    { path: "/admin/kerisi/m/1727", name: "kerisi-ar-debtor", component: DebtorView, meta: { requiresAuth: true, title: "Debtor" } },
    { path: "/admin/kerisi/m/1049", name: "kerisi-ar-cashbook-ptj", component: CashbookPtjView, meta: { requiresAuth: true, title: "Cashbook PTJ" } },
    { path: "/admin/kerisi/m/1041", name: "kerisi-ar-credit-note", component: CreditNoteView, meta: { requiresAuth: true, title: "Credit Note" } },
    { path: "/admin/kerisi/m/1782", name: "kerisi-ar-credit-note-form", component: CreditNoteFormView, meta: { requiresAuth: true, title: "Credit Note Form" } },
    { path: "/admin/kerisi/m/1042", name: "kerisi-ar-debit-note", component: DebitNoteView, meta: { requiresAuth: true, title: "Debit Note" } },
    { path: "/admin/kerisi/m/1783", name: "kerisi-ar-debit-note-form", component: DebitNoteFormView, meta: { requiresAuth: true, title: "Debit Note Form" } },
    { path: "/admin/kerisi/m/1043", name: "kerisi-ar-discount-note", component: DiscountNoteView, meta: { requiresAuth: true, title: "Discount Note" } },
    // Student Finance list aliases — same backend tables/columns as the admin
    // AR listings above; the legacy `DT_CREDIT_NOTE_LIST` / `DT_DEBIT_NOTE_LIST`
    // / `DT_DISCOUNT_NOTE_LIST` BL files are not present in the available
    // source JSON, so they are assumed to be alternate menu placements of the
    // same `DT_AR_*_LIST` listings already wired for MENUID 1041/1042/1043.
    { path: "/admin/kerisi/m/1529", name: "kerisi-sf-credit-note", component: CreditNoteView, meta: { requiresAuth: true, title: "Credit Note" } },
    { path: "/admin/kerisi/m/1575", name: "kerisi-sf-debit-note", component: DebitNoteView, meta: { requiresAuth: true, title: "Debit Note" } },
    { path: "/admin/kerisi/m/1570", name: "kerisi-sf-discount-note", component: DiscountNoteView, meta: { requiresAuth: true, title: "Discount Note" } },
    { path: "/admin/kerisi/m/1784", name: "kerisi-ar-discount-note-form", component: DiscountNoteFormView, meta: { requiresAuth: true, title: "Discount Note Form" } },
    { path: "/admin/kerisi/m/1952", name: "kerisi-ar-authorized-receipting", component: AuthorizedReceiptingView, meta: { requiresAuth: true, title: "Authorized Receipting" } },
    { path: "/admin/kerisi/m/1953", name: "kerisi-ar-authorized-receipting-form", component: AuthorizedReceiptingFormView, meta: { requiresAuth: true, title: "Authorized Receipting Form" } },
    // FIMS Credit Control — MENUID 1809 / 3066 / 3388 / 3397 (legacy PAGEIDs
    // 1445, 2159, 2561, 2688). Backed by DepositController /
    // ListOfDepositController / InvoiceBalanceController / DepositFormController.
    { path: "/admin/kerisi/m/1809", name: "kerisi-cc-deposit", component: DepositView, meta: { requiresAuth: true, title: "Deposit" } },
    { path: "/admin/kerisi/m/3066", name: "kerisi-cc-list-of-deposit", component: ListOfDepositView, meta: { requiresAuth: true, title: "List of Deposit" } },
    { path: "/admin/kerisi/m/3388", name: "kerisi-cc-invoice-balance", component: InvoiceBalanceView, meta: { requiresAuth: true, title: "Invoice Balance" } },
    { path: "/admin/kerisi/m/3397", name: "kerisi-cc-deposit-form", component: DepositFormView, meta: { requiresAuth: true, title: "Detail of Deposit" } },
    // Portal (debtor/vendor) read-only listings
    { path: "/admin/kerisi/m/2608", name: "kerisi-portal-debtor-profile-updates", component: DebtorProfileUpdateView, meta: { requiresAuth: true, title: "List of Profile Update Application" } },
    { path: "/admin/kerisi/m/2767", name: "kerisi-portal-tender-list", component: TenderQuotationView, meta: { requiresAuth: true, title: "Tender/Quotation List" } },
    { path: "/admin/kerisi/m/2003", name: "kerisi-portal-registration-fees", component: VendorRegistrationFeeHistoryView, meta: { requiresAuth: true, title: "Online Registration Fee History" } },
    { path: "/admin/kerisi/m/2584", name: "kerisi-portal-debtor-reminder", component: DebtorReminderView, meta: { requiresAuth: true, title: "Reminder" } },
    { path: "/admin/kerisi/m/2267", name: "kerisi-portal-debtor-statement", component: DebtorStatementView, meta: { requiresAuth: true, title: "Debtors Statement" } },
    // FIMS setup & maintenance pages migrated from legacy PAGE_SETUP_MAINTENANCE
    // (level 2) — MENUID maps to legacy MENUID and keeps URL parity with the
    // generic `/admin/kerisi/m/:menuId` pattern used by the sidebar.
    { path: "/admin/kerisi/m/3506", name: "kerisi-letter-phrase", component: LetterPhraseView, meta: { requiresAuth: true, title: "Letter Phrase" } },
    {
      path: "/admin/kerisi/m/1532",
      name: "kerisi-petty-cash-recoup-list",
      component: PettyCashRecoupView,
      meta: { requiresAuth: true, title: "Petty Cash Recoup List" },
    },
    {
      path: "/admin/kerisi/m/1534",
      name: "kerisi-petty-cash-recoup-form",
      component: PettyCashRecoupFormView,
      meta: { requiresAuth: true, title: "Petty Cash Recoup Form" },
    },
    {
      path: "/admin/kerisi/m/1490",
      name: "kerisi-petty-cash-application-list",
      component: PettyCashApplicationListView,
      meta: { requiresAuth: true, title: "List of Petty Cash Application" },
    },
    {
      path: "/admin/kerisi/m/1872",
      name: "kerisi-petty-cash-claim-form",
      component: PettyCashClaimFormView,
      meta: { requiresAuth: true, title: "Petty Cash Claim Form" },
    },
    {
      path: "/admin/kerisi/m/2399",
      name: "kerisi-petty-cash-by-ptj",
      component: PettyCashByPtjView,
      meta: { requiresAuth: true, title: "List Petty Cash by PTJ" },
    },
    {
      path: "/admin/kerisi/m/2400",
      name: "kerisi-petty-cash-bill",
      component: PettyCashBillView,
      meta: { requiresAuth: true, title: "Bill Petty Cash" },
    },
    {
      path: "/admin/kerisi/m/2424",
      name: "kerisi-petty-cash-confirm-payment",
      component: PettyCashConfirmPaymentView,
      meta: { requiresAuth: true, title: "Confirmation Payment" },
    },
    {
      path: "/admin/kerisi/m/2456",
      name: "kerisi-petty-cash-request-list",
      component: PettyCashRequestListView,
      meta: { requiresAuth: true, title: "Request Petty Cash" },
    },
    {
      path: "/admin/kerisi/m/2761",
      name: "kerisi-petty-cash-release-paid",
      component: PettyCashReleasePaidView,
      meta: { requiresAuth: true, title: "List of Release Paid" },
    },
    {
      path: "/admin/kerisi/m/3344",
      name: "kerisi-petty-cash-voucher-list",
      component: PettyCashVoucherListView,
      meta: { requiresAuth: true, title: "List of Voucher Petty Cash" },
    },
    { path: "/admin/kerisi/m/2073", name: "kerisi-vc-tnc", component: VcTncView, meta: { requiresAuth: true, title: "HOD, VC & TNC" } },
    { path: "/admin/kerisi/m/2740", name: "kerisi-check-error", component: CheckErrorView, meta: { requiresAuth: true, title: "Cek yang mungkin error" } },
    { path: "/admin/kerisi/m/3224", name: "kerisi-budget-structure-search", component: BudgetStructureSearchView, meta: { requiresAuth: true, title: "Setup Carian Structure Budget" } },
    { path: "/admin/kerisi/m/:menuId", name: "kerisi-menu", component: ComingSoonView, meta: { requiresAuth: true, title: "KERISI" } },
    { path: "/admin/kitchen-sink", name: "kitchen-sink", component: KitchenSinkView, meta: { requiresAuth: true, title: "Kitchen Sink" } },
    { path: "/admin/kitchen-sink/forms", name: "kitchen-forms", component: KitchenFormsView, meta: { requiresAuth: true, title: "Forms" } },
    { path: "/admin/kitchen-sink/charts", name: "kitchen-charts", component: KitchenChartsView, meta: { requiresAuth: true, title: "Charts" } },
    {
      path: "/admin/kitchen-sink/patterns",
      name: "kitchen-patterns",
      component: KitchenSinkPatternsView,
      meta: { requiresAuth: true, title: "Kitchen Sink Patterns" },
    },
    { path: "/admin/development/developers-guide", name: "developers-guide", component: DevelopersGuideView, meta: { requiresAuth: true, title: "Developers Guide" } },
    { path: "/admin/development/database-schema", name: "database-schema", component: DatabaseSchemaView, meta: { requiresAuth: true, title: "Database Schema" } },
    { path: "/admin/development/api-explorer", name: "api-explorer", component: ApiManagementView, meta: { requiresAuth: true, title: "API Explorer" } },
    { path: "/admin/development/api-management", redirect: "/admin/development/api-explorer" },
    {
      path: "/admin/profile",
      name: "profile",
      meta: { requiresAuth: true },
      beforeEnter: async () => {
        const auth = useAuthStore();
        await auth.initialize();
        if (auth.user?.id) return `/admin/platform/identity/users/${auth.user.id}`;
        return { name: "login" };
      },
      component: { template: "" },
    },

    // ── Administration ──
    { path: "/admin/settings", name: "settings", component: SettingsView, meta: { requiresAuth: true, title: "Settings" } },
    { path: "/admin/settings/system", name: "settings-system", component: SystemInfoView, meta: { requiresAuth: true, title: "System Info" } },

    // ── Core Platform: Identity & Access ──
    { path: "/admin/platform/identity", redirect: "/admin/platform/identity/users" },
    { path: "/admin/platform/identity/users", name: "platform-users", component: UsersView, meta: { requiresAuth: true, title: "Users" } },
    { path: "/admin/platform/identity/users/new", name: "platform-user-create", component: UserEditView, meta: { requiresAuth: true, title: "New User" } },
    { path: "/admin/platform/identity/users/:id", name: "platform-user-edit", component: UserEditView, meta: { requiresAuth: true, title: "Edit User" } },
    { path: "/admin/platform/identity/roles", name: "platform-rbac", component: RolesView, meta: { requiresAuth: true, title: "RBAC" } },
    { path: "/admin/platform/identity/tokens", name: "platform-tokens", component: ComingSoonView, meta: { requiresAuth: true, title: "Token Management" } },

    // ── Core Platform: Observability (Grafana) ──
    { path: "/admin/platform/observability", redirect: "/admin/platform/observability/audit-trail" },
    { path: "/admin/platform/observability/audit-trail", name: "platform-audit-trail", component: AuditLogsView, meta: { requiresAuth: true, title: "Audit Trail" } },
    { path: "/admin/platform/observability/activity-log", name: "platform-activity-log", component: ComingSoonView, meta: { requiresAuth: true, title: "Activity Log" } },
    { path: "/admin/platform/observability/logging", name: "platform-logging", component: ComingSoonView, meta: { requiresAuth: true, title: "Logging" } },
    { path: "/admin/platform/observability/errors", name: "platform-error-tracking", component: ComingSoonView, meta: { requiresAuth: true, title: "Error Tracking" } },
    { path: "/admin/platform/observability/monitoring", name: "platform-monitoring", component: ComingSoonView, meta: { requiresAuth: true, title: "Monitoring" } },

    // ── Core Platform: Queue (Laravel Queue) ──
    { path: "/admin/platform/queue", name: "platform-queue", component: QueueMonitorView, meta: { requiresAuth: true, title: "Queue" } },
    { path: "/admin/platform/queue/failed", name: "platform-queue-failed", component: ComingSoonView, meta: { requiresAuth: true, title: "Failed Jobs" } },
    { path: "/admin/platform/queue/scheduled", name: "platform-queue-scheduled", component: ComingSoonView, meta: { requiresAuth: true, title: "Scheduled Jobs" } },

    // ── Core Platform: Messaging ──
    { path: "/admin/platform/messaging", redirect: "/admin/platform/messaging/event-bus" },
    { path: "/admin/platform/messaging/event-bus", name: "platform-event-bus", component: ComingSoonView, meta: { requiresAuth: true, title: "Event Bus" } },
    { path: "/admin/platform/messaging/notifications", name: "platform-notifications", component: ComingSoonView, meta: { requiresAuth: true, title: "Notifications" } },

    // ── Backward-compat redirects from old governance/communication paths ──
    { path: "/admin/platform/governance", redirect: "/admin/platform/observability/audit-trail" },
    { path: "/admin/platform/governance/audit-trail", redirect: "/admin/platform/observability/audit-trail" },
    { path: "/admin/platform/governance/activity-log", redirect: "/admin/platform/observability/activity-log" },
    { path: "/admin/platform/communication", redirect: "/admin/platform/messaging/notifications" },
    { path: "/admin/platform/communication/notifications", redirect: "/admin/platform/messaging/notifications" },
    { path: "/admin/platform/messaging/queue", redirect: "/admin/platform/queue" },
    { path: "/admin/platform/messaging/queue/failed", redirect: "/admin/platform/queue/failed" },
    { path: "/admin/platform/messaging/queue/scheduled", redirect: "/admin/platform/queue/scheduled" },

    // ── Core Platform: System Management ──
    { path: "/admin/platform/system", redirect: "/admin/platform/system/configuration" },
    { path: "/admin/platform/system/configuration", name: "platform-config", component: ComingSoonView, meta: { requiresAuth: true, title: "Configuration" } },
    { path: "/admin/platform/system/feature-flags", name: "platform-feature-flags", component: ComingSoonView, meta: { requiresAuth: true, title: "Feature Flags" } },
    { path: "/admin/platform/system/scheduler", name: "platform-scheduler", component: ComingSoonView, meta: { requiresAuth: true, title: "Scheduler" } },

    // ── Core Platform: Storage ──
    { path: "/admin/platform/storage", redirect: "/admin/platform/storage/media" },
    { path: "/admin/platform/storage/media", name: "platform-file-media", component: ComingSoonView, meta: { requiresAuth: true, title: "File / Media Management" } },

    // ── Core Platform: API Gateway (APISIX) ──
    { path: "/admin/platform/gateway", redirect: "/admin/platform/gateway/routes" },
    { path: "/admin/platform/gateway/routes", name: "platform-gateway-routes", component: ComingSoonView, meta: { requiresAuth: true, title: "Routes" } },
    { path: "/admin/platform/gateway/upstreams", name: "platform-gateway-upstreams", component: ComingSoonView, meta: { requiresAuth: true, title: "Upstreams" } },
    { path: "/admin/platform/gateway/consumers", name: "platform-gateway-consumers", component: ComingSoonView, meta: { requiresAuth: true, title: "Consumers" } },
    { path: "/admin/platform/gateway/plugins", name: "platform-gateway-plugins", component: ComingSoonView, meta: { requiresAuth: true, title: "Plugins" } },
    { path: "/admin/platform/gateway/ssl", name: "platform-gateway-ssl", component: ComingSoonView, meta: { requiresAuth: true, title: "SSL Certificates" } },
    { path: "/admin/platform/gateway/webhooks", name: "platform-webhooks", component: ComingSoonView, meta: { requiresAuth: true, title: "Webhooks" } },

    // ── Backward-compat redirects from old integration paths ──
    { path: "/admin/platform/integration", redirect: "/admin/platform/gateway/routes" },
    { path: "/admin/platform/integration/api", redirect: "/admin/platform/gateway/routes" },
    { path: "/admin/platform/integration/webhooks", redirect: "/admin/platform/gateway/webhooks" },

    // ── Core Platform: AI Integration ──
    { path: "/admin/platform/ai", redirect: "/admin/platform/ai/providers" },
    { path: "/admin/platform/ai/providers", name: "platform-ai-providers", component: ComingSoonView, meta: { requiresAuth: true, title: "AI Providers" } },
    { path: "/admin/platform/ai/models", name: "platform-ai-models", component: ComingSoonView, meta: { requiresAuth: true, title: "AI Models" } },
    { path: "/admin/platform/ai/prompts", name: "platform-ai-prompts", component: ComingSoonView, meta: { requiresAuth: true, title: "Prompt Templates" } },
    { path: "/admin/platform/ai/usage", name: "platform-ai-usage", component: ComingSoonView, meta: { requiresAuth: true, title: "AI Usage & Billing" } },

    // ── Backward-compat redirects from old settings paths ──
    ...settingsRedirects,

    ...legacyAdminPaths.map<RouteRecordRaw>((path) => ({
      path,
      redirect: (to: RouteLocationGeneric) => `/admin${to.fullPath}`,
    })),

    { path: "/", name: "storefront-home", component: StorefrontHomeView, meta: { title: "Webfront" } },
    { path: "/:slug", name: "storefront-page", component: StorefrontPageView, meta: { title: "Webfront" } },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  await auth.initialize();

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: "login" };
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: "main-dashboard" };
  }

  return true;
});

router.afterEach((to) => {
  const site = useSiteStore();
  const pageTitle = (to.meta.title as string) || "Admin";
  site.setDocumentTitle(pageTitle);
});

export default router;
