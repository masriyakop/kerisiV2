<script setup lang="ts">
/**
 * Cashbook / List Of CashBook (Daily|Monthly)
 * - Daily  : PAGEID 1397, MENUID 1702, `type=DAILY`
 * - Monthly: PAGEID 2024, MENUID 2471, `type=MONTHLY`
 *
 * Legacy FIMS BL `NF_BL_CC_CASHBOOK`. Read-only listing with smart-filter
 * dropdowns (Account Code, Trans Date, Status Recon, Recon Flag, Type,
 * Period) and totals footer (debit / credit sums). Columns follow the
 * legacy `dt_bi`. `Reference Document` column and `Type` column are kept
 * hidden (d-none) exactly like the legacy UI.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { getCashbookListOptions, listCashbookList } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { CashbookListOptions, CashbookListRow, CashbookListType } from "@/types";

const props = defineProps<{ type: CashbookListType }>();

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<CashbookListRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const totalDebit = ref(0);
const totalCredit = ref(0);
const sortBy = ref(props.type === "DAILY" ? "cbk_ref_id" : "cbk_trans_period");
const sortDir = ref<"asc" | "desc">(props.type === "DAILY" ? "desc" : "asc");
const showSmartFilter = ref(false);
const loading = ref(false);

const smartFilter = ref({
  acmAcctCodeBank: "",
  cbkTransDate: "",
  cbkReconStatus: "",
  cbkReconFlag: "",
  cbkTransPeriod: "",
});

const options = ref<CashbookListOptions>({
  smartFilter: { accountCode: [], period: [], reconStatus: [], reconFlag: [], type: [] },
});

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const pageName = computed(() => `List Of CashBook (${props.type === "DAILY" ? "Daily" : "Monthly"})`);

function formatCurrency(n: number) {
  return new Intl.NumberFormat("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n ?? 0));
}

async function loadOptions() {
  try {
    const res = await getCashbookListOptions(props.type);
    options.value = res.data;
  } catch (e) {
    toast.error("Failed to load options", e instanceof Error ? e.message : "Unable to fetch options.");
  }
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
    ...(smartFilter.value.acmAcctCodeBank ? { acm_acct_code_bank: smartFilter.value.acmAcctCodeBank } : {}),
    ...(smartFilter.value.cbkTransDate ? { cbk_trans_date: smartFilter.value.cbkTransDate } : {}),
    ...(smartFilter.value.cbkReconStatus ? { cbk_recon_status: smartFilter.value.cbkReconStatus } : {}),
    ...(smartFilter.value.cbkReconFlag ? { cbk_recon_flag: smartFilter.value.cbkReconFlag } : {}),
    ...(smartFilter.value.cbkTransPeriod ? { cbk_trans_period: smartFilter.value.cbkTransPeriod } : {}),
  });
  try {
    const res = await listCashbookList(props.type, `?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    const footer = (res.meta?.footer as { cbkDebitAmt?: number; cbkCreditAmt?: number } | undefined) ?? {};
    totalDebit.value = Number(footer.cbkDebitAmt ?? 0);
    totalCredit.value = Number(footer.cbkCreditAmt ?? 0);
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
  void loadRows();
}

function applySmartFilter() { page.value = 1; showSmartFilter.value = false; void loadRows(); }
function resetSmartFilter() { smartFilter.value = { acmAcctCodeBank: "", cbkTransDate: "", cbkReconStatus: "", cbkReconFlag: "", cbkTransPeriod: "" }; }
function prevPage() { if (page.value > 1) { page.value -= 1; void loadRows(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; void loadRows(); } }

const exportColumns = ["Reference No", "Account Code", "Period", "Bank Ref", "Trans Date", "Debit", "Credit", "Payee / Pay To", "Status Recon", "Recon Flag", "Subsystem Ref"];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: pageName.value,
  apiDataPath: `/cashbook/list/${props.type.toLowerCase()}`,
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Reference No": r.cbkRefId,
      "Account Code": r.acmAcctCodeBank,
      Period: r.cbkTransPeriod ?? "",
      "Bank Ref": r.cbkTransRef ?? "",
      "Trans Date": r.cbkTransDate ?? "",
      Debit: Number(r.cbkDebitAmt).toFixed(2),
      Credit: Number(r.cbkCreditAmt).toFixed(2),
      "Payee / Pay To": r.cbkPaytoName,
      "Status Recon": r.cbkReconStatus,
      "Recon Flag": r.cbkReconFlag,
      "Subsystem Ref": r.cbkSubsystemId ?? "",
    })),
  datatableRef,
  searchKeyword: q,
  smartFilter,
  applyFilters: () => void loadRows(),
});

async function exportExcel() {
  if (rows.value.length === 0) {
    toast.info("No data", "There is nothing to export.");
    return;
  }
  try {
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet(pageName.value.slice(0, 31));
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.cbkRefId,
        r.acmAcctCodeBank,
        r.cbkTransPeriod ?? "",
        r.cbkTransRef ?? "",
        r.cbkTransDate ?? "",
        Number(r.cbkDebitAmt),
        Number(r.cbkCreditAmt),
        r.cbkPaytoName,
        r.cbkReconStatus,
        r.cbkReconFlag,
        r.cbkSubsystemId ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${pageName.value.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
  searchDebounce = setTimeout(() => { searchDebounce = null; page.value = 1; void loadRows(); }, 350);
});

watch(() => props.type, async () => {
  page.value = 1;
  resetSmartFilter();
  await loadOptions();
  await loadRows();
});

onMounted(async () => { await loadOptions(); await loadRows(); });
onUnmounted(() => { if (searchDebounce) clearTimeout(searchDebounce); });
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <h1 class="page-title">Cashbook / {{ pageName }}</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">{{ pageName }}</h1>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More">
            <MoreVertical class="h-4 w-4" />
          </button>
        </div>

        <div class="space-y-4 p-4">
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
                <input v-model="q" type="search" placeholder="Filter rows..." class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm" @keyup.enter="page = 1; void loadRows()" />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="q = ''"><X class="h-3.5 w-3.5" /></button>
              </div>
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm" @click="showSmartFilter = true">
                <Filter class="h-4 w-4" />Filter
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[460px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1200px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('cbk_ref_id')">Reference No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Account Code</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('cbk_trans_period')">Period</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bank Ref</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('cbk_trans_date')">Trans Date</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Debit</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Credit</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Payee / Pay To</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Status Recon</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Recon Flag</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Subsystem Ref</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td colspan="12" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="rows.length === 0"><td colspan="12" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td></tr>
                  <tr v-for="row in rows" :key="row.cbkRefId" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.cbkRefId }}</td>
                    <td class="px-3 py-2">{{ row.acmAcctCodeBank }}</td>
                    <td class="px-3 py-2">{{ row.cbkTransPeriod ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.cbkTransRef ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.cbkTransDate ?? "-" }}</td>
                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.cbkDebitAmt) }}</td>
                    <td class="px-3 py-2 text-right">{{ formatCurrency(row.cbkCreditAmt) }}</td>
                    <td class="px-3 py-2">{{ row.cbkPaytoName }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="row.cbkReconStatus === 'MATCHED' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                      >{{ row.cbkReconStatus }}</span>
                    </td>
                    <td class="px-3 py-2">{{ row.cbkReconFlag || "-" }}</td>
                    <td class="px-3 py-2">{{ row.cbkSubsystemId ?? "-" }}</td>
                  </tr>
                </tbody>
                <tfoot v-if="rows.length > 0" class="bg-slate-50">
                  <tr class="border-t-2 border-slate-200">
                    <td colspan="6" class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-600">Total (filtered)</td>
                    <td class="px-3 py-2 text-right text-sm font-semibold text-slate-900">{{ formatCurrency(totalDebit) }}</td>
                    <td class="px-3 py-2 text-right text-sm font-semibold text-slate-900">{{ formatCurrency(totalCredit) }}</td>
                    <td colspan="4" />
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</div>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="page <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="prevPage">Prev</button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" :disabled="page >= totalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="nextPage">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadPDF"><Download class="h-3.5 w-3.5" />PDF</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadCSV"><FileDown class="h-3.5 w-3.5" />CSV</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportExcel"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
            </div>
          </div>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div v-if="showSmartFilter" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showSmartFilter = false">
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3"><h3 class="text-base font-semibold text-slate-900">Smart filter</h3></div>
          <div class="space-y-4 p-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Account Code</label>
              <select v-model="smartFilter.acmAcctCodeBank" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.accountCode" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Trans Date (dd/mm/yyyy)</label>
              <input v-model="smartFilter.cbkTransDate" type="text" placeholder="31/12/2024" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Period</label>
              <select v-model="smartFilter.cbkTransPeriod" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.period" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status Recon</label>
              <select v-model="smartFilter.cbkReconStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.reconStatus" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Recon Flag</label>
              <select v-model="smartFilter.cbkReconFlag" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.reconFlag" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
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
