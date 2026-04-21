import { apiRequest } from "./client";
import type {
  AuditLog,
  ActivityGroupRow,
  AccountActivityInput,
  AccountActivityRow,
  AccountCodeInput,
  AccountCodeRow,
  AccountCodePpiRow,
  AccountCodePpiOptions,
  ActivitySubgroupRow,
  ActivitySubsiriRow,
  ActivityTypeRow,
  BudgetClosingOptions,
  BudgetClosingPayload,
  BudgetInitialOptions,
  BudgetInitialRow,
  BudgetMonitoringOptions,
  BudgetMonitoringRow,
  BudgetMovementOptions,
  BudgetMovementRow,
  BudgetMovementType,
  Category,
  CategoryInput,
  CascadeStructureInput,
  CascadeStructureRow,
  CostCentreInput,
  CostCentreRow,
  FundTypeInput,
  FundTypeRow,
  Media,
  MediaMetadataInput,
  Page,
  PageInput,
  PtjCodeInput,
  PtjCodeRow,
  Post,
  PostInput,
  PublicSiteSettings,
  Role,
  RoleInput,
  SettingsPayload,
  StorefrontMenuItem,
  UserDetail,
  UserInput,
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
