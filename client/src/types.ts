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

// Petty Cash Recoup list (PAGEID 1255 / MENUID 1532). Legacy BL
// API_PETTYCASH_PETTYCASHRECOUP (?PettyCashRecoupList_dt=1).
export type PettyCashRecoupRow = {
  index: number;
  pcbId: number;
  pcbBatchId: string | null;
  pcbTransNo: string | null;
  pcbBatchAmt: number | null;
  pcmBalance: number | null;
  pcbBalanceBefore: number | null;
  pcbReceiveamt: number | null;
  pcbBalanceInhand: number | null;
  pcbStatus: string | null;
  vmaVoucherNo: string | null;
  vmaVchStatus: string | null;
  urlView: string;
  urlEdit: string;
};

// Petty Cash Recoup form (PAGEID 1256 / MENUID 1534). Legacy BL
// API_PETTYCASH_PETTYCASHRECOUPFORM — `PettyCashBatchMaster` +
// `PettyCashRecoupDetailSelected_dt` branches.
export type PettyCashRecoupDetailLine = {
  index: number;
  pcdId: number;
  pmsApplicationNo: string;
  pmsRequestDate: string | null;
  pcdReceiptNo: string;
  ftyFundType: string;
  atActivityCode: string;
  ounCode: string;
  ccrCostcentre: string;
  acmAcctCode: string;
  soCode: string;
  cpaProjectNo: string;
  pcdTransAmt: number | null;
  pcdBatchStatus: string;
};

export type PettyCashRecoupDetail = {
  pcbId: number;
  pcbBatchId: string;
  pcbTransNo: number | null;
  pcbBatchAmt: number | null;
  pcbStatus: string;
  pcbBalanceBefore: number | null;
  pcbReceiveamt: number | null;
  pcbBalanceInhand: number | null;
  ounCode: string;
  vmaVoucherNo: string | null;
  vmaVchStatus: string | null;
  lines: PettyCashRecoupDetailLine[];
};

// List of Petty Cash Application (PAGEID 1217 / MENUID 1490). Legacy BL
// API_PETTYCASH_LISTAPPLICATIONPETTYCASH.
export type PettyCashApplicationListRow = {
  index: number;
  pmsId: number;
  pmsApplicationNo: string | null;
  pmsRequestBy: string | null;
  pmsRequestDate: string;
  pmsTotalAmt: number | null;
  rejectAmt: number;
  cancelAmt: number;
  paidAmount: number;
  pmsStatus: string | null;
  editable: string;
  urlView: string;
  urlEdit: string;
};

export type PettyCashApplicationListOptions = {
  status: { id: string; label: string }[];
};

export type PettyCashApplicationDetailLine = {
  pcdId: number;
  pcdTransDesc: string | null;
  pcdReceiptNo: string | null;
  acmAcctCode: string | null;
  pcdTransAmt: number | null;
  pcdStatus: string | null;
};

export type PettyCashApplicationDetail = {
  pmsId: number;
  pmsApplicationNo: string | null;
  pmsRequestBy: string | null;
  pmsRequestByDesc: string | null;
  pmsPayToId: string | null;
  pmsPayToIdDesc: string | null;
  pmsRequestDate: string;
  pmsRequestTime: string;
  pmsTotalAmt: number;
  pmsStatus: string | null;
  requestorName: string;
  requestorJob: string;
  payToName: string;
  payToJob: string;
  lines: PettyCashApplicationDetailLine[];
};

// List Petty Cash by PTJ (PAGEID 1963 / MENUID 2399). Legacy BL
// NAD_API_PC_PETTYCASHBYPTJ.
export type PettyCashByPtjRow = {
  index: number;
  pmsId: number;
  pcmId: number;
  pmsApplicationNo: string | null;
  pmsRequestBy: string | null;
  pmsRequestDate: string;
  pmsTotalAmt: number | null;
  pmsReturnAmt: number | null;
  pmsStatus: string | null;
  urlView: string;
};

// Bill Petty Cash (PAGEID 1964 / MENUID 2400). Legacy BL
// NAD_API_PC_PETTYCASHBILL.
export type PettyCashBillRow = {
  index: number;
  pcbId: number;
  pcbBatchId: string | null;
  pcbBatchAmt: number | null;
  pcbStatus: string | null;
  urlView: string;
};

// Confirmation Payment — Petty Cash (PAGEID 1982 / MENUID 2424). Legacy BL
// NAD_API_PC_CONFIRMATIONPAYMENT. Awaiting and confirmed rows share most
// columns; confirmed rows add namastaff (stfStaffName) and pcbReceivedate.
export type PettyCashConfirmPaymentRow = {
  index: number;
  pcbId: number;
  pcbBatchId: string | null;
  pcbBatchAmt: number | null;
  pcbStatus: string | null;
  pcbApproveAmt: number | null;
  vmaVoucherNo: string | null;
  vdePaymentNo: string | null;
  preTotalAmt: number | null;
  stfStaffName?: string | null;
  pcbReceivedate?: string;
  urlView: string;
};

// Request Petty Cash list (PAGEID 2010 / MENUID 2456). Legacy BL
// NAD_API_PC_REQUESTPETTYCASH.
export type PettyCashRequestListRow = {
  index: number;
  pmsId: number;
  pmsApplicationNo: string | null;
  pmsRequestBy: string | null;
  stfStaffName: string | null;
  pmsRequestDate: string;
  pmsTotalAmt: number | null;
  pcbBatchId: string | null;
  pcbStatus: string | null;
  recoupCreatedDate: string;
  bimBillsNo: string | null;
  bimStatus: string | null;
  billCreatedDate: string;
  vmaVoucherNo: string | null;
  vmaVchStatus: string | null;
  voucherCreatedDate: string;
  urlView: string;
};

// List of Release Paid — Petty Cash (PAGEID 2273 / MENUID 2761). Legacy BL
// NAD_API_PC_LISTOFRELEASEPAID.
export type PettyCashReleasePaidApplicationRow = {
  index: number;
  pmsId: number;
  pcmId: number;
  pmsApplicationNo: string | null;
  pmsRequestBy: string | null;
  pmsRequestDate: string;
  pmsPayToId: string | null;
  pcdPaidDate: string;
  pmsTotalAmt: number | null;
  pmsReturnAmt: number | null;
  pmsStatus: string | null;
  urlView: string;
};

export type PettyCashReleasePaidReceiptRow = {
  index: number;
  pmsId: number;
  pcmId: number;
  pmsApplicationNo: string | null;
  pcdReceiptNo: string | null;
  pcdPaidDate: string;
  pmsRequestBy: string | null;
  pmsRequestDate: string;
  pmsPayToId: string | null;
  pcdTransAmt: number | null;
  pmsStatus: string | null;
};

// List of Voucher Petty Cash (PAGEID 2774 / MENUID 3344). Legacy BL
// NAD_API_PC_LISTOFVOUCHERPETTYCASH.
export type PettyCashVoucherListRow = {
  index: number;
  vmaVoucherId: number;
  vmaVoucherNo: string | null;
  vmaVoucherDate: string;
  vmaVoucherAmt: number | null;
  vmaVchStatus: string | null;
  bimBillsNo: string | null;
  pcbBatchId: string | null;
  urlView: string;
};

export type PettyCashVoucherListOptions = {
  status: { id: string; label: string }[];
};

// Petty Cash Claim Form (PAGEID 1544 / MENUID 1872). Legacy BL
// MM_API_PETTYCASH_PETTYCASHCLAIMFORM. Header + detail lines drive the full
// claim application (create / edit / view / cancel). Workflow submit/cancel
// endpoints return `workflowStub=true` until the FIMS workflow SPs are ported.
export type PettyCashClaimFormHead = {
  pmsId: number;
  pmsApplicationNo: string;
  pmsRequestBy: string;
  pmsRequestByDesc: string;
  pmsRequestDate: string;
  pmsTotalAmt: number;
  pmsStatus: string;
};

export type PettyCashClaimFormLine = {
  pcdId: number;
  pcdReceiptNo: string;
  pcdTransDesc: string;
  pcdTransAmt: number;
  pcdStatus: string;
  pcmId: number;
  pcmPaytoId: string;
  pcmPaytoName: string;
  pcmMaxPerReceipt: number | null;
  ftyFundType: string;
  ftyFundDesc: string;
  atActivityCode: string;
  atActivityDesc: string;
  ounCode: string;
  ounDesc: string;
  ccrCostcentre: string;
  ccrCostcentreDesc: string;
  cpaProjectNo: string;
  soCode: string;
  acmAcctCode: string;
  acmAcctDesc: string;
};

export type PettyCashClaimForm = {
  head: PettyCashClaimFormHead;
  lines: PettyCashClaimFormLine[];
};

export type PettyCashClaimRequestBySuggestion = {
  id: string;
  text: string;
  Name: string;
};

export type PettyCashClaimPcmSuggestion = {
  id: number;
  text: string;
  defaults: {
    ftyFundType: string;
    ftyFundDesc: string;
    atActivityCode: string;
    atActivityDesc: string;
    ounCode: string;
    ounDesc: string;
    ccrCostcentre: string;
    ccrCostcentreDesc: string;
    soCode: string;
  };
  maxPerReceipt: number | null;
};

export type PettyCashClaimAccountCodeSuggestion = {
  id: string;
  text: string;
};

export type PettyCashClaimDimensionSuggestion = {
  id: string;
  desc: string;
  text: string;
};

export type PettyCashClaimLinePayload = {
  pcdId?: number | null;
  pcdReceiptNo: string;
  pcdTransDesc: string;
  pcdTransAmt: number;
  pcmId: number;
  ftyFundType?: string | null;
  atActivityCode?: string | null;
  ounCode?: string | null;
  ccrCostcentre?: string | null;
  cpaProjectNo?: string | null;
  soCode?: string | null;
  acmAcctCode?: string | null;
};

export type PettyCashClaimSavePayload = {
  head: {
    pmsId?: number | null;
    pmsApplicationNo?: string | null;
    pmsRequestBy: string;
    pmsRequestByDesc?: string | null;
    pmsRequestDate: string;
    pmsTotalAmt?: number | null;
    pcmId: number;
  };
  lines: PettyCashClaimLinePayload[];
};

export type PettyCashClaimSaveResponse = {
  status: "ok";
  pmsId: number;
  pmsApplicationNo: string;
  pmsTotalAmt: number;
  pmsStatus: string;
  workflowStub: boolean;
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

// ─── FIMS Cashbook ──────────────────────────────────────────────────────────
// Lookup option shape shared by Cashbook smart-filter / popup-modal dropdowns.
export type CashbookOption<TId = string | number> = { id: TId; label: string };

// Bank Setup (PAGEID 2680 / MENUID 3246) — table lookup_bank_main.
export type BankSetupRow = {
  index: number;
  lbmBankCode: string;
  lbmBankName: string;
  isBankMain: "Y" | "N" | null;
  mainBankLabel: "YES" | "NO";
  lbmStatus: "ACTIVE" | "INACTIVE";
  lbmStatusValue: number;
  updatedDate: string | null;
};

export type BankSetupInput = {
  lbmBankCode?: string;
  lbmBankName: string;
  isBankMain: "Y" | "N";
  lbmStatus: 0 | 1;
};

export type BankSetupOptions = {
  smartFilter: {
    bankCode: CashbookOption[];
    isBankMain: CashbookOption[];
    status: CashbookOption[];
  };
  popupModal: {
    isBankMain: CashbookOption[];
    status: CashbookOption<number>[];
  };
};

// Bank Master (PAGEID 1682 / MENUID 2036) — table bank_master.
export type BankMasterRow = {
  index: number;
  bnmBankId: number;
  bnmBankCodeMain: string | null;
  bnmBankCode: string;
  bnmBankDesc: string;
  bnmShortname: string | null;
  bnmBankAddress: string | null;
  bnmAddressCity: string | null;
  bnmContactPerson: string | null;
  bnmBranchName: string | null;
  bnmOfficeTelno: string | null;
  bnmOfficeFaxno: string | null;
  bnmUrlAddress: string | null;
  bnmSwiftCode: string | null;
  bnmBusinessNature: string | null;
};

export type BankMasterInput = {
  bnmBankCode: string;
  bnmBankCodeMain?: string | null;
  bnmBankDesc: string;
  bnmShortname?: string | null;
  bnmBankAddress: string;
  bnmAddressCity?: string | null;
  bnmContactPerson: string;
  bnmBranchName?: string | null;
  bnmOfficeTelno?: string | null;
  bnmOfficeFaxno?: string | null;
  bnmUrlAddress: string;
  bnmSwiftCode: string;
  bnmBusinessNature?: string | null;
};

export type BankMasterOptions = {
  smartFilter: {
    bankCode: CashbookOption[];
    mainBank: CashbookOption[];
  };
  popupModal: {
    mainBank: CashbookOption[];
  };
};

// Bank Account (PAGEID 1736 / MENUID 2097) — table bank_detl + joins.
export type BankAccountRow = {
  index: number;
  bndBankDetlId: number;
  bnmBankDesc: string | null;
  bndBankAcctno: string | null;
  acmAcctCode: string | null;
  acmAcctDesc: string | null;
  bndStatus: "ACTIVE" | "INACTIVE";
  bndStatusValue: number;
  createdby: string | null;
};

export type BankAccountDetail = {
  bndBankDetlId: number;
  bnmBankId: number;
  bnmBankCode: string | null;
  bnmBankDesc: string | null;
  bndBankAcctno: string | null;
  acmAcctCode: string | null;
  acmAcctDesc: string | null;
  ounCode: string | null;
  bndStatus: number;
  bndIsBankMain: "Y" | "N";
};

export type BankAccountInput = {
  bnmBankId: number | string;
  bndBankAcctno: string;
  acmAcctCode: string;
  ounCode?: string | null;
  bndStatus: 0 | 1;
  bndIsBankMain: "Y" | "N";
};

export type BankAccountUpdateInput = {
  bndBankAcctno: string;
  ounCode?: string | null;
  bndStatus: 0 | 1;
  bndIsBankMain: "Y" | "N";
};

export type BankAccountOptions = {
  smartFilter: {
    bankName: CashbookOption<number>[];
    status: CashbookOption[];
  };
  popupModal: {
    bankName: CashbookOption<number>[];
    accountCode: (CashbookOption & { desc?: string | null })[];
    ptj: CashbookOption[];
    isBankMain: CashbookOption[];
    status: CashbookOption<number>[];
  };
};

// List Of Cashbook DAILY/MONTHLY (PAGEID 1397/2024 / MENUID 1702/2471).
export type CashbookListRow = {
  index: number;
  cbkRefId: string;
  acmAcctCodeBank: string;
  cbkTransPeriod: string | null;
  cbkTransRef: string | null;
  cbkTransDate: string | null;
  cbkDebitAmt: number;
  cbkCreditAmt: number;
  cbkPaytoName: string;
  cbkReconStatus: "MATCHED" | "UNMATCHED" | string;
  cbkReconFlag: string;
  cbkSubsystemId: string | null;
  cbkType: string;
};

export type CashbookListType = "DAILY" | "MONTHLY";

export type CashbookListOptions = {
  smartFilter: {
    accountCode: CashbookOption[];
    period: CashbookOption[];
    reconStatus: CashbookOption[];
    reconFlag: CashbookOption[];
    type: CashbookOption[];
  };
};

// ─── FIMS Account Payable ───────────────────────────────────────────────────
// Shared lookup option shape for AP dropdowns.
export type ApOption<TId = string | number> = { id: TId; label: string };

// Payee Registration (Others) — PAGEID 1403 / MENUID 1711 (read-only).
export type PayeeRegistrationRow = {
  index: number;
  vcsId: string;
  vcsVendorCode: string | null;
  vcsVendorName: string | null;
  vcsAddr1: string | null;
  vcsAddr2: string | null;
  vcsAddr3: string | null;
  vcsTown: string | null;
  state: string | null;
  vcsState: string | null;
  vendorBank: string | null;
  vcsVendorBank: string | null;
  vcsBankAccno: string | null;
  vcsBillerCode: string | null;
  vcsTelNo: string | null;
  vcsEmailAddress: string | null;
  vcsContactPerson: string | null;
  vcsIcNo: string | null;
  vcsRegistrationNo: string | null;
  vcsVendorStatus: "ACTIVE" | "INACTIVE";
  vcsVendorStatusValue: number;
};

export type PayeeRegistrationOptions = {
  smartFilter: {
    payeeCode: ApOption[];
    state: ApOption[];
    status: ApOption[];
    yearRegister: ApOption<number>[];
  };
};

// Utility Registration — PAGEID 2881 / MENUID 3466.
export type UtilityRegistrationRow = {
  index: number;
  vcsId: string;
  vcsVendorCode: string | null;
  vcsVendorName: string | null;
  vcsBillerCode: string | null;
  vcsVendorStatus: "ACTIVE" | "INACTIVE";
  vcsVendorStatusValue: number;
};

export type UtilityRegistrationDetail = {
  vcsId: string;
  vcsVendorCode: string | null;
  vcsVendorName: string;
  vcsBillerCode: string;
  vcsVendorStatus: number;
};

export type UtilityRegistrationInput = {
  vcsVendorName: string;
  vcsBillerCode: string;
  vcsVendorStatus: 0 | 1;
};

// Account Bank by Payee — PAGEID 2262 / MENUID 2751 (read-only).
export type AccountBankPayeeType = "A" | "B" | "CDG" | "E" | "F";

export type AccountBankByPayeeGenericRow = {
  index: number;
  name: string;
  status: string | null;
  acctCode: string | null;
  acctName: string | null;
  acctNo: string | null;
};

export type AccountBankByPayeeSponsorRow = {
  index: number;
  spnSponsorCode: string;
  spnSponsorName: string | null;
  spnBankNameCd: string | null;
  spnBankAccNo: string | null;
  spnAddress1: string | null;
  spnAddress2: string | null;
  spnCity: string | null;
  spnPostcode: string | null;
  spnState: string | null;
  spnContactPerson: string | null;
  spnContactNo: string | null;
};

export type AccountBankByPayeeInvestmentRow = {
  index: number;
  iitInstCode: string;
  iitInstName: string | null;
  bnmBankCode: string | null;
  bnmShortname: string | null;
  bankName: string | null;
  iitAddress1: string | null;
  iitAddress2: string | null;
  iitAddress3: string | null;
  iitCity: string | null;
  iitPcode: string | null;
  iitState: string | null;
  iitCountry: string | null;
};

export type AccountBankByPayeeOptions = {
  payeeType: ApOption[];
  smartFilter: {
    name?: ApOption[];
    status?: ApOption[];
    accountName?: ApOption[];
    sponsorCode?: ApOption[];
    sponsorName?: ApOption[];
    bankName?: ApOption[];
    instCode?: ApOption[];
    bankCode?: ApOption[];
  };
};

// Account Bank Updated — PAGEID 1719 / MENUID 2078. Payee-type + payee-ID
// driven lists of bills / vouchers whose line-level bank details drift from
// the payee master, with bulk resync actions.
export type AccountBankUpdatedPayeeType = "A" | "B" | "C" | "D" | "E" | "G";

export type AccountBankUpdatedOptions = {
  payeeType: ApOption[];
  ids: ApOption[];
};

export type AccountBankUpdatedBillRow = {
  index: number;
  billId: string;
  billNo: string | null;
  billDesc: string | null;
  payeeType: string | null;
  payeeId: string | null;
  payeeName: string | null;
  currentBank: string | null;
  currentAccNo: string | null;
  newBank: string | null;
  newAccNo: string | null;
};

export type AccountBankUpdatedVoucherRow = {
  index: number;
  voucherId: string;
  voucherNo: string | null;
  voucherDesc: string | null;
  payeeType: string | null;
  payeeId: string | null;
  payeeName: string | null;
  currentBank: string | null;
  currentAccNo: string | null;
  newBank: string | null;
  newAccNo: string | null;
};

export type AccountBankUpdatedProcessInput = {
  payeeType: AccountBankUpdatedPayeeType;
  ids: string[];
};

export type AccountBankUpdatedProcessResult = {
  success: boolean;
  affected: number;
  message: string;
};

// ─── FIMS Account Receivable ────────────────────────────────────────────────
// Shared lookup option shape for AR dropdowns.
export type ArOption<TId = string | number> = { id: TId; label: string };

// Debtor — PAGEID 1415 / MENUID 1727.
// Backend source: DebtorController (BL DT_AR_DEBTOR).
export type DebtorRow = {
  index: number;
  id: number;
  vendorCode: string;
  vendorName: string | null;
  status: string | null;
  outstandingAmount: number;
};

export type DebtorOptions = {
  status: ArOption[];
};

// Cashbook PTJ — PAGEID 2048 / MENUID 1049.
// Backend source: CashbookPtjController (BL MZ_BL_AR_CASHBOOK_LISTING).
export type CashbookPtjRow = {
  index: number;
  staffId: string;
  staffName: string | null;
  staffPtj: string | null;
  applicationNo: string | null;
  purpose: string | null;
  collectionAmount: number;
  receiptType: "Offline" | "Preprinted" | string;
};

// ─── AR Notes: Credit / Debit / Discount ────────────────────────────────────
// All three note lists share a shape: master id + note no + date + customer
// + linked invoice + three money fields (invoice total, invoice balance,
// note total) + status label decoded from the extended-field JSON.
//
// Backend sources: CreditNoteController / DebitNoteController /
// DiscountNoteController, based on FIMS BL DT_AR_{CREDIT,DEBIT}_NOTE_LIST
// and the null-title discount-note list body (idx-35 in the BL export).

type BaseNoteRow = {
  index: number;
  id: string;
  customerId: string | null;
  customerName: string | null;
  customerType: string | null;
  description: string | null;
  invoiceNo: string | null;
  invoiceTotalAmount: number;
  invoiceBalanceAmount: number;
  status: string | null;
};

export type CreditNoteRow = BaseNoteRow & {
  creditNoteNo: string | null;
  creditNoteDate: string | null;
  creditNoteTotalAmount: number;
};

export type DebitNoteRow = BaseNoteRow & {
  debitNoteNo: string | null;
  debitNoteDate: string | null;
  debitNoteTotalAmount: number;
};

export type DiscountNoteRow = BaseNoteRow & {
  discountNoteNo: string | null;
  discountNoteDate: string | null;
  discountNoteTotalAmount: number;
};

// Aggregate footer carried on `meta.footer` for note list responses. Legacy
// FIMS renders these three totals under the datatable.
export type NoteListFooter = {
  invoiceTotalAmount: number;
  invoiceBalanceAmount: number;
  creditNoteTotalAmount?: number;
  debitNoteTotalAmount?: number;
  discountNoteTotalAmount?: number;
};

// Authorized Receipting — PAGEID 1613 / MENUID 1952. Workflow applications
// that grant staff the authority to issue receipts for a PTJ/event/position.
// Backend source: AuthorizedReceiptingController (BL V2_AUTHORIZED_RECEIPTING_API).
export type AuthorizedReceiptingRow = {
  index: number;
  id: string;
  applicationNo: string | null;
  staffId: string | null;
  staffName: string | null;
  ptjCode: string | null;
  ptjDescription: string | null;
  eventCode: string | null;
  eventDescription: string | null;
  positionCode: string | null;
  positionDescription: string | null;
  reason: string | null;
  status: string | null;
  requestedAt: string | null;
};

export type AuthorizedReceiptingOptions = {
  status: ArOption[];
  ptj: ArOption[];
  event: ArOption[];
  position: ArOption[];
};

// ─── AR Credit Note Form + Debit Note Form ──────────────────────────────────
// Forms are symmetric: the master is a credit/debit note, lines are picked
// from the source customer invoice's `cust_invoice_details`. Debit-side and
// credit-side lines are stored in the same details table keyed on
// `{c,d}nd_transaction_type` ('DT' | 'CR').
//
// Backend sources:
//   CreditNoteFormController / DebitNoteFormController
//   BL DT_AR_CREDIT_NOTE_FORM / DT_AR_DEBIT_NOTE_FORM
//
// Workflow-related endpoints (submit / cancel / process-flow) are shipped as
// stubs — the FIMS workflow engine is not yet migrated. `submit` flips the
// master status to 'Entry' (same as the legacy update after workflowSubmit),
// `cancel` flips it to 'CANCELLED' with a reason, and `process-flow` returns
// an empty list. See controller docblocks.

export type InvoiceLine = {
  ID: string;
  invoiceId: string;
  feeCategoryId: string | null;
  feeCategory: string | null;
  cii_item_code: string | null;
  feeItem: string | null;
  fty_fund_type: string | null;
  fundType: string | null;
  at_activity_code: string | null;
  activityCode: string | null;
  oun_code: string | null;
  ptjCode: string | null;
  ccr_costcentre: string | null;
  costcentre: string | null;
  cpa_project_no: string | null;
  codeSO: string | null;
  acm_acct_code: string | null;
  acctCode: string | null;
  taxCode: string | null;
  taxAmt: number;
  amt: number;
  totalAmt: number;
  cnAmt?: number;
  dnAmt: number;
  dcAmt: number;
  balance?: number;
  transactionType: "DT" | "CR" | string;
};

export type InvoiceLinesResponse = {
  debit: InvoiceLine[];
  credit: InvoiceLine[];
  invoiceBalance: number;
};

// A persisted / draft line on the form. Merges the invoice lookup fields
// with note-specific ones (the amount the user entered to credit/debit, the
// running balance, etc.). Used for both tabs of the form.
export type NoteFormLine = {
  cnd_id?: string;
  dnd_id?: string;
  ID: string;
  feeCategoryId: string | null;
  feeCategory: string | null;
  cii_item_code: string | null;
  feeItem: string | null;
  cnd_detail_desc?: string | null;
  fty_fund_type: string | null;
  fundType: string | null;
  at_activity_code: string | null;
  activityCode: string | null;
  oun_code: string | null;
  ptjCode: string | null;
  ccr_costcentre: string | null;
  costcentre: string | null;
  cpa_project_no: string | null;
  codeSO: string | null;
  acm_acct_code: string | null;
  acctCode: string | null;
  taxCode: string | null;
  taxAmt: number;
  amt: number;
  balance: number;
  // Credit Note uses cnAmt for "user-entered credit amount".
  // Debit Note uses dnAmt for "user-entered debit amount".
  cnAmt?: number;
  dnAmt?: number;
  // Proportional CN/DN tax on the edited amount:
  //   cnTaxAmt = (cnAmt / amt) * taxAmt  (when amt > 0)
  // Computed client-side for display; persisted as `cnd_cn_taxamt`
  // (CN) / `dnd_cn_taxamt` (DN) on save.
  cnTaxAmt?: number;
  dnTaxAmt?: number;
};

/**
 * Small `{value,label}` option used by the AR note-form debtor-type
 * dropdown (`GET /account-receivable/lookup/customer-type`). `value`
 * is the `CODE#Label` composite stored in `cnm_cust_type`.
 */
export type LookupOption = {
  value: string;
  label: string;
  code?: string;
};

/**
 * Autosuggest row for AR note-form Debtor Name dropdown
 * (`GET /account-receivable/credit-note-form/search-debtor`).
 * `value` is the legacy `sddCustomerName` composite `code#name`.
 */
export type DebtorSearchOption = {
  value: string;
  label: string;
  code: string;
  name: string;
};

/**
 * Autosuggest row for AR note-form Invoice No dropdown
 * (`GET /account-receivable/credit-note-form/search-invoice?cust_id=...`).
 * `value` is the legacy `sddInvoiceNo` composite `invoiceId#invoiceNo`.
 */
export type InvoiceSearchOption = {
  value: string;
  label: string;
  invoiceId: string;
  invoiceNo: string;
  balance: number;
  total: number;
};

export type CreditNoteFormHead = {
  cnm_credit_note_master_id: string | null;
  cnm_crnote_no: string | null;
  cim_invoice_no: string | null;
  cim_cust_invoice_id: string | null;
  cnm_cust_id: string | null;
  cnm_cust_type: string | null;
  cnm_cust_type_desc: string | null;
  cnm_cust_name: string | null;
  cnm_crnote_desc: string | null;
  cnm_crnote_date: string | null;
  cnm_cn_total_amount: number;
  cnm_status_cd: string | null;
  cnm_status_cd_desc: string | null;
  invoiceTotalAmount: number;
  invoiceBalanceAmount: number;
};

export type CreditNoteFormData = {
  head: CreditNoteFormHead;
  debit: NoteFormLine[];
  credit: NoteFormLine[];
};

export type SaveCreditNoteResponse = {
  status: string;
  cnID: string;
  creditNoteNo: string | null;
  status_cd: string;
};

export type DebitNoteFormHead = {
  dnm_debit_note_master_id: string | null;
  dnm_dnnote_no: string | null;
  cim_invoice_no: string | null;
  cim_cust_invoice_id: string | null;
  dnm_cust_id: string | null;
  dnm_cust_type: string | null;
  dnm_cust_type_desc: string | null;
  dnm_cust_name: string | null;
  dnm_dnnote_desc: string | null;
  dnm_dnnote_date: string | null;
  dnm_dn_total_amount: number;
  dnm_status_cd: string | null;
  dnm_status_cd_desc: string | null;
  invoiceTotalAmount: number;
  invoiceBalanceAmount: number;
};

export type DebitNoteFormData = {
  head: DebitNoteFormHead;
  debit: NoteFormLine[];
  credit: NoteFormLine[];
};

export type SaveDebitNoteResponse = {
  status: string;
  dnID: string;
  debitNoteNo: string | null;
  status_cd: string;
};

// ─── AR Discount Note Form ─────────────────────────────────────────────────
// Discount Note (MENUID 1784) behaves like the Credit / Debit Note forms
// but is keyed on a `dcp_dc_policy_id` that drives which invoice fee items
// are discountable. The debit/credit line shape therefore carries two
// extra fields: `dcType` (policy description) + `dcTaxAmt`.
// Workflow endpoints (submit/cancel/process-flow) are stubbed — see
// `DiscountNoteFormController` docblock for the rationale.

export type DiscountInvoiceLine = InvoiceLine & {
  dcType?: string | null;
  dcRate?: number;
  dcTaxAmt?: number;
};

/**
 * Dropdown row for the `Discount Policy *` combobox
 * (`GET /account-receivable/discount-note-form/discount-policies`).
 * `value` is the bare `dcp_dc_policy_id` because the master table stores it
 * as an integer, not a composite.
 */
export type DiscountPolicyOption = {
  value: string;
  label: string;
  policyId: string;
  dcType: string;
  description: string;
  rate: number;
};

export type DiscountInvoiceLinesResponse = {
  debit: DiscountInvoiceLine[];
  credit: DiscountInvoiceLine[];
  invoiceBalance: number;
  dcAmtTotal: number;
};

export type DiscountNoteFormLine = {
  dcd_id?: string;
  ID: string;
  feeCategoryId: string | null;
  feeCategory: string | null;
  cii_item_code: string | null;
  feeItem: string | null;
  dcd_detail_desc?: string | null;
  dcType?: string | null;
  fty_fund_type: string | null;
  fundType: string | null;
  at_activity_code: string | null;
  activityCode: string | null;
  oun_code: string | null;
  ptjCode: string | null;
  ccr_costcentre: string | null;
  costcentre: string | null;
  cpa_project_no: string | null;
  codeSO: string | null;
  acm_acct_code: string | null;
  acctCode: string | null;
  taxCode: string | null;
  taxAmt: number;
  amt: number;
  dcAmt: number;
  dcTaxAmt?: number;
  balance: number;
};

export type DiscountNoteFormHead = {
  dcm_discount_note_master_id: string | null;
  dcm_dcnote_no: string | null;
  cim_invoice_no: string | null;
  cim_cust_invoice_id: string | null;
  dcm_cust_id: string | null;
  dcm_cust_type: string | null;
  dcm_cust_type_desc: string | null;
  dcm_cust_name: string | null;
  dcm_dcnote_desc: string | null;
  dcm_dcnote_date: string | null;
  dcm_dc_total_amount: number;
  dcm_status_cd: string | null;
  dcm_status_cd_desc: string | null;
  dcp_dc_policy_id: string | null;
  invoiceTotalAmount: number;
  invoiceBalanceAmount: number;
};

export type DiscountNoteFormData = {
  head: DiscountNoteFormHead;
  debit: DiscountNoteFormLine[];
  credit: DiscountNoteFormLine[];
};

export type SaveDiscountNoteResponse = {
  status: string;
  dcID: string;
  discountNoteNo: string | null;
  status_cd: string;
};

// ─── AR Authorized Receipting Form ─────────────────────────────────────────
// Authorized Receipting (MENUID 1953) authorizes a staff member to issue
// offline receipts for a given PTJ / event / position range. The form is a
// master (`authorized_receipting`) + a `dt_authorized` grid of
// `offline_receipt_staff` rows. Workflow endpoints are stubbed — see
// `AuthorizedReceiptingFormController` docblock.

export type AuthorizedReceiptingStaffRow = {
  ors_id?: string;
  ors_staff_id: string;
  ors_staff_name?: string | null;
  ors_ic?: string | null;
  ors_oun_code?: string | null;
  ors_contact_no?: string | null;
  ors_fax_no?: string | null;
  ors_email?: string | null;
  ors_position?: string | null;
  ors_position_desc?: string | null;
  sts_jobcode?: string | null;
  sts_job_desc?: string | null;
  ors_process_flag?: string | null;
  ors_reason?: string | null;
  ors_reference_no?: string | null;
};

export type AuthorizedReceiptingFormHead = {
  are_authorized_receipting_id: string | null;
  are_application_no: string | null;
  stf_staff_id: string | null;
  stf_staff_name?: string | null;
  oun_code_ptj: string | null;
  oun_code_ptj_desc?: string | null;
  are_position_code: string | null;
  are_event_code: string | null;
  are_reason: string | null;
  are_purposed_code: string | null;
  are_employment_code: string | null;
  are_duration_from: string | null;
  are_duration_to: string | null;
  are_status: string | null;
  are_counter_no?: string | null;
  are_receipt_type?: string | null;
};

export type AuthorizedReceiptingFormData = {
  head: AuthorizedReceiptingFormHead;
  dt_authorized: AuthorizedReceiptingStaffRow[];
};

export type SaveAuthorizedReceiptingResponse = {
  status: string;
  are_authorized_receipting_id: string;
  are_application_no: string | null;
  are_status: string;
};

// Current-user staff profile used by the read-only "Details" card on the
// Authorized Receipting Form (legacy equivalents: `$_USER['STAFF_ID']`,
// `$_USER['STAFF_NAME']`, `$_USER['PTJ']`, `$_USER['STAFF_POSITION_ACTUAL']`,
// `$_USER['JOB_GROUP_DESC']`, `$_USER['STAFF_NRIC']`). `resolved=false` means
// the logged-in Laravel user could not be matched against `staff` /
// `staff_service` — the caller should treat the remaining fields as hints
// only.
export type CurrentStaffProfile = {
  stf_staff_id: string | null;
  stf_staff_name: string | null;
  stf_ic_no: string | null;
  stf_email_addr: string | null;
  oun_code_ptj: string | null;
  oun_code_ptj_desc: string | null;
  stf_position: string | null;
  stf_position_desc: string | null;
  stf_employment_status: string | null;
  stf_jobcode: string | null;
  stf_job_desc: string | null;
  stf_telno_work: string | null;
  stf_fax_no: string | null;
  resolved: boolean;
};

// Event autosuggest option (capital_project rows where cpa_source='EVENT').
export type ArEventSearchOption = {
  value: string;
  label: string;
  projectNo: string;
  projectDesc: string | null;
  ptj: string | null;
  startDate: string | null;
  endDate: string | null;
};

// Staff autosuggest option for the "+ New" authorized-staff modal.
export type ArStaffSearchOption = {
  value: string;
  label: string;
  staffId: string;
  staffName: string | null;
  ic: string | null;
  contact: string | null;
  fax: string | null;
  email: string | null;
  ptj: string | null;
  positionCode: string | null;
  positionDesc: string | null;
  position: string | null;
  jobcodeCode: string | null;
  jobcodeDesc: string | null;
  jobcode: string | null;
};

// ─── FIMS Credit Control ────────────────────────────────────────────────────
// Shared autosuggest / filter option shape.
export type CcOption<TId = string | number> = { id: TId; label: string };

// Deposit listing — PAGEID 1445 / MENUID 1809.
// Backend: DepositController (BL ZR_CREDITCONTROL_DEPOSIT_API).
export type DepositRow = {
  index: number;
  dpmDepositMasterId: number;
  transactionDate: string | null;
  dpmDepositNo: string | null;
  dpmPaytoType: string | null;
  vcsVendorCode: string | null;
  dpmVendorName: string | null;
  dpmRefNo: string | null;
  dpmRefNoNote: string | null;
  ddtDocNo: string | null;
  ftyFundType: string | null;
  atActivityCode: string | null;
  ounCode: string | null;
  ccrCostcentre: string | null;
  acmAcctCode: string | null;
  acmAcctDesc: string | null;
  ddtCurrencyCode: string | null;
  ddtEntAmt: number;
  ddtAmt: number;
  ddtType: string | null;
  dpmStatus: string | null;
};

export type DepositOptions = {
  category: CcOption[];
  payToType: CcOption[];
  currency: CcOption[];
  ptj: CcOption[];
};

export type ListOfDepositOptions = {
  category: CcOption[];
  customerType: CcOption[];
  ptj: CcOption[];
};

// Invoice Balance — PAGEID 2561 / MENUID 3388.
// Backend: InvoiceBalanceController (BL MZS_API_CC_INVOICE_BALANCE).
export type InvoiceBalanceRow = {
  index: number;
  pdePaytoType: string | null;
  pdePaytoTypeDesc: string | null;
  pdePaytoId: string | null;
  pdePaytoName: string | null;
  ftyFundType: string | null;
  ounCode: string | null;
  atActivityCode: string | null;
  ccrCostcentre: string | null;
  acmAcctCode: string | null;
  pdeDocumentNo: string | null;
  pdeTransDate: string | null;
  docDescription: string | null;
  pdeTransAmt: number;
  balance: number;
};

export type InvoiceBalanceOptions = {
  customerType: CcOption[];
};

// Detail of Deposit — PAGEID 2688 / MENUID 3397.
// Backend: DepositFormController (BL NAD_API_CC_DEPOSIT_DETAILS).
export type DepositFormMaster = {
  dpmDepositMasterId: number;
  dpmDepositNo: string | null;
  dpmRefNoNote: string | null;
  dpmStartDate: string | null;
  dpmEndDate: string | null;
  dpmDepositCategory: string | null;
  dpmDepositCategoryDesc: string | null;
  dpmPaytoType: string | null;
  dpmPaytoTypeDesc: string | null;
  vcsVendorCode: string | null;
  dpmVendorName: string | null;
  dpmRefNo: string | null;
  dpmContractNo: string | null;
  dpmStatus: string | null;
};

export type DepositFormMasterInput = {
  dpmRefNoNote?: string | null;
  dpmPaytoType?: string | null;
  vcsVendorCode?: string | null;
  dpmVendorName?: string | null;
  dpmContractNo?: string | null;
  dpmStartDate?: string | null;
  dpmEndDate?: string | null;
};

export type DepositDetailRow = {
  index: number;
  ddtDepositDetlId: number;
  dpmDepositMasterId: number;
  ddtDocNo: string | null;
  ddtDescription: string | null;
  ftyFundType: string | null;
  atActivityCode: string | null;
  ounCode: string | null;
  ccrCostcentre: string | null;
  acmAcctCode: string | null;
  ddtTransactionRef: string | null;
  ddtCurrencyCode: string | null;
  ddtEntAmt: number;
  debitEntAmt: number;
  creditEntAmt: number;
  debitAmt: number;
  creditAmt: number;
  ddtType: string | null;
};

export type DepositDetailInput = {
  ddtDescription?: string | null;
  ddtCurrencyCode?: string | null;
  ddtEntAmt?: number | null;
  ddtTransactionRef?: string | null;
  dpmRefNo?: string | null;
};

// Customer autosuggest option shared across Credit Control endpoints.
export type CcCustomerOption = {
  id: string;
  label: string;
  name: string;
};

// ─── FIMS Portal ───────────────────────────────────────────────────────────
// Read-only self-service listings for vendors/debtors logged into the Portal.

// Debtor Portal > List of Profile Update Application (PAGEID 2155 / MENUID 2608).
// Source: BL MZ_BL_DEBTOR_PORTAL_LIST.
export type DebtorProfileUpdateRow = {
  index: number;
  vendorCode: string;
  vendorName: string | null;
  vendorStatus: "ACTIVE" | "NON-ACTIVE";
  isCreditor: "YES" | "NO";
  bankName: string | null;
  bankAccountNo: string | null;
  statusUpdateDebtor: string | null;
  createdDate: string | null;
};

// Vendor Portal > Tender/Quotation List (PAGEID 2278 / MENUID 2767).
// Source: BL NF_BL_PURCHASING_VENDOR_PORTAL_TENDER.
export type TenderQuotationRow = {
  index: number;
  tenderId: number;
  tenderNo: string | null;
  briefingRefNo: string | null;
  tenderType: string | null;
  startDate: string | null;
  endDate: string | null;
  title: string | null;
  estimatedAmount: number | null;
  amountDoc: number | null;
  status: string | null;
  briefingCloseDate: string | null;
  tenderOpenStart: string | null;
  tenderOpenClose: string | null;
  editable: boolean;
};

// Result of the vendor status pre-check used by the Buy Document flow.
export type VendorStatusCheck = {
  vendorCode: string | null;
  restrictedStatus: string | null;
  canBuyDocument: boolean;
};

// Vendor Portal > Online Registration Fee History (PAGEID 1654 / MENUID 2003).
// Source BL NF_BL_VENDOR_ONLINE_PAYMENT is unavailable; shape is derived from
// the legacy datatable spec and the commented-out SQL inside the Tender BL.
export type VendorRegistrationFeeStatus =
  | "Successful"
  | "Pending"
  | "Unsuccessful"
  | "Pending Authorization";

export type VendorRegistrationFeeRow = {
  index: number;
  checkoutDate: string | null;
  transactionDate: string | null;
  referenceNo: string | null;
  receiptNo: string | null;
  creditorId: string | null;
  vendorName: string | null;
  description: string | null;
  transactionAmount: number | null;
  statusCode: string;
  statusDesc: VendorRegistrationFeeStatus;
};

// Debtor Portal > Financial Information > Reminder (MENUID 2584).
// Legacy BL: NF_BL_DEBTOR_PORTAL_REMINDER.
export type DebtorReminderRow = {
  index: number;
  id: number;
  invoiceNo: string | null;
  amountOutstanding: string | null;
  reminderDate: string | null;
  reminderBil: number | null;
  emailAddress: string | null;
  notificationMethod: string | null;
  custInvoiceId: number | null;
};

// Debtor Portal > Financial Information > Debtors Statement (MENUID 2267).
// Legacy BL: NF_BL_DP_DEBTORS_STATEMENT. `outstanding` and `advance` are
// running totals computed server-side from the ordered ledger.
export type DebtorStatementRow = {
  index: number;
  transactionDate: string | null;
  documentNo: string | null;
  refNo: string | null;
  description: string | null;
  debit: number;
  credit: number;
  cn: number;
  dn: number;
  dc: number;
  advance: number;
  outstanding: number;
};

export type DebtorStatementFooter = {
  debit: number;
  credit: number;
  cn: number;
  dn: number;
  dc: number;
  advance: number;
  balance: number;
};

// Student Finance > PTPTN Data (PAGEID 857 / MENUID 1031).
// Source: FIMS BL `API_PTPTN_DATA`. `isProcessed` ('Y' | 'N') gates the
// Delete action client- and server-side.
export type PtptnDataRow = {
  index: number;
  mID: number;
  referenceNo: string | null;
  date: string;
  fileName: string | null;
  source: string | null;
  totalStudent: number | null;
  totalWarrant: number | null;
  deductAmt: number | null;
  balanceAmt: number | null;
  isProcessed: string;
  isInvGenComplete: string;
  isExportComplete: string;
};

export type PtptnDataHeader = {
  mID: number;
  referenceNo: string | null;
  date: string | null;
  fileName: string | null;
  source: string | null;
  totalStudent: number | null;
  totalWarrant: number | null;
  deductAmt: number | null;
  balanceAmt: number | null;
  isProcessed: string;
  isInvGenComplete: string;
  isExportComplete: string;
};

export type PtptnDataDetail = {
  id: number;
  studentId: string | null;
  studentName: string | null;
  studentIc: string | null;
  uniCode: string | null;
  studentGrp: string | null;
  warrantNo: string | null;
  warrantAmt: number | null;
  deductionAmt: number | null;
  balanceAmt: number | null;
  statusPtptn: string | null;
  payDate: string | null;
  invoiceNo: string | null;
  invoiceAmt: number | null;
  creditStatus: string | null;
};

// Purchasing > Status PO & PR (PAGEID 1520 / MENUID 1841).
// Source: FIMS BL ZR_PURCHASING_STATUSPOPR_API. Read-only listing joining
// purchase_order_master + details + vendor + bills + requisition tables.
export type StatusPoPrRow = {
  index: number;
  pomOrderId: number | null;
  rqmRequisitionId: number | null;
  poNo: string | null;
  prNo: string | null;
  description: string | null;
  itemCode: string | null;
  itemDesc: string | null;
  poStatus: string | null;
  vendorCode: string | null;
  vendorName: string | null;
  billNo: string | null;
  requestDate: string;
  urlViewPo: string | null;
  urlViewPr: string | null;
};

export type StatusPoPrOptions = {
  poStatus: string[];
};

export type StatusPoPrSmartFilter = {
  poNo: string;
  prNo: string;
  vendorCode: string;
  poStatus: string;
  dateStart: string;
  dateEnd: string;
};

// General Ledger > Journal Listing (PAGEID 1700 / MENUID 2056).
// Source: FIMS BL `SNA_API_GLREPORT_JOURNAL_LISTING`. Read-only listing
// with a View-details modal (DR + CR sub-tables) and a delete action
// gated client- and server-side against posted / cancelled journals.
export type JournalListingRow = {
  index: number;
  mjmJournalId: number;
  journalNo: string | null;
  description: string | null;
  typeOfJournal: string | null;
  amount: number | null;
  status: string | null;
  systemId: string | null;
  dateJournal: string | null;
  createdBy: string | null;
};

export type JournalListingOptions = {
  types: string[];
  statuses: string[];
  systemIds: string[];
};

export type JournalListingSmartFilter = {
  year: string;
  typeOfJournal: string;
  description: string;
  dateJournal: string;
  status: string;
  systemId: string;
};

export type JournalListingHeader = {
  mjmJournalId: number;
  journalNo: string | null;
  description: string | null;
  typeOfJournal: string | null;
  amount: number | null;
  status: string | null;
  systemId: string | null;
  dateJournal: string | null;
  createdBy: string | null;
  sumDebit: number;
  sumCredit: number;
};

export type JournalListingLine = {
  id: number;
  ounCode: string | null;
  fundType: string | null;
  activityCode: string | null;
  costCentre: string | null;
  acctCode: string | null;
  documentNo: string | null;
  amount: number | null;
  codeSo: string | null;
  projectNo: string | null;
  taxcode: string | null;
  status: string | null;
  reference: string | null;
  paytoId: string | null;
  paytoType: string | null;
  paytoName: string | null;
  source: string | null;
};

// General Ledger > Manual Journal Listing (PAGEID 1729 / MENUID 2089).
// Source: FIMS BL `V2_GL_JOURNAL_API` (endpoints ?listing=1 and
// ?listing_delete=1). Read + DRAFT-only delete. The legacy page feeds the
// Top Filter's Type-of-Journal dropdown as a required `mjm_typeofjournal`
// query param; when empty the listing simply returns nothing (same as
// legacy BL). Listing PDF mirrors `custom/report/Manual Journal/downloadListPDF.php`
// (toolbar button) and the per-row Journal PDF mirrors
// `custom/report/Manual Journal/downloadPDFmj.php` (row action). Edit /
// View / Duplicate row actions are deferred until MENUID 2090 lands.
export type ManualJournalRow = {
  index: number;
  mjmJournalId: number;
  journalNo: string | null;
  description: string | null;
  typeOfJournal: string | null;
  amount: number | null;
  status: string | null;
  systemId: string | null;
  dateJournal: string | null;
  createdBy: string | null;
  createdDate: string | null;
  wasNotes: string | null;
};

export type ManualJournalTypeOption = {
  code: string;
  label: string;
};

export type ManualJournalOptions = {
  types: ManualJournalTypeOption[];
  statuses: string[];
};

export type ManualJournalSmartFilter = {
  enterDateFrom: string;
  enterDateTo: string;
  status: string;
  totalAmtFrom: string;
  totalAmtTo: string;
};

/** Row shape returned by `/api/general-ledger/manual-journal/listing-pdf`
 * — the toolbar-level "Download PDF" button. Columns match the legacy
 * `downloadListPDF.php` output exactly.
 */
export type ManualJournalListingPdfRow = {
  index: number;
  journalNo: string;
  description: string;
  amount: number;
  status: string;
  dateJournal: string;
  createdBy: string;
};

export type ManualJournalListingPdfFilters = {
  typeOfJournal: string;
  globalSearch: string;
  dateFrom: string;
  dateTo: string;
  status: string;
  amountFrom: string;
  amountTo: string;
};

export type ManualJournalListingPdfPayload = {
  rows: ManualJournalListingPdfRow[];
  filters: ManualJournalListingPdfFilters;
  generatedAt: string;
  typeLabel: string;
  truncated?: boolean;
  limit?: number;
};

/** Per-row Journal PDF payload backing `downloadPDFmj.php`. The backend
 * resolves the workflow signers (Input / Verifier / Approver) from
 * `wf_application_status` + `wf_process` + `staff_service`.
 */
export type ManualJournalDetailHeader = {
  journalId: number;
  journalNo: string;
  journalDesc: string;
  typeOfJournal: string;
  status: string;
  systemId: string;
  enterDate: string;
  enterMonth: string;
  createdBy: string;
  createdByName: string | null;
  createdDate: string;
  createdTime: string;
  organization: string;
  hasHumanApprover: boolean;
};

export type ManualJournalDetailLine = {
  glStructure: string;
  accountCode: string;
  payToType: string;
  payToId: string;
  documentNo: string;
  reference: string;
  debit: number;
  credit: number;
};

export type ManualJournalDetailProcessStep = {
  processName: string;
  createdByName: string;
  ounDesc: string;
  emailAddr: string;
  telNoWork: string;
  statusDesc: string;
  remark: string;
  createdDate: string;
  createdTime: string;
};

export type ManualJournalDetail = {
  header: ManualJournalDetailHeader;
  lines: ManualJournalDetailLine[];
  totals: { debit: number; credit: number };
  processFlow: ManualJournalDetailProcessStep[];
};

// General Ledger > List of Year and Month (PAGEID 2721 / MENUID 3287).
// Source: FIMS BL `MZ_BL_GL_LIST_YEAR_MONTH`. List + add/edit popup modal;
// no delete endpoint exists in legacy.
export type GlYearMonthRow = {
  index: number;
  gymId: number;
  year: string;
  month: string;
  status: string;
  remark: string | null;
};

export type GlYearMonthDetail = {
  gymId: number;
  year: string;
  month: string;
  status: string;
  remark: string | null;
};

export type GlYearMonthInput = {
  gym_year: string;
  gym_month: string;
  gym_status: "OPEN" | "CLOSE";
  gym_remark?: string | null;
};

export type GlYearMonthLookupOption = {
  value: string;
  label: string;
};

export type GlYearMonthOptions = {
  months: GlYearMonthLookupOption[];
  statuses: GlYearMonthLookupOption[];
};

export type GlYearMonthSmartFilter = {
  year: string;
  month: string;
  status: string;
};

// General Ledger > Posting to GL (TB) (PAGEID 1139 / MENUID 1409).
// Source: FIMS BL `POSTING_TO_TB`. Read-only listing over
// posting_master + posting_details grouped per posting + document +
// references + date, with a View-details modal (DR + CR sub-tables) that
// replaces the legacy deep-link to MENUID 1413 (out of migration scope).
export type PostingToTbRow = {
  index: number;
  pmtPostingId: number;
  postingNo: string | null;
  documentNo: string | null;
  systemId: string | null;
  amountCr: number;
  amountDt: number;
  status: string | null;
  reference: string | null;
  reference1: string | null;
  transDate: string | null;
};

export type PostingToTbOptions = {
  systemIds: string[];
  statuses: string[];
};

export type PostingToTbSmartFilter = {
  systemId: string;
  dateFrom: string;
  dateTo: string;
  totalAmt: string;
};

export type PostingToTbHeader = {
  pmtPostingId: number;
  postingNo: string | null;
  systemId: string | null;
  status: string | null;
  totalAmount: number;
  description: string | null;
  currency: string | null;
  postedDate: string | null;
  postedBy: string | null;
  sumDebit: number;
  sumCredit: number;
};

export type PostingToTbLine = {
  id: number;
  fund: string | null;
  activity: string | null;
  ptj: string | null;
  account: string | null;
  documentNo: string | null;
  reference: string | null;
  reference1: string | null;
  amount: number;
  payTo: string | null;
};

export type PostingToTbFooter = {
  amountDt: number;
  amountCr: number;
};

// General Ledger > General Ledger Listing (PAGEID 2068 / MENUID 2519).
// Source: FIMS BL `NAD_API_GL_LISTINGPOSTINGTOGL`. Read-only line-level
// listing over posting_master + posting_details with the 5-level
// account_main self-join hierarchy. Legacy page had separate Top Filter +
// Smart Filter; here both are consolidated into one smart filter modal
// (same pattern as PostingToTb).
export type GlListingRow = {
  index: number;
  pdePostingDetlId: number;
  pmtPostingId: number;
  postingNo: string | null;
  documentNo: string | null;
  docDescription: string | null;
  fundType: string | null;
  fundDesc: string | null;
  activityCode: string | null;
  activityDesc: string | null;
  ounCode: string | null;
  ounDesc: string | null;
  costCentre: string | null;
  costCentreDesc: string | null;
  soCode: string | null;
  projectDesc: string | null;
  acctCode: string | null;
  acctDesc: string | null;
  acctActivity: string | null;
  acctBehavior: string | null;
  accountClass: string | null;
  accountSubclass: string | null;
  accountSeries: string | null;
  accountSubseries: string | null;
  transAmt: number;
  transType: string | null;
  reference: string | null;
  reference1: string | null;
  postedDate: string | null;
  postedPeriod: string | null;
  transDate: string | null;
  payToType: string | null;
  payToId: string | null;
  createdBy: string | null;
  systemId: string | null;
};

export type GlListingCodeOption = {
  code: string;
  description: string;
};

export type GlListingPtjOption = GlListingCodeOption & {
  parent: string | null;
  level?: number | null;
};

export type GlListingCostCentreOption = GlListingCodeOption & {
  parent: string | null;
};

export type GlListingAccountOption = GlListingCodeOption & {
  parent: string | null;
};

export type GlListingOptions = {
  systemIds: string[];
  fundTypes: GlListingCodeOption[];
  activityCodes: GlListingCodeOption[];
  ptjL3: GlListingPtjOption[];
  ptj: GlListingPtjOption[];
  costCentres: GlListingCostCentreOption[];
  accountsByLevel: Partial<Record<1 | 2 | 3 | 4 | 5, GlListingAccountOption[]>>;
  accountTypes: string[];
  statementItems: string[];
  transTypes: string[];
  payToTypes: GlListingCodeOption[];
};

export type GlListingSmartFilter = {
  systemId: string;
  dateStart: string;
  dateEnd: string;
  fundType: string;
  activityCode: string;
  ounCodeL3: string;
  ounCode: string;
  costCentre: string;
  accountClass: string;
  accountSubclass: string;
  accountSeries: string;
  accountSubseries: string;
  acctCode: string;
  accountType: string;
  payToType: string;
  postingNo: string;
  documentNo: string;
  soCode: string;
  payToId: string;
  reference: string;
  reference1: string;
  transType: string;
  statementItem: string;
};

export type GlListingFooter = {
  transAmt: number;
};
