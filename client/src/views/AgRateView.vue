<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, Eye, FileDown, FileSpreadsheet, MoreVertical, Plus, Search, Trash2, X } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import FimsListTable, { type FimsColumn } from "@/components/fims/FimsListTable.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  checkAgRateExist,
  deleteAgRatePeriod,
  getAgRateOptions,
  listAgRateLines,
  listAgRates,
  saveAgRateEntry,
  searchAgRateCurrencies,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type {
  AgRateCurrencyOption,
  AgRateEntryInput,
  AgRateLine,
  AgRateOptions,
  AgRateRow,
} from "@/types";

// Setup and Maintenance > Currency > AG Rate (PAGEID 2647 / MENUID 3199).
// Legacy BL: QLA_API_GLOBAL_UPLOADCURRENCY — datatable grouped by year/month
// + manual-entry modal that bulk-inserts a `currency_details` row per
// (currency, day) for the chosen period.
const PAGE_NAME = "AG Rate";
const PAGE_BREADCRUMB = "Setup and Maintenance / Currency / AG Rate";

const MONTH_NAMES_BY_NUM: Record<number, string> = {
  1: "JANUARY", 2: "FEBRUARY", 3: "MARCH", 4: "APRIL", 5: "MAY", 6: "JUNE",
  7: "JULY", 8: "AUGUST", 9: "SEPTEMBER", 10: "OCTOBER", 11: "NOVEMBER", 12: "DECEMBER",
};

const toast = useToast();
const { confirm } = useConfirmDialog();

const rows = ref<AgRateRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const sortBy = ref("cyd_year");
const sortDir = ref<"asc" | "desc">("desc");

const datatableRef = ref<DatatableRefApi | null>(null);
const overflowOpen = ref(false);
const overflowRoot = ref<HTMLElement | null>(null);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const options = ref<AgRateOptions>({ years: [], months: [] });

// View / lines popup
const showLinesModal = ref(false);
const linesYear = ref<number | null>(null);
const linesMonth = ref<string>("");
const lines = ref<AgRateLine[]>([]);

// Entry modal
const showEntryModal = ref(false);
const saving = ref(false);
const entryYear = ref<number>(new Date().getFullYear());
const entryMonthNum = ref<number>(new Date().getMonth() + 1);
const entryRows = ref<{ code: string; label: string; unit: number | null; rate: number | null }[]>([]);
const currencyQuery = ref("");
const currencyOptions = ref<AgRateCurrencyOption[]>([]);
const showCurrencyDropdown = ref(false);

const columns: FimsColumn<AgRateRow>[] = [
  { key: "no", label: "No", value: (r) => r.index },
  { key: "cydYear", label: "Year", sortable: true, sortKey: "cyd_year", hideable: true, value: (r) => r.cydYear ?? "" },
  { key: "cydMonth", label: "Month", sortable: true, sortKey: "cyd_month", hideable: true, value: (r) => r.cydMonth ?? "" },
  { key: "cydFileName", label: "Source", sortable: true, sortKey: "cyd_file_name", hideable: true, value: (r) => r.cydFileName ?? "" },
  { key: "action", label: "Action" },
];

async function loadRows() {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      limit: String(limit.value),
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
      ...(q.value.trim() ? { q: q.value.trim() } : {}),
    });
    const res = await listAgRates(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load AG rates.");
  } finally {
    loading.value = false;
  }
}

async function loadOptions() {
  try {
    const res = await getAgRateOptions();
    options.value = res.data;
  } catch {
    options.value = { years: [], months: [] };
  }
}

function onSort(sortKey: string) {
  if (sortBy.value === sortKey) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else { sortBy.value = sortKey; sortDir.value = "desc"; }
  page.value = 1;
  void loadRows();
}

function prevPage() { if (page.value > 1) { page.value -= 1; void loadRows(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; void loadRows(); } }

async function openLines(row: AgRateRow) {
  linesYear.value = row.cydYear;
  linesMonth.value = row.cydMonth;
  try {
    const res = await listAgRateLines(`?cyd_year=${encodeURIComponent(String(row.cydYear))}&cyd_month=${encodeURIComponent(row.cydMonth)}`);
    lines.value = res.data;
    showLinesModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load rate lines.");
  }
}

function openEntry() {
  entryYear.value = new Date().getFullYear();
  entryMonthNum.value = new Date().getMonth() + 1;
  entryRows.value = [];
  currencyQuery.value = "";
  currencyOptions.value = [];
  showEntryModal.value = true;
}

let currencyDebounce: ReturnType<typeof setTimeout> | null = null;
watch(currencyQuery, (val) => {
  if (currencyDebounce) clearTimeout(currencyDebounce);
  currencyDebounce = setTimeout(async () => {
    currencyDebounce = null;
    try {
      const res = await searchAgRateCurrencies(`?q=${encodeURIComponent(val.trim())}`);
      currencyOptions.value = res.data;
      showCurrencyDropdown.value = true;
    } catch {
      currencyOptions.value = [];
    }
  }, 250);
});

function pickCurrency(opt: AgRateCurrencyOption) {
  if (entryRows.value.some((r) => r.code === opt.code)) {
    toast.info("Already added", `${opt.code} is already in the entry list.`);
    return;
  }
  entryRows.value.push({ code: opt.code, label: opt.label, unit: opt.unit, rate: null });
  currencyQuery.value = "";
  showCurrencyDropdown.value = false;
}

function onCurrencyBlur() {
  // small delay so click on dropdown option fires before it closes
  window.setTimeout(() => {
    showCurrencyDropdown.value = false;
  }, 200);
}

function removeEntryRow(idx: number) {
  entryRows.value.splice(idx, 1);
}

async function saveEntry() {
  if (!entryYear.value || !entryMonthNum.value || entryRows.value.length === 0) {
    toast.error("Validation failed", "Year, month and at least one currency are required.");
    return;
  }
  for (const r of entryRows.value) {
    if (r.rate == null || Number.isNaN(Number(r.rate))) {
      toast.error("Validation failed", `Conversion rate is required for ${r.code}.`);
      return;
    }
  }

  // Warn if a period already exists — the controller's transaction deletes
  // matching currency rows for the period before re-inserting.
  try {
    const monthName = MONTH_NAMES_BY_NUM[entryMonthNum.value];
    if (monthName) {
      const exists = await checkAgRateExist(`?cyd_year=${entryYear.value}&cyd_month=${encodeURIComponent(monthName)}`);
      if (exists.data?.exists) {
        const ok = await confirm({
          title: "Period already has data",
          message: `${monthName} ${entryYear.value} already has rates. Saving will overwrite the entries for the listed currencies. Continue?`,
          confirmText: "Save",
        });
        if (!ok) return;
      }
    }
  } catch {
    // non-fatal; continue
  }

  saving.value = true;
  try {
    const payload: AgRateEntryInput = {
      cydYear: Number(entryYear.value),
      cydMonth: Number(entryMonthNum.value),
      rates: entryRows.value.map((r) => ({
        cymCurrencyCode: r.code,
        cydUnit: r.unit ?? undefined,
        cydConversationRate: Number(r.rate),
      })),
    };
    await saveAgRateEntry(payload);
    toast.success("Saved", "AG rate entries saved.");
    showEntryModal.value = false;
    await loadRows();
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to save.");
  } finally {
    saving.value = false;
  }
}

async function deletePeriod(row: AgRateRow) {
  const ok = await confirm({
    title: "Delete period?",
    message: `This will remove all AG rate rows for ${row.cydMonth} ${row.cydYear}. Continue?`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!ok) return;
  try {
    await deleteAgRatePeriod(row.cydYear, row.cydMonth);
    toast.success("Deleted", "Period removed.");
    await loadRows();
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "Unable to delete.");
  }
}

const {
  templateFileInputRef,
  isGrouped,
  handleSaveTemplate,
  handleLoadTemplate,
  onTemplateFileChange,
  handleUngroupList,
  handleGroupList,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: PAGE_NAME,
  apiDataPath: "/global/ag-rate",
  defaultExportColumns: ["Year", "Month", "Source"],
  getFilteredList: () => (datatableRef.value?.getExportConfig?.()?.data as Record<string, unknown>[]) ?? [],
  datatableRef,
  searchKeyword: q,
  applyFilters: () => void loadRows(),
});

async function exportExcel() {
  try {
    const cfg = datatableRef.value?.getExportConfig?.();
    const columnsOut = cfg?.columns ?? [];
    const data = (cfg?.data as Record<string, unknown>[]) ?? [];
    if (data.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet(PAGE_NAME);
    ws.addRow(["No", ...columnsOut]);
    data.forEach((row, idx) => {
      const values = columnsOut.map((c) => (row[c] ?? "") as string | number);
      ws.addRow([idx + 1, ...values]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${PAGE_NAME.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
  }
}

function onClickOutside(event: MouseEvent) {
  if (!overflowOpen.value) return;
  if (!overflowRoot.value?.contains(event.target as Node)) overflowOpen.value = false;
}

let qSearchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (qSearchDebounce) clearTimeout(qSearchDebounce);
  qSearchDebounce = setTimeout(() => {
    qSearchDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

onMounted(async () => {
  await Promise.all([loadRows(), loadOptions()]);
  document.addEventListener("click", onClickOutside);
});

onUnmounted(() => {
  if (qSearchDebounce) clearTimeout(qSearchDebounce);
  if (currencyDebounce) clearTimeout(currencyDebounce);
  document.removeEventListener("click", onClickOutside);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />

      <h1 class="page-title">{{ PAGE_BREADCRUMB }}</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">{{ PAGE_NAME }}</h1>
          <div class="flex items-center gap-2">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-slate-800" @click="openEntry">
              <Plus class="h-3.5 w-3.5" /> Manual Entry
            </button>
            <div ref="overflowRoot" class="relative">
              <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click.stop="overflowOpen = !overflowOpen">
                <MoreVertical class="h-4 w-4" />
              </button>
              <div v-if="overflowOpen" class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg" @click.stop>
                <button type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleSaveTemplate()">Save template</button>
                <button type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleLoadTemplate()">Load template</button>
                <button v-if="isGrouped" type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleUngroupList()">Ungroup list</button>
                <button v-else type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleGroupList()">Group list</button>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm shadow-sm" @change="page = 1; loadRows()">
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input v-model="q" type="search" placeholder="Filter rows..." class="w-52 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm shadow-sm" @keyup.enter="page = 1; void loadRows()" />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="q = ''; page = 1; loadRows()">
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="loading" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">Loading&hellip;</div>
          <FimsListTable
            v-else
            ref="datatableRef"
            :rows="rows"
            :columns="columns"
            :grouped="isGrouped"
            :sort-by="sortBy"
            :sort-dir="sortDir"
            :row-key="(r) => `${r.cydYear}-${r.cydMonth}`"
            :group-by="(r) => `Year ${r.cydYear}`"
            min-width="800px"
            @sort="onSort"
          >
            <template #action="{ row }">
              <div class="flex items-center gap-1">
                <button type="button" class="rounded p-1 text-slate-600 hover:bg-slate-100" title="View rates" @click="openLines(row as AgRateRow)">
                  <Eye class="h-3.5 w-3.5" />
                </button>
                <button type="button" class="rounded p-1 text-red-600 hover:bg-red-50" title="Delete period" @click="deletePeriod(row as AgRateRow)">
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </template>
          </FimsListTable>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</div>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" :disabled="page <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="prevPage">Prev</button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" :disabled="page >= totalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="nextPage">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadPDF">
                <Download class="h-3.5 w-3.5" /> PDF
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadCSV">
                <FileDown class="h-3.5 w-3.5" /> CSV
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="exportExcel">
                <FileSpreadsheet class="h-3.5 w-3.5" /> Excel
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div v-if="showLinesModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showLinesModal = false">
        <div class="w-full max-w-4xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">AG Rates — {{ linesMonth }} {{ linesYear }}</h3>
            <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100" @click="showLinesModal = false">
              <X class="h-4 w-4" />
            </button>
          </div>
          <div class="max-h-[60vh] overflow-auto p-4">
            <table class="w-full text-sm">
              <thead class="bg-slate-50">
                <tr class="border-b border-slate-200 text-left">
                  <th class="px-3 py-2 text-xs font-semibold uppercase text-slate-600">Currency</th>
                  <th class="px-3 py-2 text-xs font-semibold uppercase text-slate-600">Start</th>
                  <th class="px-3 py-2 text-xs font-semibold uppercase text-slate-600">End</th>
                  <th class="px-3 py-2 text-xs font-semibold uppercase text-slate-600">Type</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-600">Unit</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-600">Rate</th>
                  <th class="px-3 py-2 text-xs font-semibold uppercase text-slate-600">Source</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in lines" :key="line.cydId" class="border-b border-slate-100">
                  <td class="px-3 py-2">{{ line.cymCurrencyCode ?? "-" }}</td>
                  <td class="px-3 py-2">{{ line.cydStartDate ?? "-" }}</td>
                  <td class="px-3 py-2">{{ line.cydEndDate ?? "-" }}</td>
                  <td class="px-3 py-2">{{ line.cydExchangeTypeCode ?? "-" }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ line.cydUnit != null ? Number(line.cydUnit).toString() : "-" }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ line.cydConversationRate != null ? Number(line.cydConversationRate).toFixed(6) : "-" }}</td>
                  <td class="px-3 py-2">{{ line.cydFileName ?? "-" }}</td>
                </tr>
                <tr v-if="lines.length === 0">
                  <td colspan="7" class="px-3 py-6 text-center text-slate-500">No rate lines for this period.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="flex justify-end border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="showLinesModal = false">Close</button>
          </div>
        </div>
      </div>

      <div v-if="showEntryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="!saving && (showEntryModal = false)">
        <div class="w-full max-w-3xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">AG Rate — Manual Entry</h3>
            <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100" :disabled="saving" @click="showEntryModal = false">
              <X class="h-4 w-4" />
            </button>
          </div>
          <div class="space-y-4 p-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Year</label>
                <input v-model.number="entryYear" type="number" min="1900" max="2999" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Month</label>
                <select v-model.number="entryMonthNum" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" :disabled="saving">
                  <option v-for="(name, num) in MONTH_NAMES_BY_NUM" :key="num" :value="num">{{ String(num).padStart(2, "0") }} - {{ name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Add Currency</label>
                <div class="relative">
                  <input
                    v-model="currencyQuery"
                    type="text"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    :disabled="saving"
                    placeholder="Search currency..."
                    @focus="showCurrencyDropdown = true"
                    @blur="onCurrencyBlur"
                  />
                  <div v-if="showCurrencyDropdown && currencyOptions.length > 0" class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                    <button
                      v-for="opt in currencyOptions"
                      :key="opt.id"
                      type="button"
                      class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50"
                      @mousedown.prevent="pickCurrency(opt)"
                    >
                      {{ opt.label }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="rounded-lg border border-slate-200">
              <table class="w-full text-sm">
                <thead class="bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase text-slate-600">Currency</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-600">Unit</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-600">Conversion Rate</th>
                    <th class="px-3 py-2"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in entryRows" :key="row.code" class="border-b border-slate-100">
                    <td class="px-3 py-2">{{ row.label }}</td>
                    <td class="px-3 py-2 text-right">
                      <input v-model.number="row.unit" type="number" step="0.0001" min="0" class="w-28 rounded-lg border border-slate-300 px-2 py-1 text-right text-sm" :disabled="saving" />
                    </td>
                    <td class="px-3 py-2 text-right">
                      <input v-model.number="row.rate" type="number" step="0.000001" min="0" class="w-32 rounded-lg border border-slate-300 px-2 py-1 text-right text-sm" :disabled="saving" />
                    </td>
                    <td class="px-3 py-2 text-right">
                      <button type="button" class="rounded p-1 text-red-600 hover:bg-red-50" :disabled="saving" @click="removeEntryRow(idx)">
                        <Trash2 class="h-3.5 w-3.5" />
                      </button>
                    </td>
                  </tr>
                  <tr v-if="entryRows.length === 0">
                    <td colspan="4" class="px-3 py-6 text-center text-slate-500">Add at least one currency above.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600" :disabled="saving" @click="showEntryModal = false">Cancel</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="saving || entryRows.length === 0" @click="saveEntry">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
