<script setup lang="ts">
/**
 * Credit Control / List of Deposit (PAGEID 2159, MENUID 3066)
 *
 * Source: FIMS BL `SNA_API_CC_LISTOFDEPOSIT`. Variant of the MENUID 1809
 * listing restricted to deposit_details joined to account_main where
 * `acm_flag_subsidiary='Y' AND acm_flag_deposit='Y'`. Top filters differ
 * from MENUID 1809: deposit category, customer type, customer id, and PTJ
 * (multi-select). Each row links to the Detail of Deposit page (MENUID
 * 3397) via `dpmDepositMasterId`.
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
import { useRouter } from "vue-router";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  fetchListOfDepositOptions,
  listOfDeposit,
  searchListOfDepositCustomer,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  CcCustomerOption,
  DepositRow,
  ListOfDepositOptions,
} from "@/types";

const router = useRouter();
const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<DepositRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

// Top filters
const tfCategory = ref("");
const tfCustomerType = ref("");
const tfCustomerId = ref("");
const tfPtj = ref<string[]>([]);

// Customer autosuggest
const custQuery = ref("");
const custOptions = ref<CcCustomerOption[]>([]);
const showCustDropdown = ref(false);
let custTimer: number | null = null;

const options = ref<ListOfDepositOptions>({
  category: [],
  customerType: [],
  ptj: [],
});

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
  if (tfCategory.value) params.set("category", tfCategory.value);
  if (tfCustomerType.value) params.set("customer_type", tfCustomerType.value);
  if (tfCustomerId.value) params.set("customer_id", tfCustomerId.value);
  if (tfPtj.value.length > 0) params.set("ptj", tfPtj.value.join(","));
  return `?${params.toString()}`;
}

async function loadRows() {
  loading.value = true;
  try {
    const res = await listOfDeposit(buildQuery());
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

async function loadOptions() {
  try {
    const res = await fetchListOfDepositOptions();
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
        const res = await searchListOfDepositCustomer(custQuery.value, 15);
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

function applyFilters() {
  page.value = 1;
  void loadRows();
}

function resetFilters() {
  tfCategory.value = "";
  tfCustomerType.value = "";
  clearCustomer();
  tfPtj.value = [];
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

function openDetail(id: number) {
  void router.push(`/admin/kerisi/m/3397?id=${id}`);
}

const exportColumns = [
  "Deposit No",
  "Category",
  "Vendor Code",
  "Vendor Name",
  "Pay To Type",
  "Ref No",
  "Doc No",
  "Description",
  "Fund",
  "Activity",
  "PTJ",
  "Cost Centre",
  "Account Code",
  "Account Desc",
  "Currency",
  "Amount",
  "Type",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Credit Control - List of Deposit",
  apiDataPath: "/credit-control/list-of-deposit",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Deposit No": r.dpmDepositNo ?? "",
      Category: "",
      "Vendor Code": r.vcsVendorCode ?? "",
      "Vendor Name": r.dpmVendorName ?? "",
      "Pay To Type": r.dpmPaytoType ?? "",
      "Ref No": r.dpmRefNo ?? "",
      "Doc No": r.ddtDocNo ?? "",
      Description: r.dpmRefNoNote ?? "",
      Fund: r.ftyFundType ?? "",
      Activity: r.atActivityCode ?? "",
      PTJ: r.ounCode ?? "",
      "Cost Centre": r.ccrCostcentre ?? "",
      "Account Code": r.acmAcctCode ?? "",
      "Account Desc": r.acmAcctDesc ?? "",
      Currency: r.ddtCurrencyCode ?? "",
      Amount: currencyMyr(r.ddtAmt),
      Type: r.ddtType ?? "",
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
    const ws = wb.addWorksheet("List of Deposit");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, i) => {
      ws.addRow([
        i + 1,
        r.dpmDepositNo ?? "",
        "",
        r.vcsVendorCode ?? "",
        r.dpmVendorName ?? "",
        r.dpmPaytoType ?? "",
        r.dpmRefNo ?? "",
        r.ddtDocNo ?? "",
        r.dpmRefNoNote ?? "",
        r.ftyFundType ?? "",
        r.atActivityCode ?? "",
        r.ounCode ?? "",
        r.ccrCostcentre ?? "",
        r.acmAcctCode ?? "",
        r.acmAcctDesc ?? "",
        r.ddtCurrencyCode ?? "",
        r.ddtAmt,
        r.ddtType ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `List_of_Deposit_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
  await Promise.all([loadOptions(), loadRows()]);
});

onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
  if (custTimer) window.clearTimeout(custTimer);
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
        Credit Control / List of Deposit
      </p>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Filters</h1>
        </div>
        <div class="grid gap-3 p-4 md:grid-cols-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Deposit Category</label>
            <select v-model="tfCategory" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
              <option value="">All</option>
              <option v-for="o in options.category" :key="o.id" :value="o.id">{{ o.label }}</option>
            </select>
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
          <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-medium text-slate-600">PTJ (multi-select)</label>
            <select
              v-model="tfPtj"
              multiple
              class="h-24 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option v-for="o in options.ptj" :key="o.id" :value="o.id">{{ o.label }}</option>
            </select>
          </div>
          <div class="flex items-end gap-2">
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
          <h1 class="text-base font-semibold text-slate-900">List of Deposit</h1>
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
              <table class="w-full min-w-[1400px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Deposit No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Customer</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Ref No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Doc No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Fund</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">PTJ</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Account</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Amount</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Type</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="12" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="12" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="`${row.dpmDepositMasterId}-${row.ddtDocNo ?? ''}-${row.index}`"
                    class="cursor-pointer border-b border-slate-100 hover:bg-slate-50"
                    @click="openDetail(row.dpmDepositMasterId)"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-indigo-600 underline">{{ row.dpmDepositNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsVendorCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.dpmVendorName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.dpmRefNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ddtDocNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ftyFundType ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ounCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.acmAcctCode ?? "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ currencyMyr(row.ddtAmt) }}</td>
                    <td class="px-3 py-2">{{ row.ddtType ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.dpmStatus ?? "-" }}</td>
                  </tr>
                </tbody>
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
