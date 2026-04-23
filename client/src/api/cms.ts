import { apiRequest } from "./client";
import type {
  AccountBankByPayeeGenericRow,
  AccountBankByPayeeInvestmentRow,
  AccountBankByPayeeOptions,
  AccountBankByPayeeSponsorRow,
  AccountBankPayeeType,
  AccountBankUpdatedBillRow,
  AccountBankUpdatedOptions,
  AccountBankUpdatedPayeeType,
  AccountBankUpdatedProcessInput,
  AccountBankUpdatedProcessResult,
  AccountBankUpdatedVoucherRow,
  AuditLog,
  ActivityGroupRow,
  BankAccountDetail,
  BankAccountInput,
  BankAccountOptions,
  BankAccountRow,
  BankAccountUpdateInput,
  BankMasterInput,
  BankMasterOptions,
  BankMasterRow,
  BankSetupInput,
  BankSetupOptions,
  BankSetupRow,
  CashbookListOptions,
  CashbookListRow,
  CashbookListType,
  CashbookPtjRow,
  AuthorizedReceiptingOptions,
  AuthorizedReceiptingRow,
  CreditNoteFormData,
  CreditNoteRow,
  DebitNoteFormData,
  DebitNoteRow,
  InvoiceLinesResponse,
  LookupOption,
  DebtorSearchOption,
  InvoiceSearchOption,
  SaveCreditNoteResponse,
  SaveDebitNoteResponse,
  DebtorOptions,
  DebtorProfileUpdateRow,
  DebtorRow,
  DiscountNoteRow,
  DiscountInvoiceLinesResponse,
  DiscountNoteFormData,
  DiscountPolicyOption,
  SaveDiscountNoteResponse,
  AuthorizedReceiptingFormData,
  SaveAuthorizedReceiptingResponse,
  CurrentStaffProfile,
  ArEventSearchOption,
  ArStaffSearchOption,
  AccountActivityInput,
  AccountActivityRow,
  AccountCodeInput,
  AccountCodeRow,
  AccountCodePpiRow,
  AccountCodePpiOptions,
  ActivitySubgroupRow,
  ActivitySubsiriRow,
  ActivityTypeRow,
  BillsCustomWfInput,
  BillsSetupDetail,
  BillsSetupInput,
  BillsSetupRow,
  BudgetClosingOptions,
  BudgetClosingPayload,
  BudgetInitialOptions,
  BudgetInitialRow,
  BudgetMonitoringOptions,
  BudgetMonitoringRow,
  BudgetMovementOptions,
  BudgetMovementRow,
  BudgetMovementType,
  BudgetStructureSearchForms,
  BudgetStructureSearchOptions,
  Category,
  CategoryInput,
  CascadeStructureInput,
  CascadeStructureRow,
  CheckErrorBillMasterRow,
  CheckErrorPayment2PelikRow,
  CheckErrorPaymentPelikRow,
  CheckErrorResitRow,
  CheckErrorUrlBrfHilangRow,
  CheckErrorVoucherDetailRow,
  CheckErrorVoucherMasterRow,
  CcCustomerOption,
  CcOption,
  CostCentreInput,
  CostCentreRow,
  DepositDetailInput,
  DepositDetailRow,
  DepositFormMaster,
  DepositFormMasterInput,
  DepositOptions,
  DepositRow,
  FundTypeInput,
  FundTypeRow,
  InvoiceBalanceOptions,
  InvoiceBalanceRow,
  JenisCarianDetail,
  ListOfDepositOptions,
  JenisCarianInput,
  JenisCarianRow,
  LetterPhraseDetail,
  LetterPhraseInput,
  LetterPhraseRow,
  Media,
  MediaMetadataInput,
  Page,
  PageInput,
  PayeeRegistrationOptions,
  PayeeRegistrationRow,
  PettyCashApplicationDetail,
  PettyCashApplicationListOptions,
  PettyCashApplicationListRow,
  PettyCashBillRow,
  PettyCashByPtjRow,
  PettyCashClaimAccountCodeSuggestion,
  PettyCashClaimDimensionSuggestion,
  PettyCashClaimForm,
  PettyCashClaimPcmSuggestion,
  PettyCashClaimRequestBySuggestion,
  PettyCashClaimSavePayload,
  PettyCashClaimSaveResponse,
  PettyCashConfirmPaymentRow,
  PettyCashRecoupDetail,
  PettyCashRecoupRow,
  PettyCashReleasePaidApplicationRow,
  PettyCashReleasePaidReceiptRow,
  PettyCashRequestListRow,
  PettyCashVoucherListOptions,
  PettyCashVoucherListRow,
  PtjCodeInput,
  PtjCodeRow,
  Post,
  PostInput,
  PublicSiteSettings,
  Role,
  RoleInput,
  SemiStrictInput,
  SettingsPayload,
  StorefrontMenuItem,
  DebtorReminderRow,
  DebtorStatementFooter,
  DebtorStatementRow,
  GlListingOptions,
  GlListingRow,
  GlYearMonthDetail,
  GlYearMonthInput,
  GlYearMonthOptions,
  GlYearMonthRow,
  JournalListingHeader,
  JournalListingLine,
  JournalListingOptions,
  JournalListingRow,
  ManualJournalDetail,
  ManualJournalListingPdfPayload,
  ManualJournalOptions,
  ManualJournalRow,
  PostingToTbHeader,
  PostingToTbLine,
  PostingToTbOptions,
  PostingToTbRow,
  BankAccountUpdateOptions,
  BankAccountUpdateRow,
  LedgerOptions,
  LedgerRow,
  ManualInvoiceFooter,
  ManualInvoiceOptions,
  ManualInvoiceRow,
  PtptnDataDetail,
  PtptnDataHeader,
  PtptnDataRow,
  StatusPoPrOptions,
  StatusPoPrRow,
  TenderQuotationRow,
  UtilityRegistrationDetail,
  UtilityRegistrationInput,
  UtilityRegistrationRow,
  UserDetail,
  UserInput,
  VcTncDetail,
  VcTncOptions,
  VcTncRow,
  VendorRegistrationFeeRow,
  VendorStatusCheck,
} from "@/types";
import type { AdminMenuPrefs } from "@/config/admin-menu";

export async function fetchDashboardSummary() {
  return apiRequest<{ data: { counts: { posts: number; pages: number; media: number }; recent: { posts: Post[]; pages: Page[] } } }>(
    "/api/dashboard/summary",
  );
}

export async function listPosts(params = "") {
  return apiRequest<{ data: Post[]; meta: Record<string, unknown> }>(`/api/posts${params}`);
}

export async function getPost(id: number) {
  return apiRequest<{ data: Post }>(`/api/posts/${id}`);
}

export async function createPost(input: PostInput) {
  return apiRequest<{ data: Post }>("/api/posts", { method: "POST", body: JSON.stringify(input) });
}

export async function updatePost(id: number, input: PostInput) {
  return apiRequest<{ data: Post }>(`/api/posts/${id}`, { method: "PUT", body: JSON.stringify(input) });
}

export async function deletePost(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/posts/${id}`, { method: "DELETE" });
}

// Categories
export async function listCategories(params = "") {
  return apiRequest<{ data: Category[]; meta: Record<string, unknown> }>(`/api/categories${params}`);
}

export async function getCategory(id: number) {
  return apiRequest<{ data: Category }>(`/api/categories/${id}`);
}

export async function createCategory(input: CategoryInput) {
  return apiRequest<{ data: Category }>("/api/categories", { method: "POST", body: JSON.stringify(input) });
}

export async function updateCategory(id: number, input: CategoryInput) {
  return apiRequest<{ data: Category }>(`/api/categories/${id}`, { method: "PUT", body: JSON.stringify(input) });
}

export async function deleteCategory(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/categories/${id}`, { method: "DELETE" });
}

export async function listPages(params = "") {
  return apiRequest<{ data: Page[]; meta: Record<string, unknown> }>(`/api/pages${params}`);
}

export async function getPage(id: number) {
  return apiRequest<{ data: Page }>(`/api/pages/${id}`);
}

export async function createPage(input: PageInput) {
  return apiRequest<{ data: Page }>("/api/pages", { method: "POST", body: JSON.stringify(input) });
}

export async function updatePage(id: number, input: PageInput) {
  return apiRequest<{ data: Page }>(`/api/pages/${id}`, { method: "PUT", body: JSON.stringify(input) });
}

export async function deletePage(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/pages/${id}`, { method: "DELETE" });
}

export async function listMedia() {
  return apiRequest<{ data: Media[] }>("/api/media");
}

export async function uploadMedia(file: File) {
  const formData = new FormData();
  formData.append("file", file);
  return apiRequest<{ data: Media }>("/api/media/upload", { method: "POST", body: formData });
}

export async function removeMedia(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/media/${id}`, { method: "DELETE" });
}

export async function updateMediaMetadata(id: number, input: MediaMetadataInput) {
  return apiRequest<{ data: Media }>(`/api/media/${id}`, { method: "PUT", body: JSON.stringify(input) });
}

export async function getSettings() {
  return apiRequest<{ data: SettingsPayload }>("/api/settings");
}

export async function updateSettings(payload: SettingsPayload) {
  return apiRequest<{ data: SettingsPayload }>("/api/settings", {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function getAdminMenuPrefs() {
  return apiRequest<{ data: AdminMenuPrefs | null }>("/api/settings/admin-menu-prefs");
}

export async function saveAdminMenuPrefs(prefs: AdminMenuPrefs) {
  return apiRequest<{ data: AdminMenuPrefs }>("/api/settings/admin-menu-prefs", {
    method: "PUT",
    body: JSON.stringify(prefs),
  });
}

export async function getStorefrontMenu() {
  return apiRequest<{ data: StorefrontMenuItem[] }>("/api/settings/storefront-menu");
}

export async function saveStorefrontMenu(items: StorefrontMenuItem[]) {
  return apiRequest<{ data: StorefrontMenuItem[] }>("/api/settings/storefront-menu", {
    method: "PUT",
    body: JSON.stringify(items),
  });
}

export async function getPublicSiteSettings() {
  return apiRequest<{ data: PublicSiteSettings }>("/api/public/site");
}

export async function getPublicFrontPage() {
  return apiRequest<{ data: Page; meta?: { source?: string } }>("/api/public/pages/frontpage");
}

export async function getPublicPageBySlug(slug: string) {
  return apiRequest<{ data: Page }>(`/api/public/pages/${encodeURIComponent(slug)}`);
}

// Users
export async function listUsers() {
  return apiRequest<{ data: UserDetail[] }>("/api/users");
}

export async function getUser(id: number) {
  return apiRequest<{ data: UserDetail }>(`/api/users/${id}`);
}

export async function createUser(input: UserInput) {
  return apiRequest<{ data: UserDetail }>("/api/users", { method: "POST", body: JSON.stringify(input) });
}

export async function updateUser(id: number, input: UserInput) {
  return apiRequest<{ data: UserDetail }>(`/api/users/${id}`, { method: "PUT", body: JSON.stringify(input) });
}

export async function deleteUser(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/users/${id}`, { method: "DELETE" });
}

// Roles
export async function listRoles() {
  return apiRequest<{ data: Role[] }>("/api/roles");
}

export async function getRole(id: number) {
  return apiRequest<{ data: Role }>(`/api/roles/${id}`);
}

export async function createRole(input: RoleInput) {
  return apiRequest<{ data: Role }>("/api/roles", { method: "POST", body: JSON.stringify(input) });
}

export async function updateRole(id: number, input: RoleInput) {
  return apiRequest<{ data: Role }>(`/api/roles/${id}`, { method: "PUT", body: JSON.stringify(input) });
}

export async function deleteRole(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/roles/${id}`, { method: "DELETE" });
}

// Audit Logs
export async function listAuditLogs(params = "") {
  return apiRequest<{ data: AuditLog[]; meta: Record<string, unknown> }>(`/api/audit-logs${params}`);
}

// Developers Guide
export async function getDevelopersGuide() {
  return apiRequest<{ data: { content: string; syncFiles: { filename: string; path?: string; exists: boolean; inSync: boolean; readOnly?: boolean; role?: "canonical" | "mirror" }[] } }>("/api/developers-guide");
}

export async function updateDevelopersGuide(content: string) {
  return apiRequest<{ data: { success: boolean; syncFiles: { filename: string; path?: string; exists: boolean; inSync: boolean; readOnly?: boolean; role?: "canonical" | "mirror" }[] } }>("/api/developers-guide", {
    method: "PUT",
    body: JSON.stringify({ content }),
  });
}

export async function listFundTypes(params = "") {
  return apiRequest<{ data: FundTypeRow[]; meta: Record<string, unknown> }>(`/api/fund-types${params}`);
}

export async function getFundType(id: number) {
  return apiRequest<{ data: { id: number; ftyFundType: string; ftyFundDesc: string; ftyFundDescEng: string | null; ftyBasis: string; ftyStatus: number; ftyRemark: string | null } }>(`/api/fund-types/${id}`);
}

export async function createFundType(input: FundTypeInput) {
  return apiRequest<{ data: { id: number } }>("/api/fund-types", { method: "POST", body: JSON.stringify(input) });
}

export async function updateFundType(id: number, input: FundTypeInput) {
  return apiRequest<{ data: { success: boolean } }>(`/api/fund-types/${id}`, { method: "PUT", body: JSON.stringify(input) });
}

export async function getFundTypeOptions() {
  return apiRequest<{
    data: {
      smartFilter: {
        fundType: { id: string; label: string }[];
        basis: { id: string; label: string }[];
        status: { id: string; label: string }[];
      };
      popupModal: {
        basis: { id: string; label: string }[];
        status: { id: number; label: string }[];
      };
    };
  }>("/api/fund-types/options");
}

export async function listActivityCodeLevel(params = "") {
  return apiRequest<{ data: ActivityGroupRow[] | ActivitySubgroupRow[] | ActivitySubsiriRow[] | ActivityTypeRow[] }>(
    `/api/setup/activity-code${params}`,
  );
}

export async function createActivityGroup(input: { activityGroupCode: string; activityGroupDesc: string }) {
  return apiRequest<{ data: { success: boolean } }>("/api/setup/activity-code/group", { method: "POST", body: JSON.stringify(input) });
}

export async function updateActivityGroup(code: string, input: { activityGroupDesc: string }) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/activity-code/group/${encodeURIComponent(code)}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deleteActivityGroup(code: string) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/activity-code/group/${encodeURIComponent(code)}`, { method: "DELETE" });
}

export async function createActivitySubgroup(input: { activityGroupCode: string; activitySubgroupCode: string; activitySubgroupDesc: string }) {
  return apiRequest<{ data: { success: boolean } }>("/api/setup/activity-code/subgroup", { method: "POST", body: JSON.stringify(input) });
}

export async function updateActivitySubgroup(code: string, input: { activityGroupCode: string; activitySubgroupDesc: string }) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/activity-code/subgroup/${encodeURIComponent(code)}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deleteActivitySubgroup(code: string, activityGroupCode: string) {
  const params = new URLSearchParams({ activityGroupCode });
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/activity-code/subgroup/${encodeURIComponent(code)}?${params.toString()}`, { method: "DELETE" });
}

export async function createActivitySubsiri(input: {
  activityGroup: string;
  activitySubgroupCode: string;
  activitySubsiriCode: string;
  activitySubsiriDesc: string;
  activitySubsiriDescEng?: string;
}) {
  return apiRequest<{ data: { success: boolean } }>("/api/setup/activity-code/subsiri", { method: "POST", body: JSON.stringify(input) });
}

export async function updateActivitySubsiri(
  code: string,
  input: { activityGroup: string; activitySubgroupCode: string; activitySubsiriDesc: string; activitySubsiriDescEng?: string },
) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/activity-code/subsiri/${encodeURIComponent(code)}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deleteActivitySubsiri(code: string, activityGroup: string, activitySubgroupCode: string) {
  const params = new URLSearchParams({ activityGroup, activitySubgroupCode });
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/activity-code/subsiri/${encodeURIComponent(code)}?${params.toString()}`, { method: "DELETE" });
}

export async function createActivityType(input: {
  activityGroupCode: string;
  activitySubgroupCode: string;
  activitySubsiriCode: string;
  atActivityCode: string;
  atActivityDescriptionBm: string;
  atActivityDescriptionEn?: string;
  atStatus: "ACTIVE" | "INACTIVE";
}) {
  return apiRequest<{ data: { id: number } }>("/api/setup/activity-code/activity-type", { method: "POST", body: JSON.stringify(input) });
}

export async function updateActivityType(
  id: number,
  input: { atActivityDescriptionBm: string; atActivityDescriptionEn?: string; atStatus: "ACTIVE" | "INACTIVE" },
) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/activity-code/activity-type/${id}`, { method: "PUT", body: JSON.stringify(input) });
}

export async function deleteActivityType(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/activity-code/activity-type/${id}`, { method: "DELETE" });
}

export async function listPtjCodeLevel(params = "") {
  return apiRequest<{ data: PtjCodeRow[] }>(`/api/setup/ptj-code${params}`);
}

export async function createPtjCode(input: PtjCodeInput) {
  return apiRequest<{ data: { ounId: number; ounCode: string } }>("/api/setup/ptj-code", { method: "POST", body: JSON.stringify(input) });
}

export async function updatePtjCode(code: string, input: Partial<PtjCodeInput>) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/ptj-code/${encodeURIComponent(code)}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deletePtjCode(code: string) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/ptj-code/${encodeURIComponent(code)}`, { method: "DELETE" });
}

export async function listAccountCodeLevel(params = "") {
  return apiRequest<{ data: AccountActivityRow[] | AccountCodeRow[] }>(`/api/setup/account-code${params}`);
}

export async function createAccountCode(input: AccountCodeInput) {
  return apiRequest<{ data: { success: boolean } }>("/api/setup/account-code", { method: "POST", body: JSON.stringify(input) });
}

export async function updateAccountCode(code: string, input: Partial<AccountCodeInput>) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/account-code/${encodeURIComponent(code)}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deleteAccountCode(code: string) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/account-code/${encodeURIComponent(code)}`, { method: "DELETE" });
}

export async function createAccountActivity(input: AccountActivityInput) {
  return apiRequest<{ data: { ldeId: number } }>("/api/setup/account-code/activity", { method: "POST", body: JSON.stringify(input) });
}

export async function updateAccountActivity(id: number, input: AccountActivityInput) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/account-code/activity/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function deleteAccountActivity(id: number) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/account-code/activity/${id}`, { method: "DELETE" });
}

export async function listAccountCodePpi(params = "") {
  return apiRequest<{ data: AccountCodePpiRow[]; meta: Record<string, unknown> }>(`/api/setup/account-code-ppi${params}`);
}

export async function getAccountCodePpiOptions() {
  return apiRequest<{ data: AccountCodePpiOptions }>("/api/setup/account-code-ppi/options");
}

export async function listCostCentres(params = "") {
  return apiRequest<{ data: CostCentreRow[]; meta: Record<string, unknown> }>(`/api/setup/cost-centre${params}`);
}

export async function getCostCentre(id: number) {
  return apiRequest<{ data: CostCentreRow }>(`/api/setup/cost-centre/${id}`);
}

export async function createCostCentre(input: CostCentreInput) {
  return apiRequest<{ data: { id: number } }>("/api/setup/cost-centre", { method: "POST", body: JSON.stringify(input) });
}

export async function updateCostCentre(id: number, input: CostCentreInput) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/cost-centre/${id}`, { method: "PUT", body: JSON.stringify(input) });
}

export async function getCostCentreOptions() {
  return apiRequest<{
    data: {
      smartFilter: { costCentre: { id: string; label: string }[]; ptjCode: { id: string; label: string }[]; status: { id: string; label: string }[] };
      popupModal: {
        ptjCode: { id: string; label: string }[];
        status: { id: string; label: string }[];
        flagSalary: { id: string; label: string }[];
      };
    };
  }>("/api/setup/cost-centre/options");
}

export async function listCascadeStructures(params = "") {
  return apiRequest<{ data: CascadeStructureRow[]; meta: Record<string, unknown> }>(`/api/setup/cascade-structure${params}`);
}

export async function getCascadeStructure(id: number) {
  return apiRequest<{ data: CascadeStructureRow }>(`/api/setup/cascade-structure/${id}`);
}

export async function createCascadeStructure(input: CascadeStructureInput) {
  return apiRequest<{ data: { id: number } }>("/api/setup/cascade-structure", { method: "POST", body: JSON.stringify(input) });
}

export async function updateCascadeStructure(id: number, input: CascadeStructureInput) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/cascade-structure/${id}`, { method: "PUT", body: JSON.stringify(input) });
}

// FIMS Budget (Increment / Decrement / Virement) — read-only list.
export async function listBudgetMovements(type: BudgetMovementType, params = "") {
  return apiRequest<{ data: BudgetMovementRow[]; meta: Record<string, unknown> }>(
    `/api/budget/movements/${encodeURIComponent(type)}${params}`,
  );
}

export async function getBudgetMovement(id: string | number) {
  return apiRequest<{ data: BudgetMovementRow }>(`/api/budget/movements/show/${encodeURIComponent(String(id))}`);
}

export async function getBudgetMovementOptions(type: BudgetMovementType) {
  return apiRequest<{ data: BudgetMovementOptions }>(`/api/budget/movements/${encodeURIComponent(type)}/options`);
}

// FIMS Budget Monitoring (PAGEID 1201 / MENUID 1471) — read-only aggregated list.
export async function listBudgetMonitoring(params = "") {
  return apiRequest<{ data: BudgetMonitoringRow[]; meta: Record<string, unknown> }>(
    `/api/budget/monitoring${params}`,
  );
}

export async function getBudgetMonitoringOptions() {
  return apiRequest<{ data: BudgetMonitoringOptions }>("/api/budget/monitoring/options");
}

// FIMS Budget Initial V2 (PAGEID 1264 / MENUID 1541) — stubbed list (backend BL missing).
export async function listBudgetInitial(params = "") {
  return apiRequest<{ data: BudgetInitialRow[]; meta: Record<string, unknown> }>(
    `/api/budget/initial${params}`,
  );
}

export async function getBudgetInitialOptions() {
  return apiRequest<{ data: BudgetInitialOptions }>("/api/budget/initial/options");
}

// FIMS Budget Closing (PAGEID 1953) — options + process/reverse stubs.
export async function getBudgetClosingOptions() {
  return apiRequest<{ data: BudgetClosingOptions }>("/api/budget/closing/options");
}

export async function budgetClosingProcess(payload: BudgetClosingPayload) {
  return apiRequest<{ data: unknown }>("/api/budget/closing/process", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function budgetClosingReverse(payload: BudgetClosingPayload) {
  return apiRequest<{ data: unknown }>("/api/budget/closing/reverse", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function getCascadeStructureOptions(ptjCode = "") {
  const params = ptjCode ? `?ptjCode=${encodeURIComponent(ptjCode)}` : "";
  return apiRequest<{
    data: {
      smartFilter: {
        fund: { id: string; label: string }[];
        activity: { id: string; label: string }[];
        ptj: { id: string; label: string }[];
        costCenter: { id: string; label: string }[];
        status: { id: string; label: string }[];
      };
      popupModal: {
        fund: { id: string; label: string }[];
        activity: { id: string; label: string }[];
        ptj: { id: string; label: string }[];
        costCenter: { id: string; label: string }[];
        status: { id: string; label: string }[];
      };
    };
  }>(`/api/setup/cascade-structure/options${params}`);
}

// Letter Phrase setup (PAGEID 2911 / MENUID 3506). Read-only listing with
// an edit-only popup modal — legacy BL never exposed add or delete.
export async function listLetterPhrases(params = "") {
  return apiRequest<{ data: LetterPhraseRow[]; meta: Record<string, unknown> }>(
    `/api/setup/letter-phrase${params}`,
  );
}

export async function getLetterPhrase(lpmValue: string) {
  return apiRequest<{ data: LetterPhraseDetail }>(
    `/api/setup/letter-phrase/${encodeURIComponent(lpmValue)}`,
  );
}

export async function updateLetterPhrase(lpmValue: string, input: LetterPhraseInput) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/setup/letter-phrase/${encodeURIComponent(lpmValue)}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
}

// Petty Cash Recoup list (PAGEID 1255 / MENUID 1532).
export async function listPettyCashRecoups(params = "") {
  return apiRequest<{ data: PettyCashRecoupRow[]; meta: Record<string, unknown> }>(
    `/api/petty-cash/recoup${params}`,
  );
}

// Petty Cash Recoup form view (PAGEID 1256 / MENUID 1534).
export async function getPettyCashRecoup(pcbId: number) {
  return apiRequest<{ data: PettyCashRecoupDetail }>(`/api/petty-cash/recoup/${pcbId}`);
}

export async function getPettyCashApplicationListOptions() {
  return apiRequest<{ data: PettyCashApplicationListOptions }>("/api/petty-cash/applications/options");
}

export async function listPettyCashApplications(params = "") {
  return apiRequest<{ data: PettyCashApplicationListRow[]; meta: Record<string, unknown> }>(
    `/api/petty-cash/applications${params}`,
  );
}

export async function getPettyCashApplication(id: number) {
  return apiRequest<{ data: PettyCashApplicationDetail }>(`/api/petty-cash/applications/${id}`);
}

// Petty Cash Claim Form (PAGEID 1544 / MENUID 1872). Legacy BL
// MM_API_PETTYCASH_PETTYCASHCLAIMFORM.
export async function getPettyCashClaim(id: number) {
  return apiRequest<{ data: PettyCashClaimForm }>(`/api/petty-cash/claim-form/${id}`);
}

export async function suggestPettyCashClaimRequestBy(q: string, limit = 20) {
  const params = new URLSearchParams({ q, limit: String(limit) });
  return apiRequest<{ data: PettyCashClaimRequestBySuggestion[] }>(
    `/api/petty-cash/claim-form/request-by/suggest?${params.toString()}`,
  );
}

export async function suggestPettyCashClaimPcm(q: string, ptjCode = "", limit = 20) {
  const params = new URLSearchParams({ q, limit: String(limit) });
  if (ptjCode) params.set("ptj_code", ptjCode);
  return apiRequest<{ data: PettyCashClaimPcmSuggestion[] }>(
    `/api/petty-cash/claim-form/pcm/suggest?${params.toString()}`,
  );
}

export async function suggestPettyCashClaimAccountCode(q: string, fundType = "", limit = 20) {
  const params = new URLSearchParams({ q, limit: String(limit) });
  if (fundType) params.set("fund_type", fundType);
  return apiRequest<{ data: PettyCashClaimAccountCodeSuggestion[] }>(
    `/api/petty-cash/claim-form/account-code/suggest?${params.toString()}`,
  );
}

export async function suggestPettyCashClaimFundType(q = "", limit = 50) {
  const params = new URLSearchParams({ q, limit: String(limit) });
  return apiRequest<{ data: PettyCashClaimDimensionSuggestion[] }>(
    `/api/petty-cash/claim-form/fund-type/suggest?${params.toString()}`,
  );
}

export async function suggestPettyCashClaimActivityCode(
  q = "",
  fundType = "",
  limit = 50,
) {
  const params = new URLSearchParams({ q, limit: String(limit) });
  if (fundType) params.set("fund_type", fundType);
  return apiRequest<{ data: PettyCashClaimDimensionSuggestion[] }>(
    `/api/petty-cash/claim-form/activity-code/suggest?${params.toString()}`,
  );
}

export async function suggestPettyCashClaimOun(
  q = "",
  opts: { fundType?: string; activityCode?: string } = {},
  limit = 50,
) {
  const params = new URLSearchParams({ q, limit: String(limit) });
  if (opts.fundType) params.set("fund_type", opts.fundType);
  if (opts.activityCode) params.set("activity_code", opts.activityCode);
  return apiRequest<{ data: PettyCashClaimDimensionSuggestion[] }>(
    `/api/petty-cash/claim-form/oun/suggest?${params.toString()}`,
  );
}

export async function suggestPettyCashClaimCostCentre(
  q = "",
  opts: { fundType?: string; activityCode?: string; ounCode?: string } = {},
  limit = 50,
) {
  const params = new URLSearchParams({ q, limit: String(limit) });
  if (opts.fundType) params.set("fund_type", opts.fundType);
  if (opts.activityCode) params.set("activity_code", opts.activityCode);
  if (opts.ounCode) params.set("oun_code", opts.ounCode);
  return apiRequest<{ data: PettyCashClaimDimensionSuggestion[] }>(
    `/api/petty-cash/claim-form/cost-centre/suggest?${params.toString()}`,
  );
}

export async function getPettyCashClaimNextSeq() {
  return apiRequest<{ data: { pcdId: number } }>(`/api/petty-cash/claim-form/next-seq`);
}

export async function savePettyCashClaim(payload: PettyCashClaimSavePayload) {
  return apiRequest<{ data: PettyCashClaimSaveResponse }>("/api/petty-cash/claim-form", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function submitPettyCashClaim(id: number) {
  return apiRequest<{ data: { status: string; pmsStatus: string; workflowStub: boolean; message: string } }>(
    `/api/petty-cash/claim-form/${id}/submit`,
    { method: "POST", body: JSON.stringify({}) },
  );
}

export async function cancelPettyCashClaim(id: number, cancelReason: string) {
  return apiRequest<{ data: { status: string; pmsStatus: string; message: string } }>(
    `/api/petty-cash/claim-form/${id}/cancel`,
    { method: "POST", body: JSON.stringify({ cancelReason }) },
  );
}

export async function getPettyCashClaimProcessFlow(id: number) {
  return apiRequest<{ data: unknown[]; meta?: Record<string, unknown> }>(
    `/api/petty-cash/claim-form/${id}/process-flow`,
  );
}

// List Petty Cash by PTJ (PAGEID 1963 / MENUID 2399).
export async function listPettyCashByPtj(params = "") {
  return apiRequest<{ data: PettyCashByPtjRow[]; meta: Record<string, unknown> }>(
    `/api/petty-cash/by-ptj${params}`,
  );
}

// Bill Petty Cash (PAGEID 1964 / MENUID 2400).
export async function listPettyCashBills(params = "") {
  return apiRequest<{ data: PettyCashBillRow[]; meta: Record<string, unknown> }>(
    `/api/petty-cash/bills${params}`,
  );
}

// Confirmation Payment — Petty Cash (PAGEID 1982 / MENUID 2424).
export async function listPettyCashConfirmPaymentAwaiting(params = "") {
  return apiRequest<{ data: PettyCashConfirmPaymentRow[]; meta: Record<string, unknown> }>(
    `/api/petty-cash/confirm-payment/awaiting${params}`,
  );
}

export async function listPettyCashConfirmPaymentConfirmed(params = "") {
  return apiRequest<{ data: PettyCashConfirmPaymentRow[]; meta: Record<string, unknown> }>(
    `/api/petty-cash/confirm-payment/confirmed${params}`,
  );
}

// Request Petty Cash list (PAGEID 2010 / MENUID 2456).
export async function listPettyCashRequests(params = "") {
  return apiRequest<{ data: PettyCashRequestListRow[]; meta: Record<string, unknown> }>(
    `/api/petty-cash/requests${params}`,
  );
}

// List of Release Paid — Petty Cash (PAGEID 2273 / MENUID 2761).
export async function listPettyCashReleasePaidApplications(params = "") {
  return apiRequest<{ data: PettyCashReleasePaidApplicationRow[]; meta: Record<string, unknown> }>(
    `/api/petty-cash/release-paid/applications${params}`,
  );
}

export async function listPettyCashReleasePaidReceipts(params = "") {
  return apiRequest<{ data: PettyCashReleasePaidReceiptRow[]; meta: Record<string, unknown> }>(
    `/api/petty-cash/release-paid/receipts${params}`,
  );
}

// List of Voucher Petty Cash (PAGEID 2774 / MENUID 3344).
export async function getPettyCashVoucherListOptions() {
  return apiRequest<{ data: PettyCashVoucherListOptions }>("/api/petty-cash/vouchers/options");
}

export async function listPettyCashVouchers(params = "") {
  return apiRequest<{ data: PettyCashVoucherListRow[]; meta: Record<string, unknown> }>(
    `/api/petty-cash/vouchers${params}`,
  );
}

// HOD, VC & TNC setup (PAGEID 1715 / MENUID 2073).
export async function listVcTnc(params = "") {
  return apiRequest<{ data: VcTncRow[]; meta: Record<string, unknown> }>(
    `/api/setup/vc-tnc${params}`,
  );
}

export async function getVcTnc(id: number) {
  return apiRequest<{ data: VcTncDetail }>(`/api/setup/vc-tnc/${id}`);
}

export async function getVcTncOptions() {
  return apiRequest<{ data: VcTncOptions }>("/api/setup/vc-tnc/options");
}

export async function updateVcTnc(id: number, input: { stStaffIdSuperior: string }) {
  return apiRequest<{ data: { success: boolean } }>(`/api/setup/vc-tnc/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

// "Cek yang mungkin error" (PAGEID 2253 / MENUID 2740).
export async function listCheckErrorBillMaster(params = "") {
  return apiRequest<{ data: CheckErrorBillMasterRow[]; meta: Record<string, unknown> }>(
    `/api/setup/check-error/bill-master${params}`,
  );
}

export async function listCheckErrorVoucherDetail(params = "") {
  return apiRequest<{ data: CheckErrorVoucherDetailRow[]; meta: Record<string, unknown> }>(
    `/api/setup/check-error/voucher-detail${params}`,
  );
}

export async function listCheckErrorVoucherMaster(params = "") {
  return apiRequest<{ data: CheckErrorVoucherMasterRow[]; meta: Record<string, unknown> }>(
    `/api/setup/check-error/voucher-master${params}`,
  );
}

export async function listCheckErrorPaymentPelik(params = "") {
  return apiRequest<{ data: CheckErrorPaymentPelikRow[]; meta: Record<string, unknown> }>(
    `/api/setup/check-error/payment-record-pelik${params}`,
  );
}

export async function listCheckErrorPayment2Pelik(params = "") {
  return apiRequest<{ data: CheckErrorPayment2PelikRow[]; meta: Record<string, unknown> }>(
    `/api/setup/check-error/payment-record-pelik2${params}`,
  );
}

export async function listCheckErrorUrlBrfHilang(params = "") {
  return apiRequest<{ data: CheckErrorUrlBrfHilangRow[]; meta: Record<string, unknown> }>(
    `/api/setup/check-error/url-brf-hilang${params}`,
  );
}

export async function listCheckErrorResit(params = "") {
  return apiRequest<{ data: CheckErrorResitRow[]; meta: Record<string, unknown> }>(
    `/api/setup/check-error/resit-no-allocate${params}`,
  );
}

// Setup Carian Structure Budget (PAGEID 2664 / MENUID 3224).
export async function getBudgetStructureSearchOptions() {
  return apiRequest<{ data: BudgetStructureSearchOptions }>(
    "/api/setup/budget-structure-search/options",
  );
}

export async function getBudgetStructureSearchForms() {
  return apiRequest<{ data: BudgetStructureSearchForms }>(
    "/api/setup/budget-structure-search/forms",
  );
}

export async function listJenisCarian(params = "") {
  return apiRequest<{ data: JenisCarianRow[]; meta: Record<string, unknown> }>(
    `/api/setup/budget-structure-search/jenis-carian${params}`,
  );
}

export async function getJenisCarian(id: number) {
  return apiRequest<{ data: JenisCarianDetail }>(
    `/api/setup/budget-structure-search/jenis-carian/${id}`,
  );
}

export async function updateJenisCarian(id: number, input: JenisCarianInput) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/setup/budget-structure-search/jenis-carian/${id}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
}

export async function listBillsSetup(params = "") {
  return apiRequest<{ data: BillsSetupRow[]; meta: Record<string, unknown> }>(
    `/api/setup/budget-structure-search/bills-setup${params}`,
  );
}

export async function getBillsSetup(id: number) {
  return apiRequest<{ data: BillsSetupDetail }>(
    `/api/setup/budget-structure-search/bills-setup/${id}`,
  );
}

export async function updateBillsSetup(id: number, input: BillsSetupInput) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/setup/budget-structure-search/bills-setup/${id}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
}

export async function saveSemiStrict(input: SemiStrictInput) {
  return apiRequest<{ data: { success: boolean } }>(
    "/api/setup/budget-structure-search/semi-strict",
    { method: "PUT", body: JSON.stringify(input) },
  );
}

export async function saveBillsCustomWf(input: BillsCustomWfInput) {
  return apiRequest<{ data: { success: boolean } }>(
    "/api/setup/budget-structure-search/custom-wf",
    { method: "PUT", body: JSON.stringify(input) },
  );
}

// ─── FIMS Cashbook ─────────────────────────────────────────────────────────
// Bank Setup (PAGEID 2680 / MENUID 3246)
export async function listBankSetup(params = "") {
  return apiRequest<{ data: BankSetupRow[]; meta: Record<string, unknown> }>(`/api/cashbook/bank-setup${params}`);
}

export async function getBankSetupOptions() {
  return apiRequest<{ data: BankSetupOptions }>("/api/cashbook/bank-setup/options");
}

export async function getBankSetup(code: string) {
  return apiRequest<{ data: { lbmBankCode: string; lbmBankName: string; isBankMain: "Y" | "N" | null; lbmStatus: number } }>(
    `/api/cashbook/bank-setup/${encodeURIComponent(code)}`,
  );
}

export async function createBankSetup(input: BankSetupInput) {
  return apiRequest<{ data: { lbmBankCode: string } }>("/api/cashbook/bank-setup", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function updateBankSetup(code: string, input: BankSetupInput) {
  return apiRequest<{ data: { success: boolean } }>(`/api/cashbook/bank-setup/${encodeURIComponent(code)}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

// Bank Master (PAGEID 1682 / MENUID 2036)
export async function listBankMaster(params = "") {
  return apiRequest<{ data: BankMasterRow[]; meta: Record<string, unknown> }>(`/api/cashbook/bank-master${params}`);
}

export async function getBankMasterOptions() {
  return apiRequest<{ data: BankMasterOptions }>("/api/cashbook/bank-master/options");
}

export async function getBankMaster(id: number) {
  return apiRequest<{ data: BankMasterRow & { bnmAddressCountry: string | null; bnmAddressPostcode: string | null } }>(
    `/api/cashbook/bank-master/${id}`,
  );
}

export async function createBankMaster(input: BankMasterInput) {
  return apiRequest<{ data: { bnmBankId: number } }>("/api/cashbook/bank-master", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function updateBankMaster(id: number, input: BankMasterInput) {
  return apiRequest<{ data: { success: boolean } }>(`/api/cashbook/bank-master/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

// Bank Account (PAGEID 1736 / MENUID 2097)
export async function listBankAccount(params = "") {
  return apiRequest<{ data: BankAccountRow[]; meta: Record<string, unknown> }>(`/api/cashbook/bank-account${params}`);
}

export async function getBankAccountOptions() {
  return apiRequest<{ data: BankAccountOptions }>("/api/cashbook/bank-account/options");
}

export async function getBankAccount(id: number) {
  return apiRequest<{ data: BankAccountDetail }>(`/api/cashbook/bank-account/${id}`);
}

export async function createBankAccount(input: BankAccountInput) {
  return apiRequest<{ data: { bndBankDetlId: number } }>("/api/cashbook/bank-account", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function updateBankAccount(id: number, input: BankAccountUpdateInput) {
  return apiRequest<{ data: { success: boolean } }>(`/api/cashbook/bank-account/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

// List Of Cashbook DAILY|MONTHLY (PAGEID 1397/2024 / MENUID 1702/2471)
export async function listCashbookList(type: CashbookListType, params = "") {
  return apiRequest<{ data: CashbookListRow[]; meta: Record<string, unknown> }>(
    `/api/cashbook/list/${type.toLowerCase()}${params}`,
  );
}

export async function getCashbookListOptions(type: CashbookListType) {
  return apiRequest<{ data: CashbookListOptions }>(`/api/cashbook/list/${type.toLowerCase()}/options`);
}

// ─── FIMS Account Payable ───────────────────────────────────────────────────
// Payee Registration (Others) — PAGEID 1403 / MENUID 1711 (read-only listing).
export async function listPayeeRegistration(params = "") {
  return apiRequest<{ data: PayeeRegistrationRow[]; meta: Record<string, unknown> }>(
    `/api/account-payable/payee-registration${params}`,
  );
}

export async function getPayeeRegistrationOptions() {
  return apiRequest<{ data: PayeeRegistrationOptions }>("/api/account-payable/payee-registration/options");
}

// Utility Registration — PAGEID 2881 / MENUID 3466 (list + inline add/edit).
export async function listUtilityRegistration(params = "") {
  return apiRequest<{ data: UtilityRegistrationRow[]; meta: Record<string, unknown> }>(
    `/api/account-payable/utility-registration${params}`,
  );
}

export async function getUtilityRegistration(id: string | number) {
  return apiRequest<{ data: UtilityRegistrationDetail }>(`/api/account-payable/utility-registration/${id}`);
}

export async function createUtilityRegistration(input: UtilityRegistrationInput) {
  return apiRequest<{ data: { vcsId: string; vcsVendorCode: string } }>(
    "/api/account-payable/utility-registration",
    { method: "POST", body: JSON.stringify(input) },
  );
}

export async function updateUtilityRegistration(id: string | number, input: UtilityRegistrationInput) {
  return apiRequest<{ data: { success: boolean } }>(`/api/account-payable/utility-registration/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

// Account Bank by Payee — PAGEID 2262 / MENUID 2751 (read-only, payee-type driven).
export async function getAccountBankByPayeeOptions(payeeType?: AccountBankPayeeType) {
  const suffix = payeeType ? `?payee_type=${payeeType}` : "";
  return apiRequest<{ data: AccountBankByPayeeOptions }>(
    `/api/account-payable/account-bank-by-payee/options${suffix}`,
  );
}

export async function listAccountBankByPayee(params = "") {
  return apiRequest<{
    data:
      | AccountBankByPayeeGenericRow[]
      | AccountBankByPayeeSponsorRow[]
      | AccountBankByPayeeInvestmentRow[];
    meta: Record<string, unknown>;
  }>(`/api/account-payable/account-bank-by-payee${params}`);
}

// Account Bank Updated — PAGEID 1719 / MENUID 2078. Bills + vouchers whose
// line-level bank account drifts from the payee master, with bulk resync.
export async function getAccountBankUpdatedOptions(payeeType?: AccountBankUpdatedPayeeType) {
  const suffix = payeeType ? `?payee_type=${payeeType}` : "";
  return apiRequest<{ data: AccountBankUpdatedOptions }>(
    `/api/account-payable/account-bank-updated/options${suffix}`,
  );
}

export async function listAccountBankUpdatedBills(params = "") {
  return apiRequest<{ data: AccountBankUpdatedBillRow[]; meta: Record<string, unknown> }>(
    `/api/account-payable/account-bank-updated/bills${params}`,
  );
}

export async function listAccountBankUpdatedVouchers(params = "") {
  return apiRequest<{ data: AccountBankUpdatedVoucherRow[]; meta: Record<string, unknown> }>(
    `/api/account-payable/account-bank-updated/vouchers${params}`,
  );
}

export async function processAccountBankUpdatedBills(input: AccountBankUpdatedProcessInput) {
  return apiRequest<{ data: AccountBankUpdatedProcessResult }>(
    "/api/account-payable/account-bank-updated/bills/process",
    { method: "POST", body: JSON.stringify(input) },
  );
}

export async function processAccountBankUpdatedVouchers(input: AccountBankUpdatedProcessInput) {
  return apiRequest<{ data: AccountBankUpdatedProcessResult }>(
    "/api/account-payable/account-bank-updated/vouchers/process",
    { method: "POST", body: JSON.stringify(input) },
  );
}

// ─── FIMS Account Receivable ────────────────────────────────────────────────
// Debtor — PAGEID 1415 / MENUID 1727 (datatable + smart filter + delete).
export async function getDebtorOptions() {
  return apiRequest<{ data: DebtorOptions }>("/api/account-receivable/debtor/options");
}

export async function listDebtors(params = "") {
  return apiRequest<{ data: DebtorRow[]; meta: Record<string, unknown> }>(
    `/api/account-receivable/debtor${params}`,
  );
}

export async function deleteDebtor(id: number | string) {
  return apiRequest<{ data: { success: boolean } }>(`/api/account-receivable/debtor/${id}`, {
    method: "DELETE",
  });
}

// Cashbook PTJ — PAGEID 2048 / MENUID 1049 (read-only listing).
export async function listCashbookPtj(params = "") {
  return apiRequest<{ data: CashbookPtjRow[]; meta: Record<string, unknown> }>(
    `/api/account-receivable/cashbook-ptj${params}`,
  );
}

// AR Note listings — Credit / Debit / Discount (MENUID 1041 / 1042 / 1043).
// The `meta` payload includes the `footer` aggregate used to render the
// legacy totals row under the datatable.
export async function listCreditNotes(params = "") {
  return apiRequest<{ data: CreditNoteRow[]; meta: Record<string, unknown> }>(
    `/api/account-receivable/credit-note${params}`,
  );
}

export async function deleteCreditNote(id: number | string) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/account-receivable/credit-note/${id}`,
    { method: "DELETE" },
  );
}

export async function listDebitNotes(params = "") {
  return apiRequest<{ data: DebitNoteRow[]; meta: Record<string, unknown> }>(
    `/api/account-receivable/debit-note${params}`,
  );
}

export async function deleteDebitNote(id: number | string) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/account-receivable/debit-note/${id}`,
    { method: "DELETE" },
  );
}

export async function listDiscountNotes(params = "") {
  return apiRequest<{ data: DiscountNoteRow[]; meta: Record<string, unknown> }>(
    `/api/account-receivable/discount-note${params}`,
  );
}

export async function deleteDiscountNote(id: number | string) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/account-receivable/discount-note/${id}`,
    { method: "DELETE" },
  );
}

// Authorized Receipting — PAGEID 1613 / MENUID 1952.
export async function getAuthorizedReceiptingOptions() {
  return apiRequest<{ data: AuthorizedReceiptingOptions }>(
    "/api/account-receivable/authorized-receipting/options",
  );
}

export async function listAuthorizedReceipting(params = "") {
  return apiRequest<{ data: AuthorizedReceiptingRow[]; meta: Record<string, unknown> }>(
    `/api/account-receivable/authorized-receipting${params}`,
  );
}

export async function deleteAuthorizedReceipting(id: number | string) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/account-receivable/authorized-receipting/${id}`,
    { method: "DELETE" },
  );
}

// ─── AR Credit Note Form — MENUID 1782 ──────────────────────────────────────
// Workflow-ish endpoints (submit / cancel / process-flow) return the same
// envelope as the save endpoints but carry `workflow_stub: true` on the
// data payload to signal that no real workflow task was created — see
// CreditNoteFormController for the rationale.

/**
 * Shared AR lookup: `CUSTOMER_TYPE` dropdown options used by Credit
 * Note / Debit Note / Discount Note forms. The `value` field is the
 * `CODE#Label` composite stored in the master `*_cust_type` column.
 */
export async function listDebtorTypes() {
  return apiRequest<{ data: LookupOption[] }>(
    `/api/account-receivable/lookup/customer-type`,
  );
}

/**
 * Autosuggest: search customers by code or name for the
 * `Customer / Debtor Name *` combobox on AR note forms.
 *
 * Optional `custType` mirrors the legacy `BL_AUTOSUGGEST_RECC_FEE` split:
 * `C` returns creditors (`vcs_iscreditor='Y'`), anything else (D / B / A /
 * F / G / U / blank) returns debtors (`vcs_isdebtor='Y'`). Accepts bare
 * code (`D`) or legacy composite (`D#DEBTOR`).
 */
export async function searchArDebtors(q: string, custType = "", limit = 20) {
  const params = new URLSearchParams({ q, limit: String(limit) });
  if (custType) {
    params.set("cust_type", custType);
  }
  return apiRequest<{ data: DebtorSearchOption[] }>(
    `/api/account-receivable/credit-note-form/search-debtor?${params.toString()}`,
  );
}

/**
 * Autosuggest: search `APPROVE` invoices for the chosen debtor/cust id, for
 * the `Invoice No *` combobox. By default the API requires open balance
 * (`cim_bal_amt > 0`); pass `{ requireBalance: false }` (Credit/Debit note
 * forms) to include zero-balance approved invoices. Mirrors legacy
 * `sddInvoiceNo` composite `invoiceId#invoiceNo`.
 */
export async function searchArInvoicesByDebtor(
  custId: string,
  q = "",
  limit = 20,
  opts?: { requireBalance?: boolean },
) {
  const params = new URLSearchParams({ cust_id: custId, q, limit: String(limit) });
  if (opts?.requireBalance === false) {
    params.set("require_balance", "0");
  }
  return apiRequest<{ data: InvoiceSearchOption[] }>(
    `/api/account-receivable/credit-note-form/search-invoice?${params.toString()}`,
  );
}

export async function getCreditNoteFormInvoiceLines(invoiceId: string) {
  return apiRequest<{ data: InvoiceLinesResponse }>(
    `/api/account-receivable/credit-note-form/invoice-lines?invoice_id=${encodeURIComponent(invoiceId)}`,
  );
}

export async function getCreditNoteForm(id: string | number) {
  return apiRequest<{ data: CreditNoteFormData }>(
    `/api/account-receivable/credit-note-form/${id}`,
  );
}

export async function saveCreditNoteForm(input: unknown) {
  return apiRequest<{ data: SaveCreditNoteResponse }>(
    `/api/account-receivable/credit-note-form`,
    { method: "POST", body: JSON.stringify(input) },
  );
}

export async function submitCreditNoteForm(id: string | number) {
  return apiRequest<{ data: Record<string, unknown> }>(
    `/api/account-receivable/credit-note-form/${id}/submit`,
    { method: "POST", body: JSON.stringify({}) },
  );
}

export async function cancelCreditNoteForm(
  id: string | number,
  reason: string,
) {
  return apiRequest<{ data: Record<string, unknown> }>(
    `/api/account-receivable/credit-note-form/${id}/cancel`,
    { method: "POST", body: JSON.stringify({ cancelReason: reason }) },
  );
}

export async function getCreditNoteFormProcessFlow(id: string | number) {
  return apiRequest<{ data: unknown[]; meta: Record<string, unknown> }>(
    `/api/account-receivable/credit-note-form/${id}/process-flow`,
  );
}

// ─── AR Debit Note Form — MENUID 1783 ───────────────────────────────────────
export async function getDebitNoteFormInvoiceLines(invoiceId: string) {
  return apiRequest<{ data: InvoiceLinesResponse }>(
    `/api/account-receivable/debit-note-form/invoice-lines?invoice_id=${encodeURIComponent(invoiceId)}`,
  );
}

export async function getDebitNoteForm(id: string | number) {
  return apiRequest<{ data: DebitNoteFormData }>(
    `/api/account-receivable/debit-note-form/${id}`,
  );
}

export async function saveDebitNoteForm(input: unknown) {
  return apiRequest<{ data: SaveDebitNoteResponse }>(
    `/api/account-receivable/debit-note-form`,
    { method: "POST", body: JSON.stringify(input) },
  );
}

export async function submitDebitNoteForm(id: string | number) {
  return apiRequest<{ data: Record<string, unknown> }>(
    `/api/account-receivable/debit-note-form/${id}/submit`,
    { method: "POST", body: JSON.stringify({}) },
  );
}

export async function cancelDebitNoteForm(id: string | number, reason: string) {
  return apiRequest<{ data: Record<string, unknown> }>(
    `/api/account-receivable/debit-note-form/${id}/cancel`,
    { method: "POST", body: JSON.stringify({ cancelReason: reason }) },
  );
}

export async function getDebitNoteFormProcessFlow(id: string | number) {
  return apiRequest<{ data: unknown[]; meta: Record<string, unknown> }>(
    `/api/account-receivable/debit-note-form/${id}/process-flow`,
  );
}

// ─── AR Discount Note Form — MENUID 1784 ────────────────────────────────────

/**
 * Lookup for the `Discount Policy *` combobox on Discount Note Form.
 * Mirrors legacy BL `DT_AR_DISCOUNT_NOTE_FORM` ~line 495.
 */
export async function listDiscountPolicies(q = "") {
  const params = new URLSearchParams();
  if (q) params.set("q", q);
  const qs = params.toString();
  return apiRequest<{ data: DiscountPolicyOption[] }>(
    `/api/account-receivable/discount-note-form/discount-policies${qs ? `?${qs}` : ""}`,
  );
}

export async function getDiscountNoteFormInvoiceLines(
  invoiceId: string,
  policyId: string,
) {
  return apiRequest<{ data: DiscountInvoiceLinesResponse }>(
    `/api/account-receivable/discount-note-form/invoice-lines?invoice_id=${encodeURIComponent(invoiceId)}&policy_id=${encodeURIComponent(policyId)}`,
  );
}

export async function getDiscountNoteForm(id: string | number) {
  return apiRequest<{ data: DiscountNoteFormData }>(
    `/api/account-receivable/discount-note-form/${id}`,
  );
}

export async function saveDiscountNoteForm(input: unknown) {
  return apiRequest<{ data: SaveDiscountNoteResponse }>(
    `/api/account-receivable/discount-note-form`,
    { method: "POST", body: JSON.stringify(input) },
  );
}

export async function submitDiscountNoteForm(id: string | number) {
  return apiRequest<{ data: Record<string, unknown> }>(
    `/api/account-receivable/discount-note-form/${id}/submit`,
    { method: "POST", body: JSON.stringify({}) },
  );
}

export async function cancelDiscountNoteForm(
  id: string | number,
  reason: string,
) {
  return apiRequest<{ data: Record<string, unknown> }>(
    `/api/account-receivable/discount-note-form/${id}/cancel`,
    { method: "POST", body: JSON.stringify({ cancelReason: reason }) },
  );
}

export async function getDiscountNoteFormProcessFlow(id: string | number) {
  return apiRequest<{ data: unknown[]; meta: Record<string, unknown> }>(
    `/api/account-receivable/discount-note-form/${id}/process-flow`,
  );
}

// ─── AR Authorized Receipting Form — MENUID 1953 ────────────────────────────
export async function getAuthorizedReceiptingForm(id: string | number) {
  return apiRequest<{ data: AuthorizedReceiptingFormData }>(
    `/api/account-receivable/authorized-receipting-form/${id}`,
  );
}

export async function saveAuthorizedReceiptingForm(input: unknown) {
  return apiRequest<{ data: SaveAuthorizedReceiptingResponse }>(
    `/api/account-receivable/authorized-receipting-form`,
    { method: "POST", body: JSON.stringify(input) },
  );
}

export async function submitAuthorizedReceiptingForm(
  id: string | number,
  input: unknown,
) {
  return apiRequest<{ data: Record<string, unknown> }>(
    `/api/account-receivable/authorized-receipting-form/${id}/submit`,
    { method: "POST", body: JSON.stringify(input) },
  );
}

export async function cancelAuthorizedReceiptingForm(
  id: string | number,
  reason: string,
) {
  return apiRequest<{ data: Record<string, unknown> }>(
    `/api/account-receivable/authorized-receipting-form/${id}/cancel`,
    { method: "POST", body: JSON.stringify({ cancelReason: reason }) },
  );
}

export async function getAuthorizedReceiptingFormProcessFlow(
  id: string | number,
) {
  return apiRequest<{ data: unknown[]; meta: Record<string, unknown> }>(
    `/api/account-receivable/authorized-receipting-form/${id}/process-flow`,
  );
}

/**
 * Fetch the logged-in user's staff profile for the Details card at the top
 * of the Authorized Receipting Form. Replaces the legacy `$_USER` session
 * superglobal lookups. Returns `resolved=false` (plus best-effort name/email
 * only) when the Laravel user cannot be matched against `staff` /
 * `staff_service`.
 */
export async function getCurrentStaffProfile() {
  return apiRequest<{ data: CurrentStaffProfile }>(
    `/api/account-receivable/authorized-receipting-form/current-staff`,
  );
}

/**
 * Autosuggest for the "Event" combobox on the Authorized Receipting Form
 * (visible when Collection Type = EVENT). Backs `capital_project` rows
 * flagged as EVENT and still within their valid window. Pass the current
 * staff id to restrict to the user's own / PTJ-matching events — matches
 * legacy `autoSuggestProject` behaviour.
 */
export async function searchArEvents(
  query = "",
  staffId = "",
  limit = 20,
) {
  const params = new URLSearchParams();
  if (query) params.set("q", query);
  if (staffId) params.set("stf_staff_id", staffId);
  params.set("limit", String(limit));
  const qs = params.toString();
  return apiRequest<{ data: ArEventSearchOption[] }>(
    `/api/account-receivable/authorized-receipting-form/search-event${qs ? `?${qs}` : ""}`,
  );
}

/**
 * Autosuggest for the "+ New" authorized-staff modal. Filters
 * `staff` + `staff_service` to active staff (job status 1/2/4, service
 * status 1/6/B). When `oun` (PTJ) is provided, limits to that PTJ — matches
 * legacy `autoSuggestAuthorized` behaviour.
 */
export async function searchArAuthorizedStaff(
  query = "",
  oun = "",
  limit = 20,
) {
  const params = new URLSearchParams();
  if (query) params.set("q", query);
  if (oun) params.set("oun_code", oun);
  params.set("limit", String(limit));
  const qs = params.toString();
  return apiRequest<{ data: ArStaffSearchOption[] }>(
    `/api/account-receivable/authorized-receipting-form/search-staff${qs ? `?${qs}` : ""}`,
  );
}

// ─── FIMS Credit Control ────────────────────────────────────────────────────

/**
 * Deposit listing (MENUID 1809). Accepts query string already built by the
 * caller so tables / smart filters / sort params are passed through 1:1 to
 * `DepositController@index`.
 */
export async function listDeposits(query = "") {
  return apiRequest<{ data: DepositRow[]; meta: Record<string, unknown> }>(
    `/api/credit-control/deposit${query}`,
  );
}

export async function fetchDepositOptions() {
  return apiRequest<{ data: DepositOptions }>(
    `/api/credit-control/deposit/options`,
  );
}

/**
 * Smart-filter combobox autosuggest for the Deposit listing. `field` matches
 * the controller's switch (deposit_no | vendor_code | ref_no | acct_code |
 * amount | fund_type).
 */
export async function autosuggestDeposit(
  field: string,
  query = "",
  limit = 20,
) {
  const params = new URLSearchParams({ field, limit: String(limit) });
  if (query) params.set("q", query);
  return apiRequest<{ data: { id: string; label: string }[] }>(
    `/api/credit-control/deposit/autosuggest?${params.toString()}`,
  );
}

/**
 * List of Deposit (MENUID 3066). Restricted to account_main rows flagged as
 * subsidiary+deposit and exposes the customer-type / customer-id / PTJ
 * top filters defined in the legacy BL.
 */
export async function listOfDeposit(query = "") {
  return apiRequest<{ data: DepositRow[]; meta: Record<string, unknown> }>(
    `/api/credit-control/list-of-deposit${query}`,
  );
}

export async function fetchListOfDepositOptions() {
  return apiRequest<{ data: ListOfDepositOptions }>(
    `/api/credit-control/list-of-deposit/options`,
  );
}

export async function searchListOfDepositCustomer(query = "", limit = 20) {
  const params = new URLSearchParams({ limit: String(limit) });
  if (query) params.set("q", query);
  return apiRequest<{ data: CcCustomerOption[] }>(
    `/api/credit-control/list-of-deposit/search-customer?${params.toString()}`,
  );
}

/**
 * Invoice Balance (MENUID 3388) — read-only aggregated view. `tf_end_date`
 * is required by the BL; backend defaults to today when omitted.
 */
export async function listInvoiceBalance(query = "") {
  return apiRequest<{ data: InvoiceBalanceRow[]; meta: Record<string, unknown> }>(
    `/api/credit-control/invoice-balance${query}`,
  );
}

export async function fetchInvoiceBalanceOptions() {
  return apiRequest<{ data: InvoiceBalanceOptions }>(
    `/api/credit-control/invoice-balance/options`,
  );
}

export async function searchInvoiceBalanceCustomer(
  query = "",
  customerType = "",
  limit = 20,
) {
  const params = new URLSearchParams({ limit: String(limit) });
  if (query) params.set("q", query);
  if (customerType) params.set("customer_type", customerType);
  return apiRequest<{ data: CcCustomerOption[] }>(
    `/api/credit-control/invoice-balance/search-customer?${params.toString()}`,
  );
}

export async function searchInvoiceBalanceInvoice(
  query = "",
  customerType = "",
  customerId = "",
  limit = 20,
) {
  const params = new URLSearchParams({ limit: String(limit) });
  if (query) params.set("q", query);
  if (customerType) params.set("customer_type", customerType);
  if (customerId) params.set("customer_id", customerId);
  return apiRequest<{ data: CcOption[] }>(
    `/api/credit-control/invoice-balance/search-invoice?${params.toString()}`,
  );
}

/**
 * Detail of Deposit (MENUID 3397) — master form + detail datatable + popup.
 */
export async function getDepositForm(id: number | string) {
  return apiRequest<{ data: DepositFormMaster }>(
    `/api/credit-control/deposit-form/${id}`,
  );
}

export async function listDepositFormDetails(
  id: number | string,
  query = "",
) {
  return apiRequest<{ data: DepositDetailRow[]; meta: Record<string, unknown> }>(
    `/api/credit-control/deposit-form/${id}/details${query}`,
  );
}

export async function updateDepositFormMaster(
  id: number | string,
  input: DepositFormMasterInput,
) {
  return apiRequest<{ data: DepositFormMaster }>(
    `/api/credit-control/deposit-form/${id}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
}

export async function updateDepositFormDetail(
  id: number | string,
  detailId: number | string,
  input: DepositDetailInput,
) {
  return apiRequest<{ data: Record<string, unknown> }>(
    `/api/credit-control/deposit-form/${id}/detail/${detailId}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
}

export async function searchDepositFormCustomer(query = "", limit = 20) {
  const params = new URLSearchParams({ limit: String(limit) });
  if (query) params.set("q", query);
  return apiRequest<{ data: CcCustomerOption[] }>(
    `/api/credit-control/deposit-form/search-customer?${params.toString()}`,
  );
}

// ─── FIMS Portal ───────────────────────────────────────────────────────────
// Read-only self-service listings for vendors/debtors logged into the Portal.

// Debtor Portal > List of Profile Update Application (MENUID 2608)
export async function listDebtorProfileUpdates(params = "") {
  return apiRequest<{ data: DebtorProfileUpdateRow[]; meta: Record<string, unknown> }>(
    `/api/portal/debtor/profile-update-applications${params}`,
  );
}

// Vendor Portal > Tender/Quotation List (MENUID 2767)
export async function listPortalTenders(params = "") {
  return apiRequest<{ data: TenderQuotationRow[]; meta: Record<string, unknown> }>(
    `/api/portal/vendor/tenders${params}`,
  );
}

export async function checkPortalVendorStatus() {
  return apiRequest<{ data: VendorStatusCheck }>("/api/portal/vendor/tenders/check-status");
}

// Vendor Portal > Online Registration Fee History (MENUID 2003)
export async function listPortalRegistrationFees(params = "") {
  return apiRequest<{ data: VendorRegistrationFeeRow[]; meta: Record<string, unknown> }>(
    `/api/portal/vendor/registration-fees${params}`,
  );
}

// Debtor Portal > Financial Information > Reminder (MENUID 2584).
export async function listDebtorReminders(params = "") {
  return apiRequest<{ data: DebtorReminderRow[]; meta: Record<string, unknown> }>(
    `/api/portal/debtor/reminders${params}`,
  );
}

// Debtor Portal > Financial Information > Debtors Statement (MENUID 2267).
export async function listDebtorStatement(params = "") {
  return apiRequest<{
    data: DebtorStatementRow[];
    meta: Record<string, unknown> & { footer?: DebtorStatementFooter };
  }>(`/api/portal/debtor/statement${params}`);
}

// Student Finance > PTPTN Data (PAGEID 857 / MENUID 1031).
export async function listPtptnData(params = "") {
  return apiRequest<{ data: PtptnDataRow[]; meta: Record<string, unknown> }>(
    `/api/student-finance/ptptn-data${params}`,
  );
}

export async function getPtptnData(id: number) {
  return apiRequest<{ data: { header: PtptnDataHeader; details: PtptnDataDetail[] } }>(
    `/api/student-finance/ptptn-data/${id}`,
  );
}

export async function deletePtptnData(id: number) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/student-finance/ptptn-data/${id}`,
    { method: "DELETE" },
  );
}

// Student Finance > Student Profile or Ledger (PAGEID 1232 / MENUID 1509).
// Legacy BL `V2_SFSP_LEDGER_API`.
export async function listLedger(params = "") {
  return apiRequest<{ data: LedgerRow[]; meta: Record<string, unknown> }>(
    `/api/student-finance/ledger${params}`,
  );
}

export async function getLedgerOptions() {
  return apiRequest<{ data: LedgerOptions }>("/api/student-finance/ledger/options");
}

// Student Finance > Manual Invoice Listing (PAGEID 2389 / MENUID 2897).
// Legacy BL `DT_SF_MANUAL_INV_LISTING`.
export async function listManualInvoices(params = "") {
  return apiRequest<{
    data: ManualInvoiceRow[];
    meta: Record<string, unknown> & { footer?: ManualInvoiceFooter };
  }>(`/api/student-finance/manual-invoice${params}`);
}

export async function getManualInvoiceOptions() {
  return apiRequest<{ data: ManualInvoiceOptions }>(
    "/api/student-finance/manual-invoice/options",
  );
}

export async function deleteManualInvoice(id: number) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/student-finance/manual-invoice/${id}`,
    { method: "DELETE" },
  );
}

// Student Finance > Bank Account Update (PAGEID 977 / MENUID 1081).
// Legacy BL `DT_BANK_ACC_UPDATE`. Read-only listing.
export async function listBankAccountUpdates(params = "") {
  return apiRequest<{ data: BankAccountUpdateRow[]; meta: Record<string, unknown> }>(
    `/api/student-finance/bank-account-update${params}`,
  );
}

export async function getBankAccountUpdateOptions() {
  return apiRequest<{ data: BankAccountUpdateOptions }>(
    "/api/student-finance/bank-account-update/options",
  );
}

// Purchasing > Status PO & PR (PAGEID 1520 / MENUID 1841).
export async function listStatusPoPr(params = "") {
  return apiRequest<{ data: StatusPoPrRow[]; meta: Record<string, unknown> }>(
    `/api/purchasing/status-po-pr${params}`,
  );
}

export async function getStatusPoPrOptions() {
  return apiRequest<{ data: StatusPoPrOptions }>(
    "/api/purchasing/status-po-pr/options",
  );
}

// General Ledger > Journal Listing (PAGEID 1700 / MENUID 2056).
export async function listJournalListing(params = "") {
  return apiRequest<{ data: JournalListingRow[]; meta: Record<string, unknown> }>(
    `/api/general-ledger/journal-listing${params}`,
  );
}

export async function getJournalListing(id: number) {
  return apiRequest<{
    data: {
      header: JournalListingHeader;
      debit: JournalListingLine[];
      credit: JournalListingLine[];
    };
  }>(`/api/general-ledger/journal-listing/${id}`);
}

export async function deleteJournalListing(id: number) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/general-ledger/journal-listing/${id}`,
    { method: "DELETE" },
  );
}

export async function getJournalListingOptions() {
  return apiRequest<{ data: JournalListingOptions }>(
    "/api/general-ledger/journal-listing/options",
  );
}

// General Ledger > Manual Journal Listing (PAGEID 1729 / MENUID 2089).
// Source: FIMS BL `V2_GL_JOURNAL_API` (?listing=1 + ?listing_delete=1).
// Read list + DRAFT-only delete. Type-of-Journal is a fixed dropdown
// hard-coded on the legacy page (General / InterOU / Intercompany) and is
// REQUIRED by the backend — index returns an empty page when missing, as
// the legacy BL does.
export async function listManualJournal(params = "") {
  return apiRequest<{ data: ManualJournalRow[]; meta: Record<string, unknown> }>(
    `/api/general-ledger/manual-journal${params}`,
  );
}

export async function deleteManualJournal(id: number) {
  return apiRequest<{ data: { success: boolean } }>(
    `/api/general-ledger/manual-journal/${id}`,
    { method: "DELETE" },
  );
}

export async function getManualJournalOptions() {
  return apiRequest<{ data: ManualJournalOptions }>(
    "/api/general-ledger/manual-journal/options",
  );
}

// Backs the toolbar "Download PDF" button — fetches ALL rows matching the
// current filters (same contract as listManualJournal, no pagination).
// Mirrors `custom/report/Manual Journal/downloadListPDF.php`.
export async function getManualJournalListingPdf(params = "") {
  return apiRequest<{ data: ManualJournalListingPdfPayload }>(
    `/api/general-ledger/manual-journal/listing-pdf${params}`,
  );
}

// Backs the per-row "PDF" row action — header + GL lines + workflow signers.
// Mirrors `custom/report/Manual Journal/downloadPDFmj.php`.
export async function getManualJournalDetail(id: number) {
  return apiRequest<{ data: ManualJournalDetail }>(
    `/api/general-ledger/manual-journal/${id}`,
  );
}

// General Ledger > List of Year and Month (PAGEID 2721 / MENUID 3287).
export async function listGlYearMonth(params = "") {
  return apiRequest<{ data: GlYearMonthRow[]; meta: Record<string, unknown> }>(
    `/api/general-ledger/year-month${params}`,
  );
}

export async function getGlYearMonth(id: number) {
  return apiRequest<{ data: GlYearMonthDetail }>(`/api/general-ledger/year-month/${id}`);
}

export async function getGlYearMonthOptions() {
  return apiRequest<{ data: GlYearMonthOptions }>(
    "/api/general-ledger/year-month/options",
  );
}

export async function createGlYearMonth(input: GlYearMonthInput) {
  return apiRequest<{ data: GlYearMonthDetail }>("/api/general-ledger/year-month", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function updateGlYearMonth(id: number, input: GlYearMonthInput) {
  return apiRequest<{ data: GlYearMonthDetail }>(
    `/api/general-ledger/year-month/${id}`,
    { method: "PUT", body: JSON.stringify(input) },
  );
}

// General Ledger > Posting to GL (TB) (PAGEID 1139 / MENUID 1409).
// Source: FIMS BL `POSTING_TO_TB`. List returns grouped master+document
// rows with aggregated DR/CR sums; show returns the master header plus
// debit + credit line sub-tables in one payload for the in-page modal.
export async function listPostingToTb(params = "") {
  return apiRequest<{ data: PostingToTbRow[]; meta: Record<string, unknown> }>(
    `/api/general-ledger/posting-to-tb${params}`,
  );
}

export async function getPostingToTb(id: number) {
  return apiRequest<{
    data: { header: PostingToTbHeader; debit: PostingToTbLine[]; credit: PostingToTbLine[] };
  }>(`/api/general-ledger/posting-to-tb/${id}`);
}

export async function getPostingToTbOptions() {
  return apiRequest<{ data: PostingToTbOptions }>(
    "/api/general-ledger/posting-to-tb/options",
  );
}

// General Ledger > General Ledger Listing (PAGEID 2068 / MENUID 2519).
// Source: FIMS BL `NAD_API_GL_LISTINGPOSTINGTOGL`. Read-only line-level
// datatable with a single consolidated smart filter modal.
export async function listGlListing(params = "") {
  return apiRequest<{ data: GlListingRow[]; meta: Record<string, unknown> }>(
    `/api/general-ledger/general-ledger-listing${params}`,
  );
}

export async function getGlListingOptions() {
  return apiRequest<{ data: GlListingOptions }>(
    "/api/general-ledger/general-ledger-listing/options",
  );
}
