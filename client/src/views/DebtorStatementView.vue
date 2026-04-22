<script setup lang="ts">
/**
 * Debtor Portal > Financial Information > Debtors Statement (MENUID 2267).
 *
 * Runs the legacy `NF_BL_DP_DEBTORS_STATEMENT` ledger: a 7-branch UNION
 * over invoices, credit / debit / discount notes, deposits and
 * knock-offs for the logged-in debtor. The running outstanding /
 * advance columns are produced server-side, and the grand totals are
 * rendered as a sticky footer row to match the legacy layout.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { listDebtorStatement } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { DebtorStatementFooter, DebtorStatementRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<DebtorStatementRow[]>([]);
const page = ref(1);
const limit = ref(25);
const q = ref("");
const total = ref(0);
const loading = ref(false);
const debtorIdDisplay = ref<string | null>(null);
const footer = ref<DebtorStatementFooter>({
  debit: 0, credit: 0, cn: 0, dn: 0, dc: 0, advance: 0, balance: 0,
});

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function fmtDate(d: string | null): string {
  if (!d) return "-";
  try { return new Date(d).toLocaleDateString("en-GB"); } catch { return String(d); }
}

function fmtMoney(n: number | null | undefined): string {
  if (n === null || n === undefined) return "-";
  if (!Number.isFinite(n)) return String(n);
  return new Intl.NumberFormat("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

function fmtMoneyOrDash(n: number | null | undefined): string {
  if (n === null || n === undefined || n === 0) return "-";
  return fmtMoney(n);
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    ...(q.value ? { q: q.value } : {}),
  });
  try {
    const res = await listDebtorStatement(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    const d = res.meta?.debtorId;
    debtorIdDisplay.value = typeof d === "string" ? d : null;
    if (res.meta?.footer) footer.value = res.meta.footer;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load statement.");
  } finally {
    loading.value = false;
  }
}

function prevPage() { if (page.value > 1) { page.value -= 1; void loadRows(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; void loadRows(); } }

const exportColumns = [
  "Transaction Date",
  "Document No",
  "Reference No",
  "Description",
  "Total Invoice (RM)",
  "Total Payment (RM)",
  "CN",
  "DC",
  "DN",
  "Payment in Advance",
  "Outstanding Amount",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "List Of Debtor Account Statement",
  apiDataPath: "/portal/debtor/statement",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Transaction Date": fmtDate(r.transactionDate),
      "Document No": r.documentNo ?? "",
      "Reference No": r.refNo ?? "",
      Description: r.description ?? "",
      "Total Invoice (RM)": fmtMoneyOrDash(r.debit),
      "Total Payment (RM)": r.credit ? `(${fmtMoney(r.credit)})` : "-",
      CN: fmtMoneyOrDash(r.cn),
      DC: fmtMoneyOrDash(r.dc),
      DN: fmtMoneyOrDash(r.dn),
      "Payment in Advance": fmtMoneyOrDash(r.advance),
      "Outstanding Amount": fmtMoneyOrDash(r.outstanding),
    })),
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
    const ws = wb.addWorksheet("Statement");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        fmtDate(r.transactionDate),
        r.documentNo ?? "",
        r.refNo ?? "",
        r.description ?? "",
        r.debit || 0,
        r.credit || 0,
        r.cn || 0,
        r.dc || 0,
        r.dn || 0,
        r.advance || 0,
        r.outstanding || 0,
      ]);
    });
    ws.addRow([
      "",
      "Total",
      "",
      "",
      "",
      footer.value.debit,
      footer.value.credit,
      footer.value.cn,
      footer.value.dc,
      footer.value.dn,
      footer.value.advance,
      footer.value.balance,
    ]);
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Debtor_Statement_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

onMounted(() => { void loadRows(); });
onUnmounted(() => { if (searchDebounce) clearTimeout(searchDebounce); });
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <p class="text-base font-semibold text-slate-500">Debtor Portal / Financial Information / Debtors Statement</p>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <div>
            <h1 class="text-base font-semibold text-slate-900">List Of Debtor Account Statement</h1>
            <p v-if="debtorIdDisplay" class="mt-0.5 text-xs text-slate-500">
              Debtor: <span class="font-medium text-slate-700">{{ debtorIdDisplay }}</span>
            </p>
          </div>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More">
            <MoreVertical class="h-4 w-4" />
          </button>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="page = 1; loadRows()">
                <option v-for="n in [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="q"
                  type="search"
                  placeholder="Filter document / description / ref..."
                  class="w-72 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm"
                  @keyup.enter="page = 1; void loadRows()"
                />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" aria-label="Clear search" @click="q = ''">
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full min-w-[1400px] text-sm">
              <thead class="bg-slate-50">
                <tr class="border-b border-slate-200 text-left">
                  <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                  <th class="px-3 py-2 text-xs font-semibold uppercase">Date</th>
                  <th class="px-3 py-2 text-xs font-semibold uppercase">Document No</th>
                  <th class="px-3 py-2 text-xs font-semibold uppercase">Ref No</th>
                  <th class="px-3 py-2 text-xs font-semibold uppercase">Description</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Invoice</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Payment</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold uppercase">CN</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold uppercase">DC</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold uppercase">DN</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Advance</th>
                  <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Outstanding</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading">
                  <td colspan="12" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                </tr>
                <tr v-else-if="rows.length === 0">
                  <td colspan="12" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                </tr>
                <tr v-for="row in rows" :key="`${row.documentNo}_${row.index}`" class="border-b border-slate-100 hover:bg-slate-50">
                  <td class="px-3 py-2">{{ row.index }}</td>
                  <td class="px-3 py-2">{{ fmtDate(row.transactionDate) }}</td>
                  <td class="px-3 py-2 font-medium text-slate-900">{{ row.documentNo ?? "-" }}</td>
                  <td class="px-3 py-2">{{ row.refNo ?? "-" }}</td>
                  <td class="px-3 py-2">{{ row.description ?? "-" }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoneyOrDash(row.debit) }}</td>
                  <td class="px-3 py-2 text-right">{{ row.credit ? `(${fmtMoney(row.credit)})` : "-" }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoneyOrDash(row.cn) }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoneyOrDash(row.dc) }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoneyOrDash(row.dn) }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoneyOrDash(row.advance) }}</td>
                  <td class="px-3 py-2 text-right font-medium">{{ fmtMoneyOrDash(row.outstanding) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="rows.length > 0">
                <tr class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                  <td class="px-3 py-2" colspan="5">Total</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoney(footer.debit) }}</td>
                  <td class="px-3 py-2 text-right">{{ footer.credit ? `(${fmtMoney(footer.credit)})` : "-" }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoney(footer.cn) }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoney(footer.dc) }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoney(footer.dn) }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoney(footer.advance) }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoney(footer.balance) }}</td>
                </tr>
              </tfoot>
            </table>
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
  </AdminLayout>
</template>
