<script setup lang="ts">
/**
 * General Ledger / General Ledger Listing
 * (PAGEID 2068 / MENUID 2519)
 *
 * Source: FIMS BL `NAD_API_GL_LISTINGPOSTINGTOGL`. Read-only line-level
 * listing over posting_master + posting_details with the 5-level
 * account_main self-join hierarchy.
 *
 * Legacy page ships two separate filter components (Top Filter + Smart
 * Filter) that both feed the same backend query. Following every other
 * migrated Kerisi listing (Posting to GL (TB), Journal Listing), both
 * forms are consolidated here into a single smart filter modal to avoid
 * a duplicated UX. Every legacy filter key is accepted by the backend.
 *
 * Filter-first behaviour (same pattern as Posting to GL (TB)): the
 * `posting_master × posting_details` + 5-level `account_main` self-join
 * is extremely heavy against the full dataset, so the table stays empty
 * on page load and only fetches after the user applies the smart filter.
 * After the first apply the text search + pagination + sort behave
 * normally and refetch against the same filter set.
 *
 * Date range inputs use native HTML5 date pickers (`type="date"`). The
 * ISO value is converted to `dd/mm/yyyy` before being sent to the
 * backend, which still parses with `STR_TO_DATE(..., '%d/%m/%Y')`.
 *
 * Default PDF / CSV / Excel export buttons are added per rule #8 — the
 * legacy COMPONENT_JS download is not applicable (it was the Fund Type
 * page download JS pasted into the wrong spec row).
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  FileDown,
  FileSpreadsheet,
  Filter,
  MoreVertical,
  Search,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { getGlListingOptions, listGlListing } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  GlListingFooter,
  GlListingOptions,
  GlListingRow,
  GlListingSmartFilter,
} from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<GlListingRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("acm_acct_code");
const sortDir = ref<"asc" | "desc">("asc");
const loading = ref(false);
const footer = ref<GlListingFooter>({ transAmt: 0 });

const showSmartFilter = ref(false);
const hasSearched = ref(false);
const emptyFilter: GlListingSmartFilter = {
  systemId: "",
  dateStart: "",
  dateEnd: "",
  fundType: "",
  activityCode: "",
  ounCodeL3: "",
  ounCode: "",
  costCentre: "",
  accountClass: "",
  accountSubclass: "",
  accountSeries: "",
  accountSubseries: "",
  acctCode: "",
  accountType: "",
  payToType: "",
  postingNo: "",
  documentNo: "",
  soCode: "",
  payToId: "",
  reference: "",
  reference1: "",
  transType: "",
  statementItem: "",
};
const smartFilter = ref<GlListingSmartFilter>({ ...emptyFilter });
const options = ref<GlListingOptions>({
  systemIds: [],
  fundTypes: [],
  activityCodes: [],
  ptjL3: [],
  ptj: [],
  costCentres: [],
  accountsByLevel: {},
  accountTypes: [],
  statementItems: [],
  transTypes: [],
  payToTypes: [],
});

// Cascaded account hierarchy lists driven by the selected parents.
const accountsL1 = computed(() => options.value.accountsByLevel[1] ?? []);
const accountsL2 = computed(() =>
  (options.value.accountsByLevel[2] ?? []).filter((a) =>
    smartFilter.value.accountClass ? a.parent === smartFilter.value.accountClass : true,
  ),
);
const accountsL3 = computed(() =>
  (options.value.accountsByLevel[3] ?? []).filter((a) =>
    smartFilter.value.accountSubclass ? a.parent === smartFilter.value.accountSubclass : true,
  ),
);
const accountsL4 = computed(() =>
  (options.value.accountsByLevel[4] ?? []).filter((a) =>
    smartFilter.value.accountSeries ? a.parent === smartFilter.value.accountSeries : true,
  ),
);
const accountsL5 = computed(() =>
  (options.value.accountsByLevel[5] ?? []).filter((a) =>
    smartFilter.value.accountSubseries ? a.parent === smartFilter.value.accountSubseries : true,
  ),
);
// PTJ level 4 list filtered by chosen level 3 (matches legacy autosuggest).
const ptjL4 = computed(() =>
  options.value.ptj.filter((p) =>
    smartFilter.value.ounCodeL3 ? p.parent === smartFilter.value.ounCodeL3 : p.level !== 3,
  ),
);
const costCentres = computed(() =>
  options.value.costCentres.filter((c) =>
    smartFilter.value.ounCode ? c.parent === smartFilter.value.ounCode : true,
  ),
);

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function fmtMoney(n: number | null): string {
  if (n === null || Number.isNaN(n)) return "-";
  return new Intl.NumberFormat("en-MY", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n);
}

/** Convert <input type="date"> value (yyyy-mm-dd) to dd/mm/yyyy for the
 *  backend (STR_TO_DATE(..., '%d/%m/%Y')). Pass-through for empty/other. */
function toLegacyDate(iso: string): string {
  if (!iso) return "";
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : iso;
}

async function loadOptions() {
  try {
    const res = await getGlListingOptions();
    options.value = res.data;
  } catch {
    // keep empty defaults
  }
}

async function loadRows() {
  loading.value = true;
  const f = smartFilter.value;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
    ...(f.systemId ? { pmt_system_id: f.systemId } : {}),
    ...(toLegacyDate(f.dateStart) ? { date_start: toLegacyDate(f.dateStart) } : {}),
    ...(toLegacyDate(f.dateEnd) ? { date_end: toLegacyDate(f.dateEnd) } : {}),
    ...(f.fundType ? { fty_fund_type: f.fundType } : {}),
    ...(f.activityCode ? { at_activity_code: f.activityCode } : {}),
    ...(f.ounCodeL3 ? { oun_code_l3: f.ounCodeL3 } : {}),
    ...(f.ounCode ? { oun_code: f.ounCode } : {}),
    ...(f.costCentre ? { ccr_costcentre: f.costCentre } : {}),
    ...(f.accountClass ? { account_class: f.accountClass } : {}),
    ...(f.accountSubclass ? { account_subclass: f.accountSubclass } : {}),
    ...(f.accountSeries ? { account_series: f.accountSeries } : {}),
    ...(f.accountSubseries ? { account_subseries: f.accountSubseries } : {}),
    ...(f.acctCode ? { acm_acct_code: f.acctCode } : {}),
    ...(f.accountType ? { account_type: f.accountType } : {}),
    ...(f.payToType ? { pde_payto_type: f.payToType } : {}),
    ...(f.postingNo ? { pmt_posting_no: f.postingNo } : {}),
    ...(f.documentNo ? { pde_document_no: f.documentNo } : {}),
    ...(f.soCode ? { so_code: f.soCode } : {}),
    ...(f.payToId ? { pde_payto_id: f.payToId } : {}),
    ...(f.reference ? { pde_reference: f.reference } : {}),
    ...(f.reference1 ? { pde_reference1: f.reference1 } : {}),
    ...(f.transType ? { pde_trans_type: f.transType } : {}),
    ...(f.statementItem ? { acm_behavior: f.statementItem } : {}),
  });
  try {
    const res = await listGlListing(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    const meta = res.meta as { footer?: GlListingFooter } | undefined;
    footer.value = meta?.footer ?? { transAmt: 0 };
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load rows.");
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: string) {
  if (sortBy.value === col) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
    sortBy.value = col;
    sortDir.value = "asc";
  }
  if (!hasSearched.value) return;
  void loadRows();
}

function prevPage() {
  if (page.value > 1) {
    page.value -= 1;
    void loadRows();
  }
}
function nextPage() {
  if (page.value < totalPages.value) {
    page.value += 1;
    void loadRows();
  }
}

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  hasSearched.value = true;
  void loadRows();
}
function resetSmartFilter() {
  smartFilter.value = { ...emptyFilter };
}

const exportColumns = [
  "Posting Reference",
  "Document No",
  "Document Description",
  "Fund Type",
  "Fund Desc",
  "Activity Code",
  "Activity Desc",
  "PTJ",
  "PTJ Desc",
  "Cost Centre Code",
  "Cost Centre Desc",
  "S/O Code",
  "SO Desc",
  "Account Code",
  "Account Desc",
  "Account Class",
  "Account Sub-Class",
  "Account Series",
  "Account Sub-Series",
  "Transaction Amount",
  "Transaction Type",
  "Reference 1",
  "Reference 2",
  "Transaction Date",
  "Transaction Period",
  "Debtor/Creditor Type",
  "Debtor/Creditor Code",
  "System User ID",
  "System ID",
  "Account Type",
  "Statement Item",
];

function rowToRecord(r: GlListingRow): Record<string, string | number> {
  return {
    "Posting Reference": r.postingNo ?? "",
    "Document No": r.documentNo ?? "",
    "Document Description": r.docDescription ?? "",
    "Fund Type": r.fundType ?? "",
    "Fund Desc": r.fundDesc ?? "",
    "Activity Code": r.activityCode ?? "",
    "Activity Desc": r.activityDesc ?? "",
    PTJ: r.ounCode ?? "",
    "PTJ Desc": r.ounDesc ?? "",
    "Cost Centre Code": r.costCentre ?? "",
    "Cost Centre Desc": r.costCentreDesc ?? "",
    "S/O Code": r.soCode ?? "",
    "SO Desc": r.projectDesc ?? "",
    "Account Code": r.acctCode ?? "",
    "Account Desc": r.acctDesc ?? "",
    "Account Class": r.accountClass ?? "",
    "Account Sub-Class": r.accountSubclass ?? "",
    "Account Series": r.accountSeries ?? "",
    "Account Sub-Series": r.accountSubseries ?? "",
    "Transaction Amount": r.transAmt,
    "Transaction Type": r.transType ?? "",
    "Reference 1": r.reference ?? "",
    "Reference 2": r.reference1 ?? "",
    "Transaction Date": r.postedDate ?? "",
    "Transaction Period": r.postedPeriod ?? "",
    "Debtor/Creditor Type": r.payToType ?? "",
    "Debtor/Creditor Code": r.payToId ?? "",
    "System User ID": r.createdBy ?? "",
    "System ID": r.systemId ?? "",
    "Account Type": r.acctActivity ?? "",
    "Statement Item": r.acctBehavior ?? "",
  };
}

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } =
  useDatatableFeatures({
    pageName: "General Ledger Listing",
    apiDataPath: "/general-ledger/general-ledger-listing",
    defaultExportColumns: exportColumns,
    getFilteredList: () => rows.value.map(rowToRecord),
    datatableRef,
    searchKeyword: q,
  });

async function exportExcel() {
  try {
    if (rows.value.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("GL Listing");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      const rec = rowToRecord(r);
      ws.addRow([idx + 1, ...exportColumns.map((c) => rec[c])]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `GL_Listing_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
  }
}

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (searchDebounce) clearTimeout(searchDebounce);
  // Filter-gated — don't kick off a full 5-level account join just
  // because the user typed in the search box before applying a filter.
  if (!hasSearched.value) return;
  searchDebounce = setTimeout(() => {
    searchDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

// Reset dependent dropdowns when their parent changes.
watch(
  () => smartFilter.value.accountClass,
  () => {
    smartFilter.value.accountSubclass = "";
    smartFilter.value.accountSeries = "";
    smartFilter.value.accountSubseries = "";
    smartFilter.value.acctCode = "";
  },
);
watch(
  () => smartFilter.value.accountSubclass,
  () => {
    smartFilter.value.accountSeries = "";
    smartFilter.value.accountSubseries = "";
    smartFilter.value.acctCode = "";
  },
);
watch(
  () => smartFilter.value.accountSeries,
  () => {
    smartFilter.value.accountSubseries = "";
    smartFilter.value.acctCode = "";
  },
);
watch(
  () => smartFilter.value.accountSubseries,
  () => {
    smartFilter.value.acctCode = "";
  },
);
watch(
  () => smartFilter.value.ounCodeL3,
  () => {
    smartFilter.value.ounCode = "";
    smartFilter.value.costCentre = "";
  },
);
watch(
  () => smartFilter.value.ounCode,
  () => {
    smartFilter.value.costCentre = "";
  },
);

onMounted(async () => {
  await loadOptions();
});
onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <input
        ref="templateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onTemplateFileChange"
      />
      <p class="text-base font-semibold text-slate-500">
        General Ledger / General Ledger Listing
      </p>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">General Ledger Listing</h1>
          <button
            type="button"
            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
            aria-label="More"
          >
            <MoreVertical class="h-4 w-4" />
          </button>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select
                v-model.number="limit"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                @change="page = 1; hasSearched && void loadRows()"
              >
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search
                  class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                />
                <input
                  v-model="q"
                  type="search"
                  placeholder="Filter rows..."
                  class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm"
                  @keyup.enter="page = 1; hasSearched && void loadRows()"
                />
                <button
                  v-if="q"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  aria-label="Clear search"
                  @click="q = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm"
                @click="showSmartFilter = true"
              >
                <Filter class="h-4 w-4" />Filter
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[520px] overflow-y-auto' : ''">
              <table class="w-full min-w-[3400px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pmt_posting_no')"
                    >
                      Posting Reference
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pde_document_no')"
                    >
                      Document No
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Document Description</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Fund Type</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Fund Desc</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Activity Code</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Activity Desc</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">PTJ</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">PTJ Desc</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Cost Centre</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Cost Centre Desc</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">S/O Code</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">SO Desc</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('acm_acct_code')"
                    >
                      Account Code
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Account Desc</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Account Class</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Sub-Class</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Series</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Sub-Series</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('pde_trans_amt')"
                    >
                      Transaction Amount
                    </th>
                    <th class="px-3 py-2 text-center text-xs font-semibold uppercase">Type</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Reference 1</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Reference 2</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pmt_posteddate')"
                    >
                      Transaction Date
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Period</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Debtor/Creditor Type</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Debtor/Creditor Code</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">System User ID</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pmt_system_id')"
                    >
                      System ID
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Account Type</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Statement Item</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="33" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="!hasSearched">
                    <td colspan="33" class="px-3 py-10 text-center text-sm text-slate-500">
                      Use <span class="font-medium text-slate-700">Filter</span> to search GL
                      postings. The underlying join is heavy — filter first to keep it fast.
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="33" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="row.pdePostingDetlId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.postingNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.documentNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.docDescription ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.fundType ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.fundDesc ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.activityCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.activityDesc ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ounCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ounDesc ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.costCentre ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.costCentreDesc ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.soCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.projectDesc ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.acctCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.acctDesc ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.accountClass ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.accountSubclass ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.accountSeries ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.accountSubseries ?? "-" }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtMoney(row.transAmt) }}</td>
                    <td class="px-3 py-2 text-center">{{ row.transType ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <template v-if="row.reference">
                        <span
                          v-for="ref in row.reference.split(',')"
                          :key="ref"
                          class="block whitespace-nowrap"
                        >
                          {{ ref }}
                        </span>
                      </template>
                      <template v-else>-</template>
                    </td>
                    <td class="px-3 py-2">{{ row.reference1 ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.postedDate ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.postedPeriod ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.payToType ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.payToId ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.createdBy ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.systemId ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.acctActivity ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.acctBehavior ?? "-" }}</td>
                  </tr>
                </tbody>
                <tfoot
                  v-if="rows.length > 0"
                  class="sticky bottom-0 border-t border-slate-300 bg-slate-50"
                >
                  <tr>
                    <td
                      colspan="21"
                      class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-700"
                    >
                      Grand Total (filtered)
                    </td>
                    <td class="px-3 py-2 text-right text-sm font-semibold text-slate-900">
                      {{ fmtMoney(footer.transAmt) }}
                    </td>
                    <td colspan="11"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3"
          >
            <div class="text-xs text-slate-500">
              Showing {{ startIdx }}-{{ endIdx }} of {{ total }}
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                :disabled="page <= 1"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="prevPage"
              >
                Prev
              </button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button
                type="button"
                :disabled="page >= totalPages"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="nextPage"
              >
                Next
              </button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
                @click="handleDownloadPDF"
              >
                <Download class="h-3.5 w-3.5" />PDF
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
                @click="handleDownloadCSV"
              >
                <FileDown class="h-3.5 w-3.5" />CSV
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
                @click="exportExcel"
              >
                <FileSpreadsheet class="h-3.5 w-3.5" />Excel
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div
        v-if="showSmartFilter"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
        @click.self="showSmartFilter = false"
      >
        <div
          class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl"
        >
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
          </div>
          <div class="flex-1 overflow-y-auto">
            <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">System ID</label>
                <select
                  v-model="smartFilter.systemId"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="s in options.systemIds" :key="s" :value="s">{{ s }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Post Date (Start)</label>
                <input
                  v-model="smartFilter.dateStart"
                  type="date"
                  :max="smartFilter.dateEnd || undefined"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Post Date (End)</label>
                <input
                  v-model="smartFilter.dateEnd"
                  type="date"
                  :min="smartFilter.dateStart || undefined"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Fund Type</label>
                <select
                  v-model="smartFilter.fundType"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in options.fundTypes" :key="o.code" :value="o.code">
                    {{ o.code }} - {{ o.description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Activity Code</label>
                <select
                  v-model="smartFilter.activityCode"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in options.activityCodes" :key="o.code" :value="o.code">
                    {{ o.code }} - {{ o.description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Posting Reference</label>
                <input
                  v-model="smartFilter.postingNo"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">PTJ (Level 3)</label>
                <select
                  v-model="smartFilter.ounCodeL3"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in options.ptjL3" :key="o.code" :value="o.code">
                    {{ o.code }} - {{ o.description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">PTJ</label>
                <select
                  v-model="smartFilter.ounCode"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in ptjL4" :key="o.code" :value="o.code">
                    {{ o.code }} - {{ o.description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Cost Centre</label>
                <select
                  v-model="smartFilter.costCentre"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in costCentres" :key="o.code" :value="o.code">
                    {{ o.code }} - {{ o.description }}
                  </option>
                </select>
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Account Class</label>
                <select
                  v-model="smartFilter.accountClass"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in accountsL1" :key="o.code" :value="o.code">
                    {{ o.code }} - {{ o.description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sub-Class</label>
                <select
                  v-model="smartFilter.accountSubclass"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in accountsL2" :key="o.code" :value="o.code">
                    {{ o.code }} - {{ o.description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Series</label>
                <select
                  v-model="smartFilter.accountSeries"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in accountsL3" :key="o.code" :value="o.code">
                    {{ o.code }} - {{ o.description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sub-Series</label>
                <select
                  v-model="smartFilter.accountSubseries"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in accountsL4" :key="o.code" :value="o.code">
                    {{ o.code }} - {{ o.description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Account Code</label>
                <select
                  v-model="smartFilter.acctCode"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in accountsL5" :key="o.code" :value="o.code">
                    {{ o.code }} - {{ o.description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Account Type</label>
                <select
                  v-model="smartFilter.accountType"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="t in options.accountTypes" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>

              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Type Customer</label>
                <select
                  v-model="smartFilter.payToType"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in options.payToTypes" :key="o.code" :value="o.code">
                    {{ o.code }} - {{ o.description }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Payee Code</label>
                <input
                  v-model="smartFilter.payToId"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Document No</label>
                <input
                  v-model="smartFilter.documentNo"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">SO Code</label>
                <input
                  v-model="smartFilter.soCode"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Reference 1</label>
                <input
                  v-model="smartFilter.reference"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Reference 2</label>
                <input
                  v-model="smartFilter.reference1"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Transaction Type</label>
                <select
                  v-model="smartFilter.transType"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="t in options.transTypes" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Statement Item</label>
                <select
                  v-model="smartFilter.statementItem"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="t in options.statementItems" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-4 py-2 text-sm"
              @click="resetSmartFilter"
            >
              Reset
            </button>
            <button
              type="button"
              class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white"
              @click="applySmartFilter"
            >
              OK
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
