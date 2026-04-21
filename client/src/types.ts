export type PublishStatus = "draft" | "published" | "archived";
export type ThemeColor = "violet" | "blue" | "green" | "red" | "black-white" | "grey";

export type ApiError = { error: { code: string; message: string; details?: unknown } };

export type ApiResponse<T> = { data: T; meta?: Record<string, unknown> };

export type User = {
  id: number;
  email: string;
  name: string;
  photoUrl?: string;
  role?: string;
};

export type PostInput = {
  title: string;
  slug?: string;
  excerpt?: string;
  content: string;
  status: PublishStatus;
  featuredImageId?: number | null;
  categoryIds?: number[];
};

export type Post = PostInput & {
  id: number;
  slug: string;
  publishedAt: string | null;
  createdAt: string;
  updatedAt: string;
  featuredImage?: Media | null;
  categories?: Category[];
};

export type CategoryInput = {
  name: string;
  slug?: string;
  description?: string;
};

export type Category = {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  createdAt: string;
  updatedAt: string;
  _count?: { posts: number };
};

export type PageInput = {
  title: string;
  slug?: string;
  content: string;
  status: PublishStatus;
  featuredImageId?: number | null;
};

export type Page = PageInput & {
  id: number;
  slug: string;
  publishedAt: string | null;
  createdAt: string;
  updatedAt: string;
  featuredImage?: Media | null;
};

export type Media = {
  id: number;
  filename: string;
  originalName: string;
  title: string | null;
  caption: string | null;
  description: string | null;
  mimeType: string;
  size: number;
  width: number | null;
  height: number | null;
  altText: string | null;
  path: string;
  url: string;
  createdAt: string;
};

export type MediaMetadataInput = {
  title: string;
  altText: string;
  caption: string;
  description: string;
};

export type SettingsPayload = {
  siteTitle: string;
  tagline: string;
  webfrontTitle: string;
  webfrontTagline: string;
  titleFormat: string;
  metaDescription: string;
  siteIconUrl: string;
  webfrontLogoUrl: string;
  sidebarLogoUrl: string;
  faviconUrl: string;
  language: string;
  timezone: string;
  footerText: string;
  frontPageId: number | null;
};

export type PublicSiteSettings = Pick<
  SettingsPayload,
  "siteTitle" | "tagline" | "webfrontTitle" | "webfrontTagline" | "metaDescription" | "footerText" | "siteIconUrl" | "webfrontLogoUrl" | "sidebarLogoUrl" | "faviconUrl"
> & {
  storefrontMenu: StorefrontMenuItem[];
};

export type StorefrontMenuItem = {
  id: string;
  label: string;
  href: string;
  parentId: string | null;
  openInNewTab: boolean;
};

export type Role = {
  id: number;
  name: string;
  description: string;
  permissions: string[];
  createdAt: string;
  updatedAt: string;
};

export type RoleInput = {
  name: string;
  description: string;
  permissions: string[];
};

export type UserDetail = {
  id: number;
  name: string;
  email: string;
  role: string;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
};

export type UserInput = {
  name: string;
  email: string;
  password?: string;
  role: string;
  isActive: boolean;
};

export type AuditLog = {
  id: number;
  userId: number | null;
  action: string;
  auditableType: string | null;
  auditableId: number | null;
  oldValues: Record<string, unknown> | null;
  newValues: Record<string, unknown> | null;
  ipAddress: string | null;
  userAgent: string | null;
  createdAt: string;
  user?: { id: number; name: string; email: string } | null;
};

export type FundTypeRow = {
  index: number;
  ftyFundId: number;
  ftyFundType: string;
  ftyFundDesc: string;
  ftyFundDescEng: string | null;
  ftyBasis: string;
  ftyRemark: string | null;
  ftyStatus: "ACTIVE" | "INACTIVE";
  ftyStatusValue: number;
};

export type FundTypeInput = {
  ftyFundType: string;
  ftyFundDesc: string;
  ftyFundDescEng?: string | null;
  ftyBasis: string;
  ftyStatus: number;
  ftyRemark?: string | null;
};

export type ActivityGroupRow = {
  activityGroupCode: string;
  activityGroupDesc: string;
};

export type ActivitySubgroupRow = {
  activityGroupCode: string;
  activitySubgroupCode: string;
  activitySubgroupDesc: string;
};

export type ActivitySubsiriRow = {
  activityGroup: string;
  activitySubgroupCode: string;
  activitySubsiriCode: string;
  activitySubsiriDesc: string;
  activitySubsiriDescEng: string | null;
};

export type ActivityTypeRow = {
  atActivityId: number;
  activityGroupCode: string;
  activitySubgroupCode: string;
  activitySubsiriCode: string;
  atActivityCode: string;
  atActivityDescriptionBm: string;
  atActivityDescriptionEn: string | null;
  atStatus: "ACTIVE" | "INACTIVE";
  atStatusValue: "1" | "0";
};

export type PtjCodeStatus = "ACTIVE" | "INACTIVE";

export type PtjCodeRow = {
  ounId: number;
  ounCode: string;
  ounDesc: string;
  ounDescBi: string | null;
  orgCode: string | null;
  orgDesc: string | null;
  ounAddress: string | null;
  ounState: string | null;
  stStaffIdHead: string | null;
  stStaffIdSuperior: string | null;
  ounTelNo: string | null;
  ounFaxNo: string | null;
  ounCodeParent: string | null;
  ounLevel: number;
  ounStatus: PtjCodeStatus;
  ounStatusValue: "1" | "0";
  tanggungStartDate: string | null;
  tanggungEndDate: string | null;
  ounShortname: string | null;
  ounRegion: string | null;
  lrgRegionDesc: string | null;
  cnyCountryCode: string | null;
  cnyCountryDesc: string | null;
};

export type PtjCodeInput = {
  ounCode: string;
  ounDesc: string;
  ounStatus: PtjCodeStatus;
  orgCode: string;
  ounLevel: number;
  ounCodeParent?: string | null;
  ounDescBi?: string | null;
  orgDesc?: string | null;
  ounAddress?: string | null;
  ounState?: string | null;
  stStaffIdHead?: string | null;
  stStaffIdSuperior?: string | null;
  ounTelNo?: string | null;
  ounFaxNo?: string | null;
  tanggungStartDate?: string | null;
  tanggungEndDate?: string | null;
  ounShortname?: string | null;
  ounRegion?: string | null;
  cnyCountryCode?: string | null;
};

export type AccountActivityRow = {
  no: number;
  ldeId: number;
  ldeValue: string;
  ldeDescription: string;
  ldeDescription2: string | null;
  ldeStatus: "ACTIVE" | "INACTIVE";
};

export type AccountCodeRow = {
  no: number;
  acmAcctCode: string;
  acmAcctDesc: string;
  acmAcctDescEng: string | null;
  acmAcctActivity: string | null;
  acmAcctStatus: "ACTIVE" | "INACTIVE";
  datecreate: string | null;
  acmAcctGroup: string | null;
  acmAcctLevel: number;
  acmAcctParent: string | null;
};

export type AccountActivityInput = {
  ldeValue: string;
  ldeDescription: string;
  ldeDescription2?: string | null;
  ldeStatus: "ACTIVE" | "INACTIVE";
};

export type AccountCodeInput = {
  acmAcctCode: string;
  acmAcctDesc: string;
  acmAcctDescEng?: string | null;
  acmAcctStatus: "ACTIVE" | "INACTIVE";
  acmAcctGroup?: string | null;
  acmAcctLevel: number;
  acmAcctActivity?: string | null;
  acmAcctParent?: string | null;
};

export type AccountCodePpiRow = {
  index: number;
  acmAcctCode: string;
  acmAcctDesc: string;
  acmAcctLevel: string | number | null;
  acmAcctActivity: string | null;
  acmAcctType: string | null;
  fundType: string | null;
  acmBehavior: string | null;
  acmAcctStatus: "ACTIVE" | "INACTIVE";
};

export type AccountCodePpiOption = { id: string; label: string };

export type AccountCodePpiOptions = {
  topFilter: {
    fundType: AccountCodePpiOption[];
    accountType: AccountCodePpiOption[];
    accountClass: AccountCodePpiOption[];
    accountCode: AccountCodePpiOption[];
  };
  smartFilter: {
    accountCode: AccountCodePpiOption[];
    accountDesc: AccountCodePpiOption[];
    accountLevel: AccountCodePpiOption[];
    statementItem: AccountCodePpiOption[];
    status: AccountCodePpiOption[];
  };
};

export type CostCentreRow = {
  index: number;
  ccrCostcentreId: number;
  ccrCostcentre: string;
  ccrCostcentreDesc: string;
  ccrCostcentreDescEng: string | null;
  ounCode: string;
  ounCodeDesc: string | null;
  ccrAddress: string | null;
  ccrHostelCode: string | null;
  ccrStatus: "ACTIVE" | "INACTIVE";
  ccrStatusValue: number;
  ccrFlagSalary: "Y" | "N" | null;
};

export type CostCentreInput = {
  ccrCostcentre: string;
  ccrCostcentreDesc: string;
  ccrCostcentreDescEng?: string | null;
  ounCode: string;
  ccrAddress?: string | null;
  ccrHostelCode?: string | null;
  ccrStatus: "ACTIVE" | "INACTIVE";
  ccrFlagSalary: "Y" | "N";
};

export type CascadeStructureRow = {
  oucOunitCostcentreId: number;
  ftyFundType: string;
  ftyFundDesc: string | null;
  atActivityCode: string;
  atActivityDescriptionBm: string | null;
  ounCode: string;
  ounDesc: string | null;
  ccrCostcentre: string;
  ccrCostcentreDesc: string | null;
  oucStatus: "ACTIVE" | "INACTIVE";
  oucStatusValue: number;
};

export type CascadeStructureInput = {
  ftyFundType: string;
  atActivityCode: string;
  ounCode: string;
  ccrCostcentre: string;
  oucStatus: "ACTIVE" | "INACTIVE";
};

export type BudgetMovementType = "increment" | "decrement" | "virement";

export type BudgetMovementRow = {
  index: number;
  bmmBudgetMovementId: string;
  bmmBudgetMovementNo: string | null;
  bmmYear: string | number | null;
  qbuQuarterId: string | number | null;
  bmmTransType: "INCREMENT" | "DECREMENT" | "VIREMENT" | null;
  bmmMovementType: string | null;
  bmmTotalAmt: string | number | null;
  bmmStatus: string | null;
  bmmReason: string | null;
  bmmDescription: string | null;
  bmmEndorseDoc: string | null;
  createdby: string | null;
  updatedby: string | null;
  date: string | null;
};

export type BudgetMovementOption = { id: string; label: string };

export type BudgetMovementOptions = {
  smartFilter: {
    year: BudgetMovementOption[];
    status: BudgetMovementOption[];
    movementType: BudgetMovementOption[];
  };
};

// FIMS Budget Monitoring (PAGEID 1201 / MENUID 1471). Read-only aggregated list
// derived from the legacy API_BUDGET_MONITORING BL.
export type BudgetMonitoringRow = {
  index: number;
  sbgBudgetId: string | number | null;
  bdgYear: string | number | null;
  bdgStatus: string | null;
  budgetid: string | null;
  bdgBalCarryforward: number;
  bdgTopupAmt: number;
  bdgInitialAmt: number;
  bdgAdditionalAmt: number;
  bdgVirementAmt: number;
  bdgAllocatedAmt: number;
  bdgLockAmt: number;
  bdgPreRequestAmt: number;
  bdgRequestAmt: number;
  bdgCommitAmt: number;
  bdgExpensesAmt: number;
  bdgBalanceAmt: number;
  ftyFundType: string | null;
  ftyFundDesc: string | null;
  atActivityCode: string | null;
  atActivityDesc: string | null;
  ounCode: string | null;
  ounDesc: string | null;
  ccrCostcentre: string | null;
  ccrCostcentreDesc: string | null;
  lbcBudgetCode: string | null;
  acmAcctDesc: string | null;
  bdgClosing: string | null;
  bdgClosingBy: string | null;
};

export type BudgetMonitoringFooter = {
  bdgBalCarryforward: number;
  bdgTopupAmt: number;
  bdgInitialAmt: number;
  bdgAdditionalAmt: number;
  bdgVirementAmt: number;
  bdgAllocatedAmt: number;
  bdgLockAmt: number;
  bdgPreRequestAmt: number;
  bdgRequestAmt: number;
  bdgCommitAmt: number;
  bdgExpensesAmt: number;
  bdgBalanceAmt: number;
};

export type BudgetLookupOption = { id: string; label: string };

export type BudgetPtjOption = BudgetLookupOption & { level?: number | string | null };

export type BudgetMonitoringOptions = {
  topFilter: {
    year: BudgetLookupOption[];
    fund: BudgetLookupOption[];
    ptjLevel: BudgetLookupOption[];
    ptj: BudgetPtjOption[];
    costCentre: BudgetLookupOption[];
  };
  smartFilter: {
    status: BudgetLookupOption[];
  };
};

// FIMS Budget Initial V2 (PAGEID 1264 / MENUID 1541). Shape mirrors the
// legacy datatable columns (dt_bi / dt_key) sourced from BUDGET.json and
// backed by `budget_allocation_master` joined on `quarter_budget`.
export type BudgetInitialRow = {
  index: number;
  id: number | null;
  years: string | null;
  quarter: string | null;
  quarterId: string | null;
  descr: string | null;
  allocateNo: string | null;
  endorse: string | null;
  amt: number | null;
  stat: string | null;
  date: string | null;
  cancelRemark: string | null;
};

export type BudgetInitialQuarterOption = BudgetLookupOption & {
  year?: string | null;
};

export type BudgetInitialOptions = {
  smartFilter: {
    year: BudgetLookupOption[];
    quarter: BudgetInitialQuarterOption[];
    status: BudgetLookupOption[];
  };
};

// FIMS Budget Closing (PAGEID 1953 / MENUID 2389 & 3154). Form page; backend
// process/reverse are stubs (501) pending the server-side BL.
export type BudgetClosingActivityOption = BudgetLookupOption & {
  activityGroupCode?: string | null;
  activitySubgroupCode?: string | null;
};

export type BudgetClosingSubgroupOption = BudgetLookupOption & {
  activityGroupCode?: string | null;
};

export type BudgetClosingOptions = {
  filter: {
    year: BudgetLookupOption[];
    fund: BudgetLookupOption[];
    activityGroup: BudgetLookupOption[];
    activitySubgroup: BudgetClosingSubgroupOption[];
    activityCode: BudgetClosingActivityOption[];
  };
};

export type BudgetClosingPayload = {
  closingYear: string;
  fundBudgetClosing: string;
  activityGroup?: string | null;
  activitySubgroup?: string | null;
  atActivityCodeTop?: string | null;
};

// Letter Phrase setup (PAGEID 2911 / MENUID 3506). Legacy BL
// SZ_SETUPANDMAINTENANCE_LETTERPHRASE_API. Backend returns snake_case
// identifiers that `CamelCaseMiddleware` rewrites to camelCase.
export type LetterPhraseRow = {
  index: number;
  lpmValue: string;
  lpmValueDescBm: string | null;
  lpmValueDesc: string | null;
  lpmCode: string;
};

export type LetterPhraseDetail = {
  lpmValue: string;
  lpmValueDescBm: string | null;
  lpmValueDesc: string | null;
};

export type LetterPhraseInput = {
  lpmValueDescBm: string;
  lpmValueDesc?: string | null;
};

// HOD, VC & TNC setup (PAGEID 1715 / MENUID 2073). Legacy BL API_VC_TNC_SETUP.
// Fields mirror the controller response; `oun_extended_field` JSON is flattened
// into `stStaffNameSuperior` / `stStaffTitleSuperior` on the detail endpoint.
export type VcTncRow = {
  index: number;
  id: number;
  ounCode: string;
  ounDesc: string | null;
  stStaffIdHead: string | null;
  stStaffIdHeadLabel: string | null;
  stStaffIdSuperior: string | null;
  stStaffIdSuperiorLabel: string | null;
};

export type VcTncDetail = {
  id: number;
  ounCode: string;
  ounDesc: string | null;
  stStaffIdHead: string | null;
  stStaffIdHeadLabel: string | null;
  stStaffIdSuperior: string | null;
  stStaffNameSuperior: string | null;
  stStaffTitleSuperior: string | null;
};

export type VcTncSuperiorOption = {
  id: string;
  label: string;
  title: string | null;
  staffName: string | null;
};

export type VcTncOptions = {
  popupModal: {
    superior: VcTncSuperiorOption[];
  };
};

// "Cek yang mungkin error" (PAGEID 2253 / MENUID 2740). Legacy BL
// MM_API_MAINTANANCE_CEKERROR — seven read-only diagnostic datatables that
// surface orphaned bills / vouchers / payments / receipts. Keys mirror the
// backend snake_case names after `CamelCaseMiddleware` rewrite.
export type CheckErrorBillMasterRow = {
  index: number;
  bimBillsId: string | number | null;
  bimBillsNo: string | null;
  bimBillsType: string | null;
  bimBillAmt: number | string | null;
  bimStatus: string | null;
  bimPaytoId: string | null;
  bimPaytoType: string | null;
  bimPaytoName: string | null;
  bimPaytoAddress: string | null;
  createdby: string | null;
  updatedby: string | null;
  bimSystemId: string | number | null;
  bimPayeeCount: number | string | null;
};

export type CheckErrorVoucherDetailRow = {
  index: number;
  vmaVoucherId: string | number | null;
  dt: number;
  cr: number;
  beza: number;
};

export type CheckErrorVoucherMasterRow = {
  index: number;
  vmaVoucherId: string | number | null;
  vmaVoucherNo: string | null;
  vmaVchStatus: string | null;
  vmaPaytoType: string | null;
  vmaPaytoId: string | null;
  vmaPaytoName: string | null;
};

export type CheckErrorPayment2PelikRow = {
  index: number;
  prePaymentRecordId: string | number | null;
  prePaymentNo: string | null;
  preModType: string | null;
};

export type CheckErrorPaymentPelikRow = {
  index: number;
  vmaVoucherId: string | number | null;
  vmaVoucherNo: string | null;
  vdePaymentNo: string | null;
};

export type CheckErrorUrlBrfHilangRow = {
  index: number;
  wtkApplicationId: string | null;
  wtkTaskId: string | number | null;
  wtkProcessId: string | null;
  wtkWorkflowCode: string | null;
  wtkTaskName: string | null;
  wtkTaskUrl: string | null;
  wtkStatus: string | null;
  createdby: string | null;
};

export type CheckErrorResitRow = {
  index: number;
  pdeDocumentNo: string | null;
  pdeReference: string | null;
  pdeEntAmt: number;
};

// Setup Carian Structure Budget (PAGEID 2664 / MENUID 3224). Legacy BL
// MM_API_GLOBAL_SETUPCARIANSBG — two datatables plus Semi-Strict and
// CustomWF Bill Setup forms.
export type JenisCarianRow = {
  index: number;
  sbssId: number;
  sbssType: string;
  sbssStatus: string;
};

export type JenisCarianDetail = {
  sbssId: number;
  sbssType: string;
  sbssStatus: string;
};

export type BillsSetupRow = {
  index: number;
  bisId: number;
  bisType: string;
  bisStatus: string;
};

export type BillsSetupDetail = {
  bisId: number;
  bisType: string;
  bisStatus: string;
};

export type BudgetStructureSearchOptions = {
  jenisCarianModal: {
    status: BudgetLookupOption[];
  };
  billSetupModal: {
    status: BudgetLookupOption[];
  };
  semiStrict: {
    column: BudgetLookupOption[];
    level: BudgetLookupOption[];
  };
  billsCustomWf: {
    sequence: BudgetLookupOption[];
  };
};

export type BudgetStructureSearchForms = {
  semiStrict: {
    sbssColumnSelection: string | null;
    sbssLevelSelection: string | null;
  };
  billsCustomWf: {
    bisSequenceLevel: string | null;
  };
};

export type JenisCarianInput = {
  sbssStatus: "ACTIVE" | "INACTIVE";
};

export type BillsSetupInput = {
  bisStatus: "ACTIVE" | "INACTIVE";
};

export type SemiStrictInput = {
  sbssColumnSelection: "ACCOUNT" | "ACTIVITY";
  sbssLevelSelection: string;
};

export type BillsCustomWfInput = {
  bisSequenceLevel: string;
};
