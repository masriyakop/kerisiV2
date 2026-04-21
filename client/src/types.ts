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
