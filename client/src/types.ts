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

// Student Finance > Student Profile or Ledger (PAGEID 1232 / MENUID 1509).
// Source: FIMS BL `V2_SFSP_LEDGER_API`. Read-only datatable with smart
// filter (Matric / Name / NRIC/Passport / Semester No. / Program Level /
// Status). The View Profile + View Ledger deep-links are not yet migrated.
export type LedgerRow = {
  index: number;
  studentId: string;
  studentName: string | null;
  icPassport: string | null;
  semLevel: string | null;
  programLevel: string | null;
  programLevelLabel: string | null;
  statusDesc: string;
  outstandingAmt: number;
};

export type LedgerOptions = {
  programLevel: ArOption[];
  status: ArOption[];
};

export type LedgerSmartFilter = {
  studentId: string;
  studentName: string;
  icPassport: string;
  semLevel: string;
  programLevel: string;
  statusDesc: string[];
};

// Student Finance > List of Offered (PAGEID 2181 / MENUID 2636).
// Source: FIMS BL `MZ_BL_SF_OFFEREDLIST`. Read-only datatable surfacing
// whichever payment channel (auto-receipt / batch knockoff / manual
// unidentified journal) settled an offered_student row.
export type OfferedStudentRow = {
  index: number;
  matric: string;
  name: string | null;
  icPassport: string | null;
  programLevel: string | null;
  programLevelLabel: string | null;
  offeredSemester: string | null;
  paymentAmt: number | null;
  paymentDate: string | null;
  receiptNo: string | null;
  receiptMasterId: number | null;
};

export type OfferedStudentOptions = {
  programLevel: ArOption[];
  offeredSemester: ArOption[];
};

export type OfferedStudentSmartFilter = {
  programLevel: string;
  offeredSemester: string;
};

// Student Finance > Invoice (PAGEID 828 / MENUID 1023).
// Source: FIMS BLs `DT_SF_INVOICE` (main listing) + `DT_DEBIT_LIST`
// (per-invoice debit detail drilldown). Read-only migration — the
// legacy Invoice Form (menuID 1062) and bulk download / cancel SP
// are NOT migrated yet, so the View / Edit / Delete buttons render
// disabled in InvoiceListView.vue.
export type InvoiceRow = {
  index: number;
  id: number;
  invoiceNo: string | null;
  invoiceDate: string | null;
  invoiceDateIso: string | null;
  status: string | null;
  statusLabel: string | null;
  customerId: string | null;
  customerName: string | null;
  customerType: string | null;
  customerTypeLabel: string;
  semester: string | null;
  batchNo: string | null;
  feeCode: string | null;
  studentStatus: string | null;
  amount: number;
  balance: number;
};

export type InvoiceFooter = {
  totalAmt: number;
};

export type InvoiceOptions = {
  status: ArOption[];
  feeCategory: ArOption[];
  programLevel: ArOption[];
  studentStatus: ArOption[];
  studyCategory: ArOption[];
  citizenship: ArOption[];
  nationality: ArOption[];
  customerType: ArOption[];
};

export type InvoiceSmartFilter = {
  status: string;
  invoiceNo: string;
  feeCategory: string;
  semester: string;
  ptj: string;
  programLevel: string;
  studentStatus: string;
  studyCategory: string;
  citizenship: string;
  nationality: string;
  customerType: string;
  customerId: string;
};

export type InvoiceDebitRow = {
  index: number;
  id: number;
  item: string | null;
  subItem: string | null;
  fundType: string | null;
  activityCode: string | null;
  ptjCode: string | null;
  costcentre: string | null;
  codeSO: string | null;
  acctCode: string | null;
  taxCode: string | null;
  taxAmt: number | null;
  amt: number;
  cnAmt: number;
  dbAmt: number;
  dcAmt: number;
  totalAmt: number;
  balAmt: number;
};

export type InvoiceDebitFooter = {
  amt: number;
  cnAmt: number;
  dbAmt: number;
  dcAmt: number;
  totalAmt: number;
  balAmt: number;
};

export type InvoiceDetails = {
  rows: InvoiceDebitRow[];
  footer: InvoiceDebitFooter;
};

// Student Finance > Invoice Generation (PAGEID 970 / MENUID 1231).
// Source: FIMS BL `CALL_PROC_STUDENT_INVOICE` with 4 actions
// (find / csv / match / generate). The "Search Parameter" form runs
// `invoiceCheckingByBatch` server-side and returns the legacy 8-column
// roster keyed by `uniqueKey`; `uniqueKey` is then passed back to the
// CSV / generate endpoints so they keep operating on the exact roster
// the user just inspected.
export type StudentInvoiceGenerationRow = {
  index: number;
  matric: string;
  name: string | null;
  status: string | null;
  program: string | null;
  intakeCase: string | null;
  citizenship: string | null;
  semesterNo: string | null;
  feeCode: string | null;
};

export type StudentInvoiceGenerationOptions = {
  semester: ArOption[];
  programLevel: ArOption[];
  studentType: ArOption[];
  feeType: ArOption[];
  intakeCase: ArOption[];
};

// Mirrors the legacy form payload (snake_case after CamelCaseMiddleware).
export type StudentInvoiceGenerationSearchInput = {
  semester: string;
  programLevel: string;
  feeType: string;
  studentType?: string;
  intakeCase?: string;
  matricNo?: string;
  page?: number;
  limit?: number;
  q?: string;
  sortBy?: string;
  sortDir?: "asc" | "desc";
};

export type StudentInvoiceGenerationSearchMeta = {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
  uniqueKey: string;
  message: string | null;
};

export type StudentInvoiceGenerationGenerateInput = {
  uniqueKey: string;
  semester: string;
  programLevel: string;
  feeType: string;
  studentType?: string;
  intakeCase?: string;
  matricNo?: string;
};

export type StudentInvoiceGenerationGenerateResult = {
  success: boolean;
  message: string | null;
  workflow: Record<string, unknown> | null;
  taskIds: string[];
};

// Student Finance > Manual Invoice Listing (PAGEID 2389 / MENUID 2897).
// Source: FIMS BL `DT_SF_MANUAL_INV_LISTING`. Scoped to
// cim_system_id='STUD_INV' AND cim_invoice_type='12'. The list meta
// includes a `footer.totalAmt` grand total (same as the legacy BL).
export type ManualInvoiceRow = {
  index: number;
  id: number;
  invoiceNo: string | null;
  invoiceDate: string | null;
  invoiceDateIso: string | null;
  status: string | null;
  debtorId: string | null;
  debtorName: string | null;
  debtorType: string | null;
  debtorTypeLabel: string;
  totalAmt: number;
  crNoteAmt: number;
  dnNoteAmt: number;
  dcNoteAmt: number;
  paidAmt: number;
  balAmt: number;
};

export type ManualInvoiceOptions = {
  debtorType: ArOption[];
  status: ArOption[];
};

export type ManualInvoiceSmartFilter = {
  invoiceDate: string;
  debtorType: string;
  status: string;
};

export type ManualInvoiceFooter = {
  totalAmt: number;
};

// Student Finance > Bank Account Update (PAGEID 977 / MENUID 1081).
// Source: FIMS BL `DT_BANK_ACC_UPDATE`. Read-only datatable joining
// student + stud_account_application + bank_master + academic_calendar.
export type BankAccountUpdateRow = {
  index: number;
  applicationId: number;
  applicationNo: string | null;
  matric: string | null;
  name: string | null;
  icPassport: string | null;
  currentSemester: string | null;
  accountNo: string | null;
  bankName: string | null;
  bankCode: string | null;
  applicationDate: string | null;
  approvedDate: string | null;
  status: string | null;
  statusRaw: string | null;
};

export type BankAccountUpdateOptions = {
  bank: ArOption[];
  status: ArOption[];
};

export type BankAccountUpdateSmartFilter = {
  semester: string;
  bank: string;
  status: string;
};

// Investment > List Of Accrual (PAGEID 1548 / MENUID 1877).
// Legacy BL `API_LIST_OF_ACCRUAL` (action=listing_all_dt). Read-only
// listing joining investment_profile + investment_institution +
// investment_accrual.
export type ListOfAccrualRow = {
  index: number;
  investmentId: number;
  batchNo: string | null;
  institutionCode: string | null;
  institutionDesc: string | null;
  institutionBranch: string | null;
  investmentNo: string | null;
  certificateNo: string | null;
  period: number | null;
  tenureDesc: string | null;
  startDate: string | null;
  endDate: string | null;
  principalAmount: number | null;
  rate: number | null;
  totalSum: number;
  status: string | null;
};

export type ListOfAccrualOptions = {
  institution: ArOption[];
  status: ArOption[];
};

export type ListOfAccrualSmartFilter = {
  batch: string;
  institution: string;
  period: string;
  tenure: string;
  amount: string;
  status: string;
};

// Investment > Summary List of Investments (PAGEID 2316 / MENUID 2808).
// Legacy BL `API_SUMMARY_LIST_OF_NEW_INVESTMENT` (action=listing_all_dt).
export type SummaryListInvestmentRow = {
  index: number;
  investmentId: number;
  batchNo: string | null;
  institutionCode: string | null;
  institutionName: string | null;
  institutionBranch: string | null;
  investmentNo: string | null;
  certificateNo: string | null;
  investmentTypeCode: string | null;
  investmentTypeDesc: string | null;
  fundTypeCode: string | null;
  fundTypeDesc: string | null;
  activityCode: string | null;
  activityDesc: string | null;
  period: number | null;
  tenureDesc: string | null;
  startDate: string | null;
  endDate: string | null;
  principalAmount: number | null;
  rate: number | null;
  status: string | null;
};

export type SummaryListInvestmentOptions = {
  yearOfBatch: ArOption[];
  batchNo: ArOption[];
  bank: ArOption[];
  institution: ArOption[];
  investmentType: ArOption[];
  fundType: ArOption[];
  activity: ArOption[];
  tenure: ArOption[];
  status: ArOption[];
};

export type SummaryListInvestmentSmartFilter = {
  year: string;
  batch: string;
  bank: string;
  institution: string;
  investType: string;
  fundType: string;
  activity: string;
  tenure: string;
  amount: string;
  status: string;
};

// Investment > List of Investments (PAGEID 1174 / MENUID 1448).
// Legacy BL `API_LIST_OF_NEW_INVESTMENT` (action=listing_all_dt).
export type ListOfInvestmentReceipt = {
  receiptNo: string;
  amount: string;
  date: string;
};

export type ListOfInvestmentRow = {
  index: number;
  investmentId: number;
  batchNo: string | null;
  institutionCode: string | null;
  institutionName: string | null;
  institutionBranch: string | null;
  investmentNo: string | null;
  certificateNo: string | null;
  journalNo: string | null;
  journalStatus: string | null;
  period: number | null;
  tenureDesc: string | null;
  startDate: string | null;
  endDate: string | null;
  principalAmount: number | null;
  rate: number | null;
  status: string | null;
  withdrawalType: string | null;
  receipts: ListOfInvestmentReceipt[];
};

export type ListOfInvestmentOptions = {
  prefix: ArOption[];
  batchNo: ArOption[];
  institution: ArOption[];
  status: ArOption[];
};

export type ListOfInvestmentSmartFilter = {
  prefix: string;
  batch: string;
  institution: string;
  periodFrom: string;
  periodTo: string;
  maturedFrom: string;
  maturedTo: string;
  amount: string;
  status: string;
};

// Investment > Investment to be Withdrawn (PAGEID 2895 / MENUID 3485).
// Legacy BL `API_INV_WITHDRAWN`.
export type InvestmentToBeWithdrawnReceipt = {
  receiptNo: string;
  amount: string;
  date: string;
};

export type InvestmentToBeWithdrawnRow = {
  index: number;
  investmentId: number;
  batchNo: string | null;
  institutionCode: string | null;
  institutionName: string | null;
  institutionBranch: string | null;
  investmentNo: string | null;
  certificateNo: string | null;
  journalNo: string | null;
  journalStatus: string | null;
  period: number | null;
  tenureDesc: string | null;
  startDate: string | null;
  endDate: string | null;
  principalAmount: number | null;
  rate: number | null;
  status: string | null;
  // 'WITHDRAWN' when ipf_status_withdraw='APPROVE', otherwise 'RENEW'
  withdrawnLabel: string | null;
  // false when the investment has already been marked as withdrawn
  canWithdraw: boolean;
  receipts: InvestmentToBeWithdrawnReceipt[];
};

export type InvestmentToBeWithdrawnOptions = {
  batchNo: ArOption[];
  institution: ArOption[];
  status: ArOption[];
};

export type InvestmentToBeWithdrawnSmartFilter = {
  batch: string;
  institution: string;
  periodFrom: string;
  periodTo: string;
  amount: string;
  status: string;
};

export type InvestmentToBeWithdrawnModalData = {
  investmentId: number;
  investmentNo: string | null;
  certificateNo: string | null;
  tenure: string | null;
  alreadyWithdrawn: boolean;
};

// Investment > Accrual (PAGEID 1175 / MENUID 1446).
// Legacy BL `API_INVESTMENT_ACCRUAL` (default listing action).
// Read-only datatable joining investment_accrual +
// investment_institution + investment_profile, scoped to
// iac_start_date <= current_date() AND pmt_posting_no IS NULL.
export type InvestmentAccrualRow = {
  index: number;
  accrualId: number | null;
  // Legacy row.ID = `${ipf_investment_no}_${iac_id}` — exposed for the
  // write flow payload once Post-to-TB lands. Unused in the read view.
  rowId: string | null;
  investmentNo: string | null;
  institutionCode: string | null;
  institutionName: string | null;
  institutionBranch: string | null;
  startDate: string | null;
  endDate: string | null;
  createdDate: string | null;
  amount: number | null;
  noOfDays: number | null;
  amtPerDay: number | null;
  rate: number | null;
  // Hidden column in legacy UI (dt_class="d-none"); kept for clients
  // that need the reference but the migrated view does not render it.
  postingNo: string | null;
};

export type InvestmentAccrualOptions = {
  institution: ArOption[];
};

export type InvestmentAccrualSmartFilter = {
  investNo: string;
  instCode: string;
  instName: string;
  branch: string;
  noOfDays: string;
  rate: string;
};

// POST /api/investment/accrual/post-to-tb — fans out the legacy
// INSERT_UPDATE_INVESTMENT_ACCRUAL default branch per selected iac_id.
// Each row either lands in `processed` (with the generated
// pmt_posting_no) or `failed` (with a reason — e.g. start date
// still today, no matching investment_acct_setup row, or a stored
// procedure error).
export type InvestmentAccrualPostProcessed = {
  accrualId: number;
  investmentNo: string | null;
  postingNo: string;
  amount: number | null;
};

export type InvestmentAccrualPostFailure = {
  accrualId: number;
  investmentNo: string | null;
  reason: string;
};

export type InvestmentAccrualPostResult = {
  processed: InvestmentAccrualPostProcessed[];
  failed: InvestmentAccrualPostFailure[];
  successCount: number;
  failureCount: number;
};

// Investment > Generate Schedule (PAGEID 1206 / MENUID 1475).
// Legacy BL `API_INVESTMENT_GENERATE_ACCRUAL`. Read-only datatable
// joining investment_profile + investment_type, scoped to
// ipf_status IN ('APPROVE','MATURED') AND NOT EXISTS an
// investment_accrual row for the investment.
export type InvestmentGenerateScheduleRow = {
  index: number;
  investmentId: number | null;
  investmentNo: string | null;
  investmentType: string | null;
  rate: number | null;
  principalAmount: number | null;
  startDate: string | null;
  endDate: string | null;
};

// POST /api/investment/generate-schedule/generate — fans out
// CALL investment_accrual(?) per selected investment_no on the
// legacy DB. Returns per-row success/failure breakdown so the UI
// can surface partial outcomes.
export type InvestmentGenerateScheduleFailure = {
  investmentNo: string;
  reason: string;
};

export type InvestmentGenerateScheduleResult = {
  processed: string[];
  failed: InvestmentGenerateScheduleFailure[];
  successCount: number;
  failureCount: number;
};

// Investment > Monitoring (PAGEID 1183 / MENUID 1458).
// Legacy BL `ATR_INVESTMENT_MONITORING`. Two-level drill-down:
// Level 1 groups investment_profile by ipf_batch_no; Level 2 lists
// investments within the selected batch, joining
// manual_journal_master (system_id='JOURNAL_INVEST') plus a
// correlated receipts subquery (receipt_details / receipt_master).
export type InvestmentMonitoringBatchRow = {
  index: number;
  batchNo: string | null;
  totalAmount: number | null;
};

export type InvestmentMonitoringReceipt = {
  receiptNo: string;
  amount: string;
  date: string;
};

export type InvestmentMonitoringInvestmentRow = {
  index: number;
  investmentId: number;
  batchNo: string | null;
  institutionCode: string | null;
  institutionDesc: string | null;
  institutionBranch: string | null;
  investmentNo: string | null;
  certificateNo: string | null;
  journalNo: string | null;
  journalId: number | null;
  journalStatus: string | null;
  period: number | null;
  tenureDesc: string | null;
  startDate: string | null;
  endDate: string | null;
  principalAmount: number | null;
  rate: number | null;
  status: string | null;
  receipts: InvestmentMonitoringReceipt[];
};

/**
 * Row shape returned by `/api/investment/monitoring/summary-pdf`.
 * Mirrors the single-line-per-investment layout of the legacy
 * TCPDF report `investmentSummary_pdf.php`. Institution / tenure
 * are pre-assembled on the backend; the client composable
 * concatenates them for the table cell so the PDF output matches
 * the legacy `Institution` + `Tenure` HTML columns exactly.
 */
export type InvestmentMonitoringSummaryPdfRow = {
  index: number;
  institutionCode: string | null;
  institutionDesc: string | null;
  institutionBranch: string | null;
  investmentNo: string | null;
  certificateNo: string | null;
  journalNo: string | null;
  journalStatus: string | null;
  period: number | null;
  tenureDesc: string | null;
  startDate: string | null;
  endDate: string | null;
  principalAmount: number | null;
  rate: number | null;
  status: string | null;
};

export type InvestmentMonitoringSummaryPdfPayload = {
  batch: string;
  totalByBatch: number;
  grandTotal: number;
  generatedAt: string;
  rows: InvestmentMonitoringSummaryPdfRow[];
  truncated: boolean;
  limit: number;
};

// Audit Trail > System Transaction (PAGEID 3 / MENUID 5).
// Source: FIMS BL V2_AUDIT_SYSTEM_TRANSACTION_API. Read-only audit ledger
// surfacing every recorded user action across the legacy system.
export type AuditSystemTransactionRow = {
  index: number;
  auditId: number;
  auditTimestamp: string | null;
  auditAction: string | null;
  auditMenuPath: string | null;
  auditMenuId: number | null;
  auditBrowser: string | null;
  auditClientIp: string | null;
  auditUserId: number | null;
  auditUserType: string | null;
  auditUser: string | null;
  hasSql: boolean;
};

export type AuditSystemTransactionOption = { id: string; label: string };

export type AuditSystemTransactionOptions = {
  browsers: AuditSystemTransactionOption[];
  userTypes: AuditSystemTransactionOption[];
  transTypes: AuditSystemTransactionOption[];
};

export type AuditSystemTransactionSql = {
  auditId: number;
  sql: string;
};

// Vendor Portal > Purchase Order Status (PAGEID 1664 / MENUID 2015).
// Source: FIMS BL `NF_BL_VENDOR_PO_STATUS`. Vendor-scoped read-only PO
// listing with per-row GRN aggregation.
export type VendorPoGrn = {
  receiveNo: string;
  totalAmount: number | null;
};

export type VendorPoStatusRow = {
  index: number;
  orderId: number;
  createdDate: string | null;
  orderNo: string | null;
  description: string | null;
  orderAmount: number | null;
  orderStatus: string | null;
  availableDate: string | null;
  grnTotalAmount: number | null;
  grns: VendorPoGrn[];
};

export type VendorPoStatusFooter = {
  pomOrderAmt: number;
  grmTotalAmt: number;
};

// Vendor Portal > Financial Status (PAGEID 1714 / MENUID 2072).
// Source: FIMS BL `NF_BL_PURCHASING_FINANCIAL_STATUS`. Three vendor-scoped
// read-only datatables: billings, vouchers, payments.
export type VendorBillingRow = {
  index: number;
  voucherNo: string | null;
  refNo: string | null;
  description: string | null;
  receivedDate: string | null;
  amount: number;
  status: string | null;
  payToId: string | null;
};

export type VendorVoucherRow = {
  index: number;
  voucherNo: string | null;
  description: string | null;
  date: string | null;
  status: string | null;
  amount: number;
  refNo: string | null;
  paymentNo: string | null;
  payToId: string | null;
  payToType: string | null;
};

export type VendorPaymentRow = {
  index: number;
  voucherNo: string | null;
  description: string | null;
  epChequeNo: string | null;
  modeType: string | null;
  amount: number | null;
  transDate: string | null;
  collectionMode: string | null;
  statusEft: string | null;
  refNo: string | null;
  payToId: string | null;
  payToType: string | null;
};

// Portal > List of Letter (PAGEID 2330 / MENUID 2823).
// Source: FIMS BL `IKA_LETTER_LIST_API`. Read-only catalog + history.
// PDF generation deferred (see SponsorLetterController doc).
export type SponsorLetterCatalogRow = {
  index: number;
  ldeId: number;
  letterId: string;
  letter: string;
};

export type SponsorLetterHistoryRow = {
  index: number;
  lvsId: number;
  letterId: string;
  letterName: string;
  referenceNo: string | null;
  downloadDate: string | null;
};

// Asset > List of Asset (PAGEID 1271 / MENUID 1548).
// Source: FIMS BL `API_ASSET_INVENTORY_LISTOFASSET`. Read-only listing
// of asset_inventory_main with a smart filter.
export type AssetInventoryRow = {
  index: number;
  assetId: number;
  assetCode: string | null;
  assetType: string | null;
  assetNo: string | null;
  gAssetNo: string | null;
  category: string | null;
  item: string | null;
  assetDescription: string | null;
  detail1: string | null;
  serialNo: string | null;
  brand: string | null;
  currentPtj: string | null;
  fund: string | null;
  activity: string | null;
  accountCode: string | null;
  currentCostCentre: string | null;
  initialCost: number | null;
  installCost: number | null;
  grnNo: string | null;
  porNo: string | null;
  journalNo: string | null;
  billNo: string | null;
  voucherNo: string | null;
  status: string | null;
  statusDate: string | null;
  acqDate: string | null;
};

export type AssetInventoryFooter = {
  totalRecord: number;
  totalInitialCost: number;
  totalInstallCost: number;
};

// Project Monitoring > List of Project (MENUID 1544). Datatable backed by
// `capital_project`. See ProjectMonitoringController::projects().
export type ProjectListRow = {
  index: number;
  cpaProjectId: number;
  cpaProjectNo: string | null;
  cpaProjectDesc: string | null;
  cpaProjectType: string | null;
  ftyFundType: string | null;
  latActivityCode: string | null;
  ounCode: string | null;
  ccrCostcentre: string | null;
  soCode: string | null;
  cpaStartDate: string | null;
  cpaEndDate: string | null;
  cpaSource: string | null;
  cpaProjectStatus: string | null;
};

// Project Monitoring > Updated Balance (MENUID 2065). The legacy page is a
// FORM (Project ID autosuggest + Information card + Cash Balance card +
// Save). Backend payload mirrors the legacy autosuggest result of
// `SNA_API_UPDATEDBALANCE_PM&autoSuggestprojectID=1` (joined select over
// capital_project / fund_type / costcentre / activity_type /
// structure_budget / organization_unit / budget). The `*Label` fields
// are the `code - description` composites that the legacy `concat_ws`
// produced for the read-only display inputs.
export type ProjectMonitoringBalance = {
  cpaProjectId: number;
  cpaProjectNo: string | null;
  cpaProjectDesc: string | null;
  ftyFundType: string | null;
  ftyFundLabel: string | null;
  latActivityCode: string | null;
  latActivityLabel: string | null;
  ounCode: string | null;
  ounLabel: string | null;
  ccrCostcentre: string | null;
  ccrCostcentreLabel: string | null;
  soCode: string | null;
  balAmt: number | null;
  budgetId: string | null;
  budgetAmt: number | null;
  seqStrtBudget: string | null;
  seqBudget: string | null;
};

// Mirrors the legacy POST body to
// `SNA_API_UPDATEDBALANCE_PM?updateAmount=1`:
//   info.projectID_UB  → info.cpaProjectNo
//   bal.currBalCash_bal → bal.currBalCashBal (saved into both
//                                              cpa_ytd_balance_amt and
//                                              bdg_topup_amt)
//   bal.seqBudget_bal  → bal.seqBudgetBal   (bdg_budget_id key for the
//                                              second UPDATE)
//   bal.currBudget_bal → bal.currBudgetBal  (kept for fidelity; the
//                                              legacy BL accepts but
//                                              never persists this)
export type ProjectMonitoringBalanceInput = {
  info: {
    cpaProjectNo: string;
  };
  bal: {
    currBalCashBal: string;
    currBudgetBal?: string;
    seqBudgetBal: string;
  };
};

// Portal > Staff Profile (PAGEID 1581 / MENUID 1914).
// Source: legacy BL `API_PORTAL_SALARYPROFILEINFORMATION` (?master,
// ?detailAddress, ?saveAddress, ?updateMaritalStatus, ?all_children,
// ?family_spouse, ?family_children). Self-service portal scoped to the
// authenticated staff. Spouse / children detail forms (MENUIDs 3301 /
// 3305) are NOT migrated here — see StaffProfileController doc.

export type StaffProfileMaster = {
  staffDetails: StaffProfileDetails | null;
  zakatAmount: string | null;
  zakatPeriod: string | null;
};

export type StaffProfileDetails = {
  stfStaffId: string | null;
  stfStaffName: string | null;
  stfIcNo: string | null;
  stfUnit: string | null;
  stfTelnoWork: string | null;
  stfEmailAddr: string | null;
  stfMaritalStatus: string | null;
  stfHandphoneNo: string | null;
  salTaxGroup: string | null;
  status: string | null;
  stfCitizen: string | null;
  stfSalIncrDate: string | null;
  stsSalaryGrade: string | null;
  staAcctNoProfile: string | null;
  staAcctNameProfile: string | null;
  sscServiceDescProfile: string | null;
  stsJoinDate: string | null;
  stfCurrentAddress: string | null;
  stfPermanentAddress: string | null;
  salBasicSalary: string | null;
  titleDesc: string | null;
  genderDesc: string | null;
  maritalstatusDesc: string | null;
  salTaxCategory: string | null;
  salZakatDesc: string | null;
  pensionstatusDesc: string | null;
  jobStatus: string | null;
  ounDesc: string | null;
  ccrCostcentreDesc: string | null;
  salSocsoStatus: string | null;
  salEpfStatus: string | null;
  isAcknowledgeMarital: number | null;
};

export type StaffProfileLookupOption = { value: string; label: string };

export type StaffProfileOptions = {
  maritalStatus: StaffProfileLookupOption[];
  state: StaffProfileLookupOption[];
  country: StaffProfileLookupOption[];
  addressType: StaffProfileLookupOption[];
};

export type StaffProfileAddress = {
  stfStaffId: string | null;
  saAddressType: number | null;
  saAddress1: string | null;
  saAddress2: string | null;
  saPcode: string | null;
  saCity: string | null;
  saState: string | null;
  saCountry: string | null;
  isAcknowledgement: number;
  stfHandphoneNo: string | null;
  hasAddress: boolean;
};

export type StaffProfileAddressInput = {
  saAddressType?: number | null;
  saAddress1: string;
  saAddress2?: string | null;
  saPcode?: string | null;
  saCity?: string | null;
  saState?: string | null;
  saCountry?: string | null;
  stfHandphoneNo?: string | null;
};

export type StaffProfileMaritalStatusInput = {
  maritalStatus: string;
};

export type StaffProfileSpouseRow = {
  index: number;
  spoSpouseSeq: string;
  spoName: string | null;
  spoIcNo: string | null;
  spoTaxNo: string | null;
  spoMarriageDate: string | null;
  spoDivorceDate: string | null;
  spoDeathDate: string | null;
};

export type StaffProfileChildRow = {
  index: number;
  stcChildSeq: string;
  stcName: string | null;
  stcSpouseSeq: string | null;
  stcIcRefNo: string | null;
  stcBod: string | null;
  stcRelation: string | null;
  stcPcbStatus: string | null;
  age: number | null;
  stcStudyStartDate: string | null;
  stcStudyEndDate: string | null;
  stcLevelStudy: string | null;
  stcDisabilityStatus: string | null;
  stcDeathDate: string | null;
};

// =============================================================================
// Vendor Portal > Vendor Portal (PAGEID 1622 / MENUID 1961)
// Backed by VendorPortalController. Phase 2a ships the editable master
// profile (`PUT /profile`) + lookups; sub-table CRUD (Phase 2b) and
// the temp_vend_* renewal staging / dropzone / submit flow (Phase 2c)
// remain deferred. Sub-table list rows below are still read-only.
// =============================================================================

export type VendorPortalProfile = {
  vendorId: string | null;
  vendorCode: string | null;
  vendorName: string | null;
  address: string | null;
  address1: string | null;
  address2: string | null;
  address3: string | null;
  postcode: string | null;
  state: string | null;
  countryCode: string | null;
  registrationNo: string | null;
  registrationDate: string | null;
  registrationExpiryDate: string | null;
  kkRegNo: string | null;
  kkExpiredDate: string | null;
  taxRegNo: string | null;
  telNo: string | null;
  faxNo: string | null;
  contactPerson: string | null;
  vendorStatus: string | null;
  isCreditor: string | null;
  isDebtor: string | null;
  bumiStatus: string | null;
  companyCategory: string | null;
  authorizeCapital: number | null;
  paidUpCapital: number | null;
  emailAddress: string | null;
  icNo: string | null;
  unvRegDate: string | null;
  unvReqExpDate: string | null;
  epfNo: string | null;
  socsoNo: string | null;
  regNoKpm: string | null;
  regDateKpm: string | null;
  regExpDateKpm: string | null;
  rosNo: string | null;
  vendorBank: string | null;
  bankAccountNo: string | null;
  billerCode: string | null;
  tempCode: string | null;
  nameApplication: string | null;
  telNoApplication: string | null;
  vendorCodeOri: string | null;
};

/**
 * Update payload sent to `PUT /api/portal/vendor/profile`. Mirrors the
 * three legacy sections that drive the master form on PAGEID 1622:
 *   - Application Information (component 5257)
 *   - Vendor Portal           (component 4737)
 *   - Vendor Registration Detail (component 4738)
 *
 * Date fields use the legacy `d/m/Y` wire format ("27/04/2026") because
 * both the read and write endpoints (and `UpdateVendorPortalProfileRequest`)
 * standardise on that representation. Empty strings are normalised to
 * null on the backend.
 *
 * `vendorCode`, `vendorId`, `vendorStatus`, `unvRegDate`, `unvReqExpDate`
 * and `tempCode` are NOT part of this payload — those are owned by the
 * renewal/approval workflow (Phase 2c) and the resolver, and live on the
 * read-only `VendorPortalProfile` shape only.
 */
export type VendorPortalProfileInput = {
  vendorName: string;
  email: string;
  telNo: string;
  faxNo?: string | null;
  bumiStatus: string;
  contactPerson: string;
  isCreditor?: "Y" | "N" | null;
  isDebtor?: "Y" | "N" | null;
  taxRegNo?: string | null;
  epfNo?: string | null;
  socsoNo?: string | null;
  billerCode?: string | null;
  icNo?: string | null;
  companyCategory?: string | null;
  authorizeCapital?: number | null;
  paidUpCapital?: number | null;
  registrationNo?: string | null;
  regDate?: string | null;
  regExpDate?: string | null;
  kkRegNo: string;
  kkExpiredDate?: string | null;
  regNoKpm?: string | null;
  regDateKpm?: string | null;
  regExpdateKpm?: string | null;
  rosNo: string;
  nameApplication?: string | null;
  telNoApplication?: string | null;
};

/**
 * Dropdown option set returned by `GET /api/portal/vendor/lookups`.
 * `taraf` is dynamic from `lookup_details`; the rest are static lists
 * (matches the legacy hard-coded `UNION` queries on PAGEID 1622).
 */
export type VendorPortalLookupOption = { value: string; label: string };

export type VendorPortalLookups = {
  taraf: VendorPortalLookupOption[];
  companyCategory: VendorPortalLookupOption[];
  vendorStatus: VendorPortalLookupOption[];
  creditorDebtor: VendorPortalLookupOption[];
};

export type VendorPortalCategoryRow = {
  index: number;
  id: string | null;
  vendorCode: string | null;
  categoryCode: string | null;
  categoryLabel: string | null;
  createdDate: string | null;
};

export type VendorPortalAccountRow = {
  index: number;
  id: string | null;
  vendorCode: string | null;
  bankCode: string | null;
  bankName: string | null;
  bankAccountNo: string | null;
  status: string;
  createdDate: string | null;
};

export type VendorPortalAddressRow = {
  index: number;
  id: string | null;
  vendorCode: string | null;
  addressType: string | null;
  addressTypeLabel: string | null;
  address1: string | null;
  address2: string | null;
  address3: string | null;
  postcode: string | null;
  city: string | null;
  state: string | null;
  country: string | null;
  createdDate: string | null;
};

export type VendorPortalJobscopeRow = {
  index: number;
  id: string | null;
  vendorCode: string | null;
  jobscopeCode: string | null;
  jobscopeLabel: string | null;
  category: string | null;
  createdDate: string | null;
};

export type VendorPortalLicenceRow = {
  index: number;
  id: string | null;
  vendorCode: string | null;
  licenceCode: string | null;
  licenceLabel: string | null;
  licenceDesc: string | null;
  createdDate: string | null;
};

export type VendorPortalOtherLicenceRow = {
  index: number;
  id: string | null;
  vendorCode: string | null;
  licenceCode: string | null;
  licenceDesc: string | null;
  createdDate: string | null;
};

// Setup and Maintenance > Integration > Integration - PTJ (PAGEID 1860 / MENUID 2277).
// Legacy BL: AS_BL_SM_INTEGRATIONPTJ.
export type IntegrationPtjRow = {
  index: number;
  iouId: number;
  iouCode: string | null;
  iouCodePersis: string | null;
  iouDesc: string | null;
  iouBursarFlag: string | null;
  orgCode: string | null;
  orgDesc: string | null;
  iouAddress: string | null;
  iouTelNo: string | null;
  iouFaxNo: string | null;
};

export type IntegrationPtjPromoteInput = {
  iouCode: string;
  iouDesc: string;
  iouCodePersis?: string | null;
  iouBursarFlag?: string | null;
  orgCode?: string | null;
  orgDesc?: string | null;
  iouAddress?: string | null;
  iouTelNo?: string | null;
  iouFaxNo?: string | null;
  ounLevel?: string | null;
  ounCodeParent?: string | null;
};

// Setup and Maintenance > Integration > Integration - Cost center (PAGEID 1861 / MENUID 2278).
// Legacy BL: AS_BL_SM_INTEGRATIONCOSTCENTRE.
export type IntegrationCostCentreRow = {
  index: number;
  icsCostcentreId: number;
  icsCostcentre: string | null;
  icsCostcentreDesc: string | null;
  icsHostelCode: string | null;
  icsStatus: string | null;
};

export type IntegrationCostCentrePromoteInput = {
  icsCostcentre: string;
  icsCostcentreDesc: string;
  icsHostelCode?: string | null;
  icsStatus?: string | null;
};

// Setup and Maintenance > Integration > Integration - Profile (PAGEID 2000 / MENUID 2443).
// Legacy BL: SNA_API_SM_INTEGRATION_PROFILE.
export type IntegrationProfileRow = {
  index: number;
  icpProjectId: number;
  icpProjectNo: string | null;
  icpSubsystemId: string | null;
  subsystemcode: string | null;
  ftyFundType: string | null;
  latActivityCode: string | null;
  ccrCostcentre: string | null;
  ounCode: string | null;
  icpSoCode: string | null;
  icpStartDate: string | null;
  icpEndDate: string | null;
  icpYearnum: number | null;
  icpProjectType: string | null;
  icpProjectDesc: string | null;
  icpPeriod: number | null;
  icpProjectStatus: string | null;
};

// Setup and Maintenance > Integration > Integration - Activity (PAGEID 2003 / MENUID 2444).
// Legacy BL: SNA_API_SM_INTEGRATION_ACTIVITY.
export type IntegrationActivityRow = {
  index: number;
  iatId: number;
  iatActivityCode: string | null;
  iatActivityDescriptionBm: string | null;
  iatActivityCodeParent: string | null;
  iatActivityGroupCode: string | null;
  iatActivitySubgroupCode: string | null;
  iatActivitySubsiriCode: string | null;
  iatStatus: string | null;
  iatSource: string | null;
  iatExtendedField?: string | null;
};

// General Ledger > Budget Not Exists (PAGEID 2200 / MENUID 2657).
// Legacy BL: NAD_API_SM_REPORT_BUDGET_NOT_EXIST.
export type BudgetNotExistsRow = {
  index: number;
  pmtPostingId: number;
  ftyFundType: string | null;
  atActivityCode: string | null;
  ounCode: string | null;
  ccrCostcentre: string | null;
  acmAcctCode: string | null;
  cpaProjectNo: string | null;
  pdeDocumentNo: string | null;
  pdeReference: string | null;
  pdeReference1: string | null;
  pdeTransType: string | null;
  pdeTransAmt: number | null;
  pdeTransDate: string | null;
  pdePaytoType: string | null;
  pdePaytoId: string | null;
  pdePaytoName: string | null;
  pdeStatus: string | null;
  pdeDocDescription: string | null;
  pmtSystemId: string | null;
  pmtPostingDesc: string | null;
  pmtStatus: string | null;
};

// Setup and Maintenance > Global > List of Currency (PAGEID 2636 / MENUID 3198).
// Legacy BL: QLA_API_GLOBAL_LISTOFCURRENCY.
export type ListOfCurrencyRow = {
  index: number;
  cymCurrencyId: number;
  cymCurrencyCode: string | null;
  cymCurrencyDesc: string | null;
  cydUnit: number | null;
  cnyCountryCode: string | null;
  cnyCountryDesc: string | null;
  cymEnabled: string;
};

export type ListOfCurrencyInput = {
  cymCurrencyCode: string;
  cymCurrencyDesc: string;
  cnyCountryCode: string;
  cydUnit: number;
  cymEnabled?: "Active" | "Inactive";
};

export type ListOfCurrencyUpdate = {
  cydUnit: number;
  cymEnabled: "Active" | "Inactive";
};

export type CountryOption = {
  id: string;
  label: string;
  code: string;
  desc: string | null;
};

// Setup and Maintenance > Global > AG Rate (PAGEID 2647 / MENUID 3199).
// Legacy BL: QLA_API_GLOBAL_UPLOADCURRENCY.
export type AgRateRow = {
  index: number;
  cydYear: number;
  cydMonth: string;
  cydFileName: string | null;
};

export type AgRateLine = {
  cydId: number;
  cymCurrencyCode: string | null;
  cydStartDate: string | null;
  cydEndDate: string | null;
  cydExchangeTypeCode: string | null;
  cydConversationRate: number | null;
  cydUnit: number | null;
  cydFileName: string | null;
  cydStatus: string | null;
};

export type AgRateCurrencyOption = {
  id: string;
  label: string;
  code: string;
  desc: string | null;
  unit: number | null;
};

export type AgRateOptionEntry = { id: string; label: string };

export type AgRateOptions = {
  years: AgRateOptionEntry[];
  months: AgRateOptionEntry[];
};

export type AgRateEntryInput = {
  cydYear: number;
  cydMonth: number;
  rates: Array<{
    cymCurrencyCode: string;
    cydUnit?: number;
    cydConversationRate: number;
  }>;
};
