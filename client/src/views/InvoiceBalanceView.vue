<script setup lang="ts">
/**
 * Credit Control / Invoice Balance (PAGEID 2561, MENUID 3388)
 *
 * Source: FIMS BL `MZS_API_CC_INVOICE_BALANCE`. Aggregated outstanding
 * invoices as of `tf_end_date`, computed from rep_aging_debtor with a
 * correlated self-join balance and filtered to balance > 0. Top filters:
 * end-date, customer type, customer id, invoice no. Read-only reporting.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
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
  fetchInvoiceBalanceOptions,
  listInvoiceBalance,
  searchInvoiceBalanceCustomer,
  searchInvoiceBalanceInvoice,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  CcCustomerOption,
  CcOption,
  InvoiceBalanceOptions,
  InvoiceBalanceRow,
} from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<InvoiceBalanceRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const footer = ref({ pdeTransAmt: 0, balance: 0 });
const resolvedEndDate = ref("");

// Top filters
function defaultEndDate(): string {
  return new Date().toISOString().slice(0, 10);
}
const tfEndDate = ref(defaultEndDate());
const tfCustomerType = ref("");
const tfCustomerId = ref("");
const tfInvoiceNo = ref("");

// Customer autosuggest
const custQuery = ref("");
const custOptions = ref<CcCustomerOption[]>([]);
const showCustDropdown = ref(false);
let custTimer: number | null = null;

// Invoice autosuggest
const invQuery = ref("");
const invOptions = ref<CcOption[]>([]);
const showInvDropdown = ref(false);
let invTimer: number | null = null;

const options = ref<InvoiceBalanceOptions>({ customerType: [] });

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() =>
  Math.min(page.value * limit.value, total.value),
);

function currencyMyr(amount: number): string {
  return new Intl.NumberFormat("en-MY", {
    style: "currency",
    currency: "MYR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0);
}

function buildQuery(): string {
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
  });
  if (q.value.trim()) params.set("q", q.value.trim());
  if (tfEndDate.value) params.set("tf_end_date", tfEndDate.value);
  if (tfCustomerType.value) params.set("tf_customer_type", tfCustomerType.value);
  if (tfCustomerId.value) params.set("tf_customer_id", tfCustomerId.value);
  if (tfInvoiceNo.value) params.set("tf_invoice_no", tfInvoiceNo.value);
  return `?${params.toString()}`;
}

async function loadRows() {
  loading.value = true;
  try {
    const res = await listInvoiceBalance(buildQuery());
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    const f = (res.meta?.footer as { pdeTransAmt?: number; balance?: number } | undefined) ?? {};
    footer.value = {
      pdeTransAmt: Number(f.pdeTransAmt ?? 0),
      balance: Number(f.balance ?? 0),
    };
    resolvedEndDate.value = String(res.meta?.endDate ?? "");
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load balances.",
    );
  } finally {
    loading.value = false;
  }
}

async function loadOptions() {
  try {
    const res = await fetchInvoiceBalanceOptions();
    options.value = res.data;
  } catch {
    // Silent
  }
}

function onCustInput() {
  if (custTimer) window.clearTimeout(custTimer);
  custTimer = window.setTimeout(() => {
    custTimer = null;
    void (async () => {
      try {
        const res = await searchInvoiceBalanceCustomer(
          custQuery.value,
          tfCustomerType.value,
          15,
        );
        custOptions.value = res.data;
      } catch {
        custOptions.value = [];
      }
    })();
  }, 300);
}
function pickCustomer(opt: CcCustomerOption) {
  tfCustomerId.value = opt.id;
  custQuery.value = opt.label;
  showCustDropdown.value = false;
}
function clearCustomer() {
  tfCustomerId.value = "";
  custQuery.value = "";
  custOptions.value = [];
}
function closeCustSoon() {
  window.setTimeout(() => {
    showCustDropdown.value = false;
  }, 150);
}

function onInvInput() {
  if (invTimer) window.clearTimeout(invTimer);
  invTimer = window.setTimeout(() => {
    invTimer = null;
    void (async () => {
      try {
        const res = await searchInvoiceBalanceInvoice(
          invQuery.value,
          tfCustomerType.value,
          tfCustomerId.value,
          15,
        );
        invOptions.value = res.data;
      } catch {
        invOptions.value = [];
      }
    })();
  }, 300);
}
function pickInvoice(opt: CcOption) {
  tfInvoiceNo.value = String(opt.id);
  invQuery.value = opt.label;
  showInvDropdown.value = false;
}
function clearInvoice() {
  tfInvoiceNo.value = "";
  invQuery.value = "";
  invOptions.value = [];
}
function closeInvSoon() {
  window.setTimeout(() => {
    showInvDropdown.value = false;
  }, 150);
}

function applyFilters() {
  page.value = 1;
  void loadRows();
}
function resetFilters() {
  tfEndDate.value = defaultEndDate();
  tfCustomerType.value = "";
  clearCustomer();
  clearInvoice();
  applyFilters();
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
  "Cust Type",
  "Cust ID",
  "Cust Name",
  "Fund",
  "PTJ",
  "Activity",
  "Cost Centre",
  "Account Code",
  "Invoice No",
  "Invoice Date",
  "Description",
  "Invoice Amount",
  "Balance",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Credit Control - Invoice Balance",
  apiDataPath: "/credit-control/invoice-balance",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Cust Type": r.pdePaytoTypeDesc ?? r.pdePaytoType ?? "",
      "Cust ID": r.pdePaytoId ?? "",
      "Cust Name": r.pdePaytoName ?? "",
      Fund: r.ftyFundType ?? "",
      PTJ: r.ounCode ?? "",
      Activity: r.atActivityCode ?? "",
      "Cost Centre": r.ccrCostcentre ?? "",
      "Account Code": r.acmAcctCode ?? "",
      "Invoice No": r.pdeDocumentNo ?? "",
      "Invoice Date": r.pdeTransDate ?? "",
      Description: r.docDescription ?? "",
      "Invoice Amount": currencyMyr(r.pdeTransAmt),
      Balance: currencyMyr(r.balance),
    })),
  datatableRef,
  searchKeyword: q,
  smartFilter: ref({}),
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
    const ws = wb.addWorksheet("Invoice Balance");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, i) => {
      ws.addRow([
        i + 1,
        r.pdePaytoTypeDesc ?? r.pdePaytoType ?? "",
        r.pdePaytoId ?? "",
        r.pdePaytoName ?? "",
        r.ftyFundType ?? "",
        r.ounCode ?? "",
        r.atActivityCode ?? "",
        r.ccrCostcentre ?? "",
        r.acmAcctCode ?? "",
        r.pdeDocumentNo ?? "",
        r.pdeTransDate ?? "",
        r.docDescription ?? "",
        r.pdeTransAmt,
        r.balance,
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Invoice_Balance_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

// Resetting cust/invoice when customer type changes mirrors legacy behaviour.
watch(tfCustomerType, () => {
  clearCustomer();
  clearInvoice();
});

onMounted(async () => {
  await Promise.all([loadOptions(), loadRows()]);
});

onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
  if (custTimer) window.clearTimeout(custTimer);
  if (invTimer) window.clearTimeout(invTimer);
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-[1400px] space-y-4">
      <input
        ref="templateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onTemplateFileChange"
      />
      <p class="text-base font-semibold text-slate-500">
        Credit Control / Invoice Balance
      </p>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Filters</h1>
          <span v-if="resolvedEndDate" class="text-xs text-slate-500">
            As of {{ resolvedEndDate }}
          </span>
        </div>
        <div class="grid gap-3 p-4 md:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">End Date</label>
            <input v-model="tfEndDate" type="date" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Customer Type</label>
            <select v-model="tfCustomerType" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">All</option>
              <option v-for="o in options.customerType" :key="o.id" :value="o.id">{{ o.label }}</option>
            </select>
          </div>
          <div class="relative">
            <label class="mb-1 block text-xs font-medium text-slate-600">Customer ID</label>
            <input
              v-model="custQuery"
              type="search"
              placeholder="Type to search..."
              class="w-full rounded-lg border border-slate-300 px-2 py-1.5 pr-8 text-sm"
              @input="onCustInput"
              @focus="showCustDropdown = true"
              @blur="closeCustSoon"
            />
            <button
              v-if="custQuery || tfCustomerId"
              type="button"
              class="absolute right-1 top-[1.75rem] rounded p-0.5 text-slate-400 hover:bg-slate-100"
              aria-label="Clear"
              @click="clearCustomer"
            >
              <X class="h-3.5 w-3.5" />
            </button>
            <div
              v-if="showCustDropdown && custOptions.length > 0"
              class="absolute left-0 right-0 top-full z-20 mt-1 max-h-60 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
            >
              <button
                v-for="opt in custOptions"
                :key="opt.id"
                type="button"
                class="block w-full truncate px-3 py-1.5 text-left text-xs hover:bg-slate-50"
                @mousedown.prevent="pickCustomer(opt)"
              >
                {{ opt.label }}
              </button>
            </div>
          </div>
          <div class="relative">
            <label class="mb-1 block text-xs font-medium text-slate-600">Invoice No</label>
            <input
              v-model="invQuery"
              type="search"
              placeholder="Type to search..."
              class="w-full rounded-lg border border-slate-300 px-2 py-1.5 pr-8 text-sm"
              @input="onInvInput"
              @focus="showInvDropdown = true"
              @blur="closeInvSoon"
            />
            <button
              v-if="invQuery || tfInvoiceNo"
              type="button"
              class="absolute right-1 top-[1.75rem] rounded p-0.5 text-slate-400 hover:bg-slate-100"
              aria-label="Clear"
              @click="clearInvoice"
            >
              <X class="h-3.5 w-3.5" />
            </button>
            <div
              v-if="showInvDropdown && invOptions.length > 0"
              class="absolute left-0 right-0 top-full z-20 mt-1 max-h-60 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"
            >
              <button
                v-for="opt in invOptions"
                :key="opt.id"
                type="button"
                class="block w-full truncate px-3 py-1.5 text-left text-xs hover:bg-slate-50"
                @mousedown.prevent="pickInvoice(opt)"
              >
                {{ opt.label }}
              </button>
            </div>
          </div>
          <div class="md:col-span-4 flex justify-end gap-2">
            <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="resetFilters">
              Reset
            </button>
            <button type="button" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white" @click="applyFilters">
              Apply
            </button>
          </div>
        </div>
      </article>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Outstanding Invoices</h1>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More">
            <MoreVertical class="h-4 w-4" />
          </button>
        </div>
        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select
                v-model.number="limit"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                @change="page = 1; loadRows()"
              >
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
                  class="w-60 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm"
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
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[480px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1200px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Cust Type</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Cust ID</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Cust Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Fund</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">PTJ</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Activity</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Cost Centre</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Account</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Invoice</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Inv Date</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Inv Amt</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Balance</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="13" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="13" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="`${row.pdePaytoId ?? ''}-${row.pdeDocumentNo ?? ''}-${row.acmAcctCode ?? ''}-${row.index}`"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2">{{ row.pdePaytoTypeDesc ?? row.pdePaytoType ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.pdePaytoId ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.pdePaytoName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ftyFundType ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ounCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.atActivityCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ccrCostcentre ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.acmAcctCode ?? "-" }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.pdeDocumentNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.pdeTransDate ?? "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ currencyMyr(row.pdeTransAmt) }}</td>
                    <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ currencyMyr(row.balance) }}</td>
                  </tr>
                </tbody>
                <tfoot v-if="rows.length > 0" class="bg-slate-50">
                  <tr>
                    <td colspan="11" class="px-3 py-2 text-right text-xs font-semibold uppercase">Total</td>
                    <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ currencyMyr(footer.pdeTransAmt) }}</td>
                    <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ currencyMyr(footer.balance) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</div>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="page <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="prevPage">
                Prev
              </button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" :disabled="page >= totalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="nextPage">
                Next
              </button>
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
  </AdminLayout>
</template>
