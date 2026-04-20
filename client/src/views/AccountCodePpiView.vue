<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { getAccountCodePpiOptions, listAccountCodePpi } from "@/api/cms";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { useToast } from "@/composables/useToast";
import type { AccountCodePpiOptions, AccountCodePpiRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<AccountCodePpiRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("acm_acct_code");
const sortDir = ref<"asc" | "desc">("asc");
const showSmartFilter = ref(false);

const topFilter = ref({
  cmFundType: "",
  acmAcctActivity: "",
  acmAcctType: "",
  cmAccountCode: "",
});
const smartFilter = ref({
  acmAcctCodeSmartFilter: "",
  acmAcctDesc: "",
  acmAcctLevel: "",
  acmBehavior: "",
  acmAcctStatus: "",
});

const options = ref<AccountCodePpiOptions>({
  topFilter: { fundType: [], accountType: [], accountClass: [], accountCode: [] },
  smartFilter: { accountCode: [], accountDesc: [], accountLevel: [], statementItem: [], status: [] },
});

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

async function loadOptions() {
  try {
    const res = await getAccountCodePpiOptions();
    options.value = res.data;
  } catch (e) {
    toast.error("Failed to load filter options", e instanceof Error ? e.message : "Unable to fetch options.");
  }
}

async function loadRows() {
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
    ...(topFilter.value.cmFundType ? { cm_fund_type: topFilter.value.cmFundType } : {}),
    ...(topFilter.value.acmAcctActivity ? { acm_acct_activity: topFilter.value.acmAcctActivity } : {}),
    ...(topFilter.value.acmAcctType ? { acm_acct_type: topFilter.value.acmAcctType } : {}),
    ...(topFilter.value.cmAccountCode ? { cm_account_code: topFilter.value.cmAccountCode } : {}),
    ...(smartFilter.value.acmAcctCodeSmartFilter ? { acm_acct_code_smart_filter: smartFilter.value.acmAcctCodeSmartFilter } : {}),
    ...(smartFilter.value.acmAcctDesc ? { acm_acct_desc: smartFilter.value.acmAcctDesc } : {}),
    ...(smartFilter.value.acmAcctLevel ? { acm_acct_level: smartFilter.value.acmAcctLevel } : {}),
    ...(smartFilter.value.acmBehavior ? { acm_behavior: smartFilter.value.acmBehavior } : {}),
    ...(smartFilter.value.acmAcctStatus ? { acm_acct_status: smartFilter.value.acmAcctStatus } : {}),
  });
  try {
    const res = await listAccountCodePpi(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load rows.");
  }
}

function toggleSort(col: string) {
  if (sortBy.value === col) {
    sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  } else {
    sortBy.value = col;
    sortDir.value = "asc";
  }
  void loadRows();
}

function applyTopFilter() {
  page.value = 1;
  void loadRows();
}

function resetTopFilter() {
  topFilter.value = { cmFundType: "", acmAcctActivity: "", acmAcctType: "", cmAccountCode: "" };
  page.value = 1;
  void loadRows();
}

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = { acmAcctCodeSmartFilter: "", acmAcctDesc: "", acmAcctLevel: "", acmBehavior: "", acmAcctStatus: "" };
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

const exportColumns = [
  "Account Code",
  "Account Desc",
  "Account Level",
  "Account Type",
  "Class",
  "Fund Type",
  "Statement Item",
  "Status",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "List of Account Code (PPI)",
  apiDataPath: "/setup/account-code-ppi",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Account Code": r.acmAcctCode,
      "Account Desc": r.acmAcctDesc,
      "Account Level": r.acmAcctLevel ?? "",
      "Account Type": r.acmAcctActivity ?? "",
      Class: r.acmAcctType ?? "",
      "Fund Type": r.fundType ?? "",
      "Statement Item": r.acmBehavior ?? "",
      Status: r.acmAcctStatus,
    })),
  datatableRef,
  searchKeyword: q,
  smartFilter,
  applyFilters: () => void loadRows(),
});

async function exportExcel() {
  try {
    const data = rows.value;
    if (data.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Account Code PPI");
    ws.addRow(["No", ...exportColumns]);
    data.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.acmAcctCode,
        r.acmAcctDesc,
        r.acmAcctLevel ?? "",
        r.acmAcctActivity ?? "",
        r.acmAcctType ?? "",
        r.fundType ?? "",
        r.acmBehavior ?? "",
        r.acmAcctStatus,
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `List_of_Account_Code_PPI_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    <div class="mx-auto max-w-7xl space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <p class="text-base font-semibold text-slate-500">Setup and Maintenance / General Ledger Structure / List of Account Code (PPI)</p>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Account Code (PPI)</h1>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More">
            <MoreVertical class="h-4 w-4" />
          </button>
        </div>

        <div class="space-y-4 p-4">
          <!-- Top filter -->
          <div class="grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-slate-50/60 p-3 md:grid-cols-4">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Fund Type</label>
              <select v-model="topFilter.cmFundType" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option v-for="opt in options.topFilter.fundType" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Account Type</label>
              <select v-model="topFilter.acmAcctActivity" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option v-for="opt in options.topFilter.accountType" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Account Class</label>
              <select v-model="topFilter.acmAcctType" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option v-for="opt in options.topFilter.accountClass" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Account Code</label>
              <select v-model="topFilter.cmAccountCode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All</option>
                <option v-for="opt in options.topFilter.accountCode" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div class="flex items-end justify-end gap-2 md:col-span-4">
              <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="resetTopFilter">Reset</button>
              <button type="button" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white" @click="applyTopFilter">Apply</button>
            </div>
          </div>

          <!-- Toolbar: display size + search + smart filter -->
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="page = 1; loadRows()">
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="q"
                  type="search"
                  placeholder="Filter rows..."
                  class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm"
                  @keyup.enter="page = 1; void loadRows()"
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
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm" @click="showSmartFilter = true">
                <Filter class="h-4 w-4" />Filter
              </button>
            </div>
          </div>

          <!-- Datatable -->
          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1200px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('acm_acct_code')">Account Code</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('acm_acct_desc')">Account Desc</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('acm_acct_level')">Account Level</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('acm_acct_activity')">Account Type</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('acm_acct_type')">Class</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Fund Type</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('acm_behavior')">Statement Item</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('acm_acct_status')">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="rows.length === 0">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr v-for="row in rows" :key="row.acmAcctCode" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.acmAcctCode }}</td>
                    <td class="px-3 py-2">{{ row.acmAcctDesc }}</td>
                    <td class="px-3 py-2">{{ row.acmAcctLevel }}</td>
                    <td class="px-3 py-2">{{ row.acmAcctActivity }}</td>
                    <td class="px-3 py-2">{{ row.acmAcctType }}</td>
                    <td class="whitespace-pre-line px-3 py-2">{{ row.fundType }}</td>
                    <td class="px-3 py-2">{{ row.acmBehavior }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="row.acmAcctStatus === 'ACTIVE' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                      >
                        {{ row.acmAcctStatus }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Footer: exports + pagination -->
          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">
              Showing {{ startIdx }}-{{ endIdx }} of {{ total }}
            </div>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="page <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="prevPage">Prev</button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" :disabled="page >= totalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="nextPage">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadPDF">
                <Download class="h-3.5 w-3.5" />PDF
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadCSV">
                <FileDown class="h-3.5 w-3.5" />CSV
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportExcel">
                <FileSpreadsheet class="h-3.5 w-3.5" />Excel
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>

    <!-- Smart filter modal -->
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Account Code</label>
              <select v-model="smartFilter.acmAcctCodeSmartFilter" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.accountCode" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Account Desc</label>
              <select v-model="smartFilter.acmAcctDesc" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.accountDesc" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Account Level</label>
              <select v-model="smartFilter.acmAcctLevel" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.accountLevel" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Statement Item</label>
              <select v-model="smartFilter.acmBehavior" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.statementItem" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="smartFilter.acmAcctStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.status" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="resetSmartFilter">Reset</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="applySmartFilter">OK</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
