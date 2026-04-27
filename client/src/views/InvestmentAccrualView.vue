<script setup lang="ts">
/**
 * Investment / Accrual (PAGEID 1175, MENUID 1446)
 *
 * Source: FIMS BL `API_INVESTMENT_ACCRUAL` (default listing action)
 * and `INSERT_UPDATE_INVESTMENT_ACCRUAL` (default branch — Post-to-TB).
 * Datatable joins investment_accrual + investment_institution +
 * investment_profile with a 6-field smart filter (Investment No /
 * Institution Code / Institution Name / Branch / No of Days / Rate)
 * mirroring the active legacy smart filter columns.
 *
 * The Post-to-TB write flow inserts into
 * posting_master / posting_details on mysql_secondary and fans out
 * to the legacy stored procedures `getTableSequenceNum` and
 * `getRefNoByCurrentYear`. Each selected accrual is processed
 * independently on the server so partial success is reported back
 * here via `processed` / `failed` arrays.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  FileDown,
  FileSpreadsheet,
  Filter,
  MoreVertical,
  Search,
  Send,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  getInvestmentAccrualOptions,
  listInvestmentAccrual,
  postInvestmentAccrualToTb,
} from "@/api/cms";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useToast } from "@/composables/useToast";
import type {
  InvestmentAccrualOptions,
  InvestmentAccrualRow,
  InvestmentAccrualSmartFilter,
} from "@/types";

const toast = useToast();
const { confirm } = useConfirmDialog();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<InvestmentAccrualRow[]>([]);
const loading = ref(false);
const total = ref(0);
const grandAmount = ref(0);
const grandAmtPerDay = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

type SortKey =
  | "dt_invest_no"
  | "dt_inst_code"
  | "dt_inst_name"
  | "dt_branch"
  | "dt_amount"
  | "dt_no_of_days"
  | "dt_amt_per_day"
  | "dt_rate"
  | "dt_posting_no";

const sortBy = ref<SortKey>("dt_invest_no");
const sortDir = ref<"asc" | "desc">("asc");

const showSmartFilter = ref(false);
const smartFilter = ref<InvestmentAccrualSmartFilter>({
  investNo: "",
  instCode: "",
  instName: "",
  branch: "",
  noOfDays: "",
  rate: "",
});
const options = ref<InvestmentAccrualOptions>({ institution: [] });

// Selection state for the "Post to TB" bulk action. Keyed by iac_id
// so selections persist across pagination / sorting / filter
// changes (the legacy UI tracks the same composite key across
// pages via hidden inputs).
const selected = ref<Set<number>>(new Set());
const posting = ref(false);

function isSelected(row: InvestmentAccrualRow): boolean {
  return row.accrualId != null && selected.value.has(row.accrualId);
}

function toggleRow(row: InvestmentAccrualRow) {
  if (row.accrualId == null) return;
  const next = new Set(selected.value);
  if (next.has(row.accrualId)) next.delete(row.accrualId);
  else next.add(row.accrualId);
  selected.value = next;
}

const pageRowKeys = computed<number[]>(() =>
  rows.value
    .map((r) => r.accrualId)
    .filter((n): n is number => typeof n === "number" && n > 0),
);

const allPageSelected = computed(() => {
  if (pageRowKeys.value.length === 0) return false;
  return pageRowKeys.value.every((k) => selected.value.has(k));
});
const somePageSelected = computed(() =>
  pageRowKeys.value.some((k) => selected.value.has(k)),
);

function toggleAllOnPage() {
  const next = new Set(selected.value);
  if (allPageSelected.value) {
    pageRowKeys.value.forEach((k) => next.delete(k));
  } else {
    pageRowKeys.value.forEach((k) => next.add(k));
  }
  selected.value = next;
}

function clearSelection() {
  selected.value = new Set();
}

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

async function loadOptions() {
  try {
    const res = await getInvestmentAccrualOptions();
    options.value = res.data;
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load filter options.",
    );
  }
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
  });
  if (q.value.trim()) params.set("q", q.value.trim());
  if (smartFilter.value.investNo)
    params.set("filter_invest_no", smartFilter.value.investNo);
  if (smartFilter.value.instCode)
    params.set("filter_inst_code", smartFilter.value.instCode);
  if (smartFilter.value.instName)
    params.set("filter_inst_name", smartFilter.value.instName);
  if (smartFilter.value.branch)
    params.set("filter_branch", smartFilter.value.branch);
  if (smartFilter.value.noOfDays)
    params.set("filter_no_of_days", smartFilter.value.noOfDays);
  if (smartFilter.value.rate) params.set("filter_rate", smartFilter.value.rate);
  try {
    const res = await listInvestmentAccrual(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    grandAmount.value = Number(res.meta?.grandTotalAmount ?? 0);
    grandAmtPerDay.value = Number(res.meta?.grandTotalAmtPerDay ?? 0);
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load list.",
    );
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: SortKey) {
  if (sortBy.value === col)
    sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
    sortBy.value = col;
    sortDir.value = "asc";
  }
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
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = {
    investNo: "",
    instCode: "",
    instName: "",
    branch: "",
    noOfDays: "",
    rate: "",
  };
}

async function postSelected() {
  const ids = Array.from(selected.value);
  if (ids.length === 0) {
    toast.info("Nothing selected", "Select at least one accrual first.");
    return;
  }

  const ok = await confirm({
    title: "Post to TB",
    message:
      ids.length === 1
        ? "Post this accrual to the Trial Balance? This creates a posting_master entry, updates the accrual, and writes DT/CR posting_details lines via legacy stored procedures."
        : `Post ${ids.length} accruals to the Trial Balance? Each one creates a posting_master entry, updates the accrual, and writes DT/CR posting_details lines via legacy stored procedures.`,
    confirmText: "Post",
  });
  if (!ok) return;

  posting.value = true;
  try {
    const res = await postInvestmentAccrualToTb(ids);
    const { successCount, failureCount, failed } = res.data;

    if (successCount > 0 && failureCount === 0) {
      toast.success(
        "Posted to TB",
        `${successCount} accrual${successCount === 1 ? "" : "s"} posted.`,
      );
    } else if (successCount > 0 && failureCount > 0) {
      const firstReason = failed[0]?.reason ?? "";
      toast.info(
        "Partial success",
        `${successCount} posted, ${failureCount} failed. First failure: ${firstReason}`,
      );
    } else {
      const firstReason = failed[0]?.reason ?? "Post-to-TB failed.";
      toast.error("Post failed", firstReason);
    }

    // Rows that were posted drop out of the list (endpoint filters
    // pmt_posting_no IS NULL). Clear stale selections and refetch.
    clearSelection();
    await loadRows();
  } catch (e) {
    toast.error(
      "Post failed",
      e instanceof Error ? e.message : "Unable to call post-to-TB.",
    );
  } finally {
    posting.value = false;
  }
}

function formatMoney(value: number | null): string {
  if (value === null || Number.isNaN(value)) return "-";
  return value.toLocaleString("en-MY", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatAmtPerDay(value: number | null): string {
  if (value === null || Number.isNaN(value)) return "-";
  return value.toLocaleString("en-MY", {
    minimumFractionDigits: 4,
    maximumFractionDigits: 4,
  });
}

function formatRate(value: number | null): string {
  if (value === null || Number.isNaN(value)) return "-";
  return Number(value).toFixed(2);
}

function institutionCell(row: InvestmentAccrualRow): string {
  return row.institutionName ?? "-";
}

const exportColumns = [
  "Investment No",
  "Institution Code",
  "Institution",
  "Branch",
  "Start Date",
  "End Date",
  "Created Date",
  "Amount (RM)",
  "No of Days",
  "Amount per day (RM)",
  "Rate (%)",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Accrual",
  apiDataPath: "/investment/accrual",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Investment No": r.investmentNo ?? "",
      "Institution Code": r.institutionCode ?? "",
      Institution: r.institutionName ?? "",
      Branch: r.institutionBranch ?? "",
      "Start Date": r.startDate ?? "",
      "End Date": r.endDate ?? "",
      "Created Date": r.createdDate ?? "",
      "Amount (RM)": r.amount !== null ? formatMoney(r.amount) : "",
      "No of Days": r.noOfDays !== null ? String(r.noOfDays) : "",
      "Amount per day (RM)":
        r.amtPerDay !== null ? formatAmtPerDay(r.amtPerDay) : "",
      "Rate (%)": r.rate !== null ? formatRate(r.rate) : "",
    })),
  datatableRef,
  searchKeyword: q,
  smartFilter,
  applyFilters: () => void loadRows(),
});

async function exportExcel() {
  try {
    if (rows.value.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Accrual");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.investmentNo ?? "",
        r.institutionCode ?? "",
        r.institutionName ?? "",
        r.institutionBranch ?? "",
        r.startDate ?? "",
        r.endDate ?? "",
        r.createdDate ?? "",
        r.amount !== null ? formatMoney(r.amount) : "",
        r.noOfDays !== null ? String(r.noOfDays) : "",
        r.amtPerDay !== null ? formatAmtPerDay(r.amtPerDay) : "",
        r.rate !== null ? formatRate(r.rate) : "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Accrual_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error(
      "Export failed",
      e instanceof Error ? e.message : "Excel export failed.",
    );
  }
}

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    searchDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

onMounted(async () => {
  await loadOptions();
  await loadRows();
});

onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input
        ref="templateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onTemplateFileChange"
      />

      <h1 class="page-title">Investment / Accrual</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Accrual</h1>
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
                @change="
                  page = 1;
                  loadRows();
                "
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
                  @keyup.enter="
                    page = 1;
                    void loadRows();
                  "
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
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-slate-50"
                @click="showSmartFilter = true"
              >
                <Filter class="h-4 w-4" />
                Filter
              </button>
              <span v-if="selected.size > 0" class="text-xs text-slate-500">
                {{ selected.size }} selected
                <button
                  type="button"
                  class="ml-1 text-slate-400 hover:text-slate-600 underline"
                  @click="clearSelection"
                >
                  clear
                </button>
              </span>
              <button
                type="button"
                :disabled="selected.size === 0 || posting"
                :title="
                  selected.size === 0
                    ? 'Select one or more accruals first'
                    : 'Post selected accruals to Trial Balance (creates posting_master + posting_details)'
                "
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                @click="postSelected"
              >
                <Send class="h-4 w-4" />
                {{ posting ? "Posting..." : "Post to TB" }}
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1400px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_invest_no')"
                    >
                      Investment No
                      <span v-if="sortBy === 'dt_invest_no'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_inst_code')"
                    >
                      Institution Code
                      <span v-if="sortBy === 'dt_inst_code'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_inst_name')"
                    >
                      Institution
                      <span v-if="sortBy === 'dt_inst_name'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_branch')"
                    >
                      Branch
                      <span v-if="sortBy === 'dt_branch'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Start Date</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">End Date</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Created Date</th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('dt_amount')"
                    >
                      Amount (RM)
                      <span v-if="sortBy === 'dt_amount'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('dt_no_of_days')"
                    >
                      No of Days
                      <span v-if="sortBy === 'dt_no_of_days'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('dt_amt_per_day')"
                    >
                      Amount per day (RM)
                      <span v-if="sortBy === 'dt_amt_per_day'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('dt_rate')"
                    >
                      Rate (%)
                      <span v-if="sortBy === 'dt_rate'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">
                      <input
                        type="checkbox"
                        :checked="allPageSelected"
                        :indeterminate.prop="!allPageSelected && somePageSelected"
                        :disabled="pageRowKeys.length === 0 || posting"
                        title="Select all on this page"
                        class="cursor-pointer"
                        @change="toggleAllOnPage"
                      />
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="13" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="13" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="row.accrualId ?? row.rowId ?? row.index"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-900">
                      {{ row.investmentNo ?? "-" }}
                    </td>
                    <td class="px-3 py-2">{{ row.institutionCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ institutionCell(row) }}</td>
                    <td class="px-3 py-2">{{ row.institutionBranch ?? "-" }}</td>
                    <td class="whitespace-nowrap px-3 py-2">{{ row.startDate ?? "-" }}</td>
                    <td class="whitespace-nowrap px-3 py-2">{{ row.endDate ?? "-" }}</td>
                    <td class="whitespace-nowrap px-3 py-2">{{ row.createdDate ?? "-" }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatMoney(row.amount) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ row.noOfDays ?? "-" }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatAmtPerDay(row.amtPerDay) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatRate(row.rate) }}
                    </td>
                    <td class="px-3 py-2">
                      <input
                        type="checkbox"
                        :checked="isSelected(row)"
                        :disabled="row.accrualId == null || posting"
                        :title="
                          row.accrualId == null
                            ? 'No accrual id'
                            : 'Select this accrual'
                        "
                        class="cursor-pointer"
                        @change="toggleRow(row)"
                      />
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="rows.length > 0" class="bg-slate-50">
                  <tr class="border-t border-slate-200">
                    <td colspan="8" class="px-3 py-2 text-right text-xs font-semibold text-slate-600">
                      Grand Total
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right text-sm font-semibold tabular-nums">
                      {{ formatMoney(grandAmount) }}
                    </td>
                    <td />
                    <td class="whitespace-nowrap px-3 py-2 text-right text-sm font-semibold tabular-nums">
                      {{ formatAmtPerDay(grandAmtPerDay) }}
                    </td>
                    <td />
                    <td />
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
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleDownloadPDF"
              >
                <Download class="h-3.5 w-3.5" />PDF
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleDownloadCSV"
              >
                <FileDown class="h-3.5 w-3.5" />CSV
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
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
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Investment No</label>
              <input
                v-model="smartFilter.investNo"
                type="text"
                placeholder="e.g. INV-2024/0001"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700"
                  >Institution Code</label
                >
                <select
                  v-model="smartFilter.instCode"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="opt in options.institution" :key="opt.id" :value="opt.id">
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700"
                  >Institution Name</label
                >
                <input
                  v-model="smartFilter.instName"
                  type="text"
                  placeholder="e.g. BANK ISLAM"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Branch</label>
              <input
                v-model="smartFilter.branch"
                type="text"
                placeholder="e.g. KUALA LUMPUR"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">No of Days</label>
                <input
                  v-model="smartFilter.noOfDays"
                  type="text"
                  inputmode="numeric"
                  placeholder="e.g. 30"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Rate (%)</label>
                <input
                  v-model="smartFilter.rate"
                  type="text"
                  inputmode="decimal"
                  placeholder="e.g. 3.50"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
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
