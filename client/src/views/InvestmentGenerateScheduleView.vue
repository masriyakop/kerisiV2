<script setup lang="ts">
/**
 * Investment / Generate Schedule (PAGEID 1206, MENUID 1475)
 *
 * Source: FIMS BL `API_INVESTMENT_GENERATE_ACCRUAL`.
 * Read-only datatable joining investment_profile + investment_type,
 * scoped to ipf_status IN ('APPROVE','MATURED') AND NOT EXISTS a
 * matching investment_accrual row — i.e. investments still awaiting
 * an accrual schedule.
 *
 * The Generate Schedule write flow (legacy
 * INSERT_UPDATE_INVESTMENT_ACCRUAL mode=generateScheduleAccrual —
 * calls the `investment_accrual(?)` stored procedure per selected
 * investment) is NOT migrated yet; the header button + row
 * checkboxes render disabled with tooltips.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  CalendarClock,
  Download,
  FileDown,
  FileSpreadsheet,
  MoreVertical,
  Search,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  generateInvestmentSchedules,
  listInvestmentGenerateSchedule,
} from "@/api/cms";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useToast } from "@/composables/useToast";
import type { InvestmentGenerateScheduleRow } from "@/types";

const toast = useToast();
const { confirm } = useConfirmDialog();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<InvestmentGenerateScheduleRow[]>([]);
const loading = ref(false);
const total = ref(0);
const grandTotalPrincipal = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

// Selection state for the "Generate Schedule" bulk action. Keyed by
// ipf_investment_no so selections persist across pagination and
// sorting changes (the legacy UI behaved the same way).
const selected = ref<Set<string>>(new Set());
const generating = ref(false);

function isSelected(row: InvestmentGenerateScheduleRow): boolean {
  return row.investmentNo != null && selected.value.has(row.investmentNo);
}

function toggleRow(row: InvestmentGenerateScheduleRow) {
  if (!row.investmentNo) return;
  const next = new Set(selected.value);
  if (next.has(row.investmentNo)) next.delete(row.investmentNo);
  else next.add(row.investmentNo);
  selected.value = next;
}

const pageRowKeys = computed<string[]>(() =>
  rows.value
    .map((r) => r.investmentNo)
    .filter((n): n is string => typeof n === "string" && n.length > 0),
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

type SortKey =
  | "dt_invest_no"
  | "dt_invest_type"
  | "dt_rate"
  | "dt_amount"
  | "dt_start_date"
  | "dt_end_date";

const sortBy = ref<SortKey>("dt_invest_no");
const sortDir = ref<"asc" | "desc">("asc");

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
  });
  if (q.value.trim()) params.set("q", q.value.trim());
  try {
    const res = await listInvestmentGenerateSchedule(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    grandTotalPrincipal.value = Number(res.meta?.grandTotalPrincipal ?? 0);
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

async function generateSelected() {
  const numbers = Array.from(selected.value);
  if (numbers.length === 0) {
    toast.info("Nothing selected", "Select at least one investment first.");
    return;
  }

  const ok = await confirm({
    title: "Generate Schedule",
    message:
      numbers.length === 1
        ? `Generate accrual schedule for investment ${numbers[0]}? This calls the legacy stored procedure investment_accrual().`
        : `Generate accrual schedules for ${numbers.length} investments? This calls the legacy stored procedure investment_accrual() for each of them.`,
    confirmText: "Generate",
  });
  if (!ok) return;

  generating.value = true;
  try {
    const res = await generateInvestmentSchedules(numbers);
    const { successCount, failureCount, failed } = res.data;

    if (successCount > 0 && failureCount === 0) {
      toast.success(
        "Schedule generated",
        `${successCount} investment${successCount === 1 ? "" : "s"} processed.`,
      );
    } else if (successCount > 0 && failureCount > 0) {
      const firstReason = failed[0]?.reason ?? "";
      toast.info(
        "Partial success",
        `${successCount} succeeded, ${failureCount} failed. First failure: ${firstReason}`,
      );
    } else {
      const firstReason = failed[0]?.reason ?? "Process unsuccessful.";
      toast.error("Generation failed", firstReason);
    }

    // Rows that just had schedules generated drop out of the list
    // (the endpoint filters NOT EXISTS on investment_accrual), so
    // clear the now-stale selection and refetch.
    clearSelection();
    await loadRows();
  } catch (e) {
    toast.error(
      "Generation failed",
      e instanceof Error ? e.message : "Unable to call generate schedule.",
    );
  } finally {
    generating.value = false;
  }
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

const exportColumns = [
  "Investment No",
  "Investment Type",
  "Rate (%)",
  "Principal Amount (RM)",
  "Start Date",
  "End Date",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Generate Schedule",
  apiDataPath: "/investment/generate-schedule",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Investment No": r.investmentNo ?? "",
      "Investment Type": r.investmentType ?? "",
      "Rate (%)": r.rate !== null ? formatRate(r.rate) : "",
      "Principal Amount (RM)":
        r.principalAmount !== null ? formatMoney(r.principalAmount) : "",
      "Start Date": r.startDate ?? "",
      "End Date": r.endDate ?? "",
    })),
  datatableRef,
  searchKeyword: q,
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
    const ws = wb.addWorksheet("Generate Schedule");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.investmentNo ?? "",
        r.investmentType ?? "",
        r.rate !== null ? formatRate(r.rate) : "",
        r.principalAmount !== null ? formatMoney(r.principalAmount) : "",
        r.startDate ?? "",
        r.endDate ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `GenerateSchedule_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

      <h1 class="page-title">Investment / Generate Schedule</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Generate Schedule</h1>
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
              <div class="flex items-center gap-2">
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
                  :disabled="selected.size === 0 || generating"
                  :title="
                    selected.size === 0
                      ? 'Select one or more investments first'
                      : 'Calls investment_accrual() stored procedure for each selected investment'
                  "
                  class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                  @click="generateSelected"
                >
                  <CalendarClock class="h-4 w-4" />
                  {{ generating ? "Generating..." : "Generate Schedule" }}
                </button>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[900px] text-sm">
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
                      @click="toggleSort('dt_invest_type')"
                    >
                      Investment Type
                      <span v-if="sortBy === 'dt_invest_type'">{{
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
                      @click="toggleSort('dt_amount')"
                    >
                      Principal Amount (RM)
                      <span v-if="sortBy === 'dt_amount'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_start_date')"
                    >
                      Start Date
                      <span v-if="sortBy === 'dt_start_date'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_end_date')"
                    >
                      End Date
                      <span v-if="sortBy === 'dt_end_date'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">
                      <input
                        type="checkbox"
                        :checked="allPageSelected"
                        :indeterminate.prop="!allPageSelected && somePageSelected"
                        :disabled="pageRowKeys.length === 0"
                        title="Select all on this page"
                        class="cursor-pointer"
                        @change="toggleAllOnPage"
                      />
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="row.investmentId ?? row.index"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-900">
                      {{ row.investmentNo ?? "-" }}
                    </td>
                    <td class="px-3 py-2">{{ row.investmentType ?? "-" }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatRate(row.rate) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatMoney(row.principalAmount) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">{{ row.startDate ?? "-" }}</td>
                    <td class="whitespace-nowrap px-3 py-2">{{ row.endDate ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <input
                        type="checkbox"
                        :checked="isSelected(row)"
                        :disabled="!row.investmentNo || generating"
                        :title="
                          row.investmentNo
                            ? 'Select this investment'
                            : 'No investment number'
                        "
                        class="cursor-pointer"
                        @change="toggleRow(row)"
                      />
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="rows.length > 0" class="bg-slate-50">
                  <tr class="border-t border-slate-200">
                    <td colspan="4" class="px-3 py-2 text-right text-xs font-semibold text-slate-600">
                      Grand Total
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right text-sm font-semibold tabular-nums">
                      {{ formatMoney(grandTotalPrincipal) }}
                    </td>
                    <td />
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
  </AdminLayout>
</template>
