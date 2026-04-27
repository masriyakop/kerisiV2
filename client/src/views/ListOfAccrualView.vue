<script setup lang="ts">
/**
 * Investment / List Of Accrual (PAGEID 1548, MENUID 1877)
 *
 * Source: FIMS BL `API_LIST_OF_ACCRUAL` (action=listing_all_dt).
 * Read-only datatable joining investment_profile +
 * investment_institution + investment_accrual with a 6-field smart
 * filter (Batch No / Institution / Period / Tenure / Amount / Status)
 * matching the legacy smart filter modal.
 *
 * The Accrual detail page (legacy menuID 1878) is NOT migrated yet;
 * the Action column renders a disabled View button until it lands.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  Eye,
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
import {
  getListOfAccrualOptions,
  listListOfAccrual,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  ListOfAccrualOptions,
  ListOfAccrualRow,
  ListOfAccrualSmartFilter,
} from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<ListOfAccrualRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

type SortKey =
  | "dt_batch"
  | "dt_institution"
  | "dt_invest_no"
  | "dt_tenure"
  | "dt_amount"
  | "dt_rate"
  | "dt_total_sum"
  | "dt_status";

const sortBy = ref<SortKey>("dt_batch");
const sortDir = ref<"asc" | "desc">("desc");

const showSmartFilter = ref(false);
const smartFilter = ref<ListOfAccrualSmartFilter>({
  batch: "",
  institution: "",
  period: "",
  tenure: "",
  amount: "",
  status: "",
});
const options = ref<ListOfAccrualOptions>({ institution: [], status: [] });

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

async function loadOptions() {
  try {
    const res = await getListOfAccrualOptions();
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
  if (smartFilter.value.batch) params.set("filter_batch", smartFilter.value.batch);
  if (smartFilter.value.institution)
    params.set("filter_institution", smartFilter.value.institution);
  if (smartFilter.value.period) params.set("filter_period", smartFilter.value.period);
  if (smartFilter.value.tenure) params.set("filter_tenure", smartFilter.value.tenure);
  if (smartFilter.value.amount) params.set("filter_amount", smartFilter.value.amount);
  if (smartFilter.value.status) params.set("filter_status", smartFilter.value.status);
  try {
    const res = await listListOfAccrual(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
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
  if (sortBy.value === col) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
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
    batch: "",
    institution: "",
    period: "",
    tenure: "",
    amount: "",
    status: "",
  };
}

function formatMoney(value: number | null): string {
  if (value === null || Number.isNaN(value)) return "-";
  return value.toLocaleString("en-MY", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatRate(value: number | null): string {
  if (value === null || Number.isNaN(value)) return "-";
  return Number(value).toFixed(2);
}

function institutionCell(row: ListOfAccrualRow): string {
  const parts: string[] = [];
  if (row.institutionCode) parts.push(`[${row.institutionCode}]`);
  if (row.institutionDesc) parts.push(row.institutionDesc);
  const base = parts.join(" ").trim();
  return row.institutionBranch ? `${base} - ${row.institutionBranch}` : base;
}

function investmentCell(row: ListOfAccrualRow): string {
  const lines = [row.investmentNo, row.certificateNo].filter((v): v is string => !!v);
  return lines.length === 0 ? "-" : lines.join(" / ");
}

function tenureCell(row: ListOfAccrualRow): string {
  const parts: string[] = [];
  if (row.period !== null) {
    parts.push(`${row.period}${row.tenureDesc ? " " + row.tenureDesc : ""}`);
  } else if (row.tenureDesc) {
    parts.push(row.tenureDesc);
  }
  const range = [row.startDate, row.endDate].filter((v): v is string => !!v);
  if (range.length > 0) parts.push(range.join(" - "));
  return parts.length === 0 ? "-" : parts.join(" / ");
}

const exportColumns = [
  "Batch No",
  "Institution",
  "Investment No / Certificate No",
  "Tenure / Period Duration",
  "Amount (RM)",
  "Rate (%)",
  "Total Sum Accrual",
  "Status",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "List Of Accrual",
  apiDataPath: "/investment/list-of-accrual",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Batch No": r.batchNo ?? "",
      Institution: institutionCell(r),
      "Investment No / Certificate No": investmentCell(r),
      "Tenure / Period Duration": tenureCell(r),
      "Amount (RM)": r.principalAmount !== null ? formatMoney(r.principalAmount) : "",
      "Rate (%)": r.rate !== null ? formatRate(r.rate) : "",
      "Total Sum Accrual": formatMoney(r.totalSum),
      Status: r.status ?? "",
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
    const ws = wb.addWorksheet("List Of Accrual");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.batchNo ?? "",
        institutionCell(r),
        investmentCell(r),
        tenureCell(r),
        r.principalAmount !== null ? formatMoney(r.principalAmount) : "",
        r.rate !== null ? formatRate(r.rate) : "",
        formatMoney(r.totalSum),
        r.status ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `ListOfAccrual_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

function statusBadge(status: string | null): string {
  switch (status) {
    case "APPROVE":
    case "APPROVED":
      return "bg-emerald-100 text-emerald-700";
    case "PENDING":
      return "bg-amber-100 text-amber-700";
    case "CANCEL":
    case "CANCELLED":
      return "bg-rose-100 text-rose-700";
    default:
      return "bg-slate-100 text-slate-500";
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

      <h1 class="page-title">Investment / List of Accrual</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Accrual</h1>
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
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1200px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_batch')"
                    >
                      Batch No
                      <span v-if="sortBy === 'dt_batch'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_institution')"
                    >
                      Institution
                      <span v-if="sortBy === 'dt_institution'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_invest_no')"
                    >
                      Investment No / Certificate No
                      <span v-if="sortBy === 'dt_invest_no'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_tenure')"
                    >
                      Tenure / Period Duration
                      <span v-if="sortBy === 'dt_tenure'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
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
                      @click="toggleSort('dt_rate')"
                    >
                      Rate (%)
                      <span v-if="sortBy === 'dt_rate'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('dt_total_sum')"
                    >
                      Total Sum Accrual
                      <span v-if="sortBy === 'dt_total_sum'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_status')"
                    >
                      Status
                      <span v-if="sortBy === 'dt_status'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="row.investmentId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-900">
                      {{ row.batchNo ?? "-" }}
                    </td>
                    <td class="px-3 py-2">{{ institutionCell(row) }}</td>
                    <td class="px-3 py-2">{{ investmentCell(row) }}</td>
                    <td class="px-3 py-2">{{ tenureCell(row) }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatMoney(row.principalAmount) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatRate(row.rate) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatMoney(row.totalSum) }}
                    </td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="statusBadge(row.status)"
                      >
                        {{ row.status ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2">
                      <button
                        type="button"
                        disabled
                        title="View accrual detail (legacy menuID 1878 not yet migrated)"
                        class="cursor-not-allowed rounded p-1 text-slate-300"
                      >
                        <Eye class="h-3.5 w-3.5" />
                      </button>
                    </td>
                  </tr>
                </tbody>
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Batch No</label>
              <input
                v-model="smartFilter.batch"
                type="text"
                placeholder="e.g. 1-JAN/2024"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Institution</label>
              <select
                v-model="smartFilter.institution"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.institution" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Period</label>
                <input
                  v-model="smartFilter.period"
                  type="text"
                  placeholder="e.g. 12"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tenure</label>
                <input
                  v-model="smartFilter.tenure"
                  type="text"
                  placeholder="e.g. MONTH"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Amount (RM)</label>
              <div class="flex items-stretch">
                <span
                  class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-100 px-3 text-sm text-slate-500"
                  >MYR</span
                >
                <input
                  v-model="smartFilter.amount"
                  type="text"
                  inputmode="decimal"
                  placeholder="e.g. 10000"
                  class="w-full rounded-r-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select
                v-model="smartFilter.status"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.status" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
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
