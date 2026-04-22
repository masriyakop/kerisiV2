<script setup lang="ts">
/**
 * Credit Control / Detail of Deposit (PAGEID 2688, MENUID 3397)
 *
 * Source: FIMS BL `NAD_API_CC_DEPOSIT_DETAILS`. The legacy page:
 *   - Loads a master record by `?id=`
 *   - Renders a read-only header and a few editable master fields
 *     (`dpm_ref_no_note`, pay-to type, vendor, contract no, start/end)
 *   - Shows a datatable of deposit_details for the same master
 *   - Each detail row opens a popup modal to edit description, currency,
 *     entry amount, transaction ref, and (on master) `dpm_ref_no`.
 *
 * There is NO "new" flow — deposits are created by upstream modules.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  ChevronLeft,
  Download,
  FileDown,
  FileSpreadsheet,
  Save,
  Search,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  getDepositForm,
  listDepositFormDetails,
  searchDepositFormCustomer,
  updateDepositFormDetail,
  updateDepositFormMaster,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  CcCustomerOption,
  DepositDetailRow,
  DepositFormMaster,
} from "@/types";

const route = useRoute();
const router = useRouter();
const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);

const masterId = computed(() => {
  const raw = route.query.id ?? route.params.id ?? "";
  const n = Number(raw);
  return Number.isFinite(n) && n > 0 ? n : null;
});

const loadingMaster = ref(false);
const savingMaster = ref(false);
const master = ref<DepositFormMaster | null>(null);

// Editable master fields (scope from legacy BL edit_process)
const editable = ref({
  dpmRefNoNote: "",
  dpmPaytoType: "",
  vcsVendorCode: "",
  dpmVendorName: "",
  dpmContractNo: "",
  dpmStartDate: "",
  dpmEndDate: "",
});

// Customer autosuggest in master editor
const custQuery = ref("");
const custOptions = ref<CcCustomerOption[]>([]);
const showCustDropdown = ref(false);
let custTimer: number | null = null;

// Details datatable
const rows = ref<DepositDetailRow[]>([]);
const detailsLoading = ref(false);
const total = ref(0);
const footer = ref({ debitAmt: 0, creditAmt: 0 });
const page = ref(1);
const limit = ref(10);
const q = ref("");

// Popup modal (updateModal)
const editing = ref<DepositDetailRow | null>(null);
const modalMasterRefNo = ref("");
const savingDetail = ref(false);

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

// dd/mm/YYYY → yyyy-mm-dd for <input type="date">
function toIsoDate(v: string | null | undefined): string {
  if (!v) return "";
  const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(v);
  if (m) return `${m[3]}-${m[2]}-${m[1]}`;
  return v;
}
function fromIsoToLegacy(v: string): string {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
  if (m) return `${m[3]}/${m[2]}/${m[1]}`;
  return v;
}

async function loadMaster() {
  if (!masterId.value) return;
  loadingMaster.value = true;
  try {
    const res = await getDepositForm(masterId.value);
    master.value = res.data;
    editable.value = {
      dpmRefNoNote: res.data.dpmRefNoNote ?? "",
      dpmPaytoType: res.data.dpmPaytoType ?? "",
      vcsVendorCode: res.data.vcsVendorCode ?? "",
      dpmVendorName: res.data.dpmVendorName ?? "",
      dpmContractNo: res.data.dpmContractNo ?? "",
      dpmStartDate: toIsoDate(res.data.dpmStartDate),
      dpmEndDate: toIsoDate(res.data.dpmEndDate),
    };
    custQuery.value = res.data.vcsVendorCode && res.data.dpmVendorName
      ? `${res.data.vcsVendorCode} - ${res.data.dpmVendorName}`
      : res.data.vcsVendorCode ?? "";
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load deposit.",
    );
  } finally {
    loadingMaster.value = false;
  }
}

async function loadDetails() {
  if (!masterId.value) return;
  detailsLoading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
  });
  if (q.value.trim()) params.set("q", q.value.trim());
  try {
    const res = await listDepositFormDetails(masterId.value, `?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    const f = (res.meta?.footer as { debitAmt?: number; creditAmt?: number } | undefined) ?? {};
    footer.value = {
      debitAmt: Number(f.debitAmt ?? 0),
      creditAmt: Number(f.creditAmt ?? 0),
    };
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load details.",
    );
  } finally {
    detailsLoading.value = false;
  }
}

function onCustInput() {
  if (custTimer) window.clearTimeout(custTimer);
  custTimer = window.setTimeout(() => {
    custTimer = null;
    void (async () => {
      try {
        const res = await searchDepositFormCustomer(custQuery.value, 15);
        custOptions.value = res.data;
      } catch {
        custOptions.value = [];
      }
    })();
  }, 300);
}
function pickCustomer(opt: CcCustomerOption) {
  editable.value.vcsVendorCode = opt.id;
  editable.value.dpmVendorName = opt.name;
  custQuery.value = opt.label;
  showCustDropdown.value = false;
}
function closeCustSoon() {
  window.setTimeout(() => {
    showCustDropdown.value = false;
  }, 150);
}

async function saveMaster() {
  if (!masterId.value) return;
  savingMaster.value = true;
  try {
    const res = await updateDepositFormMaster(masterId.value, {
      dpmRefNoNote: editable.value.dpmRefNoNote,
      dpmPaytoType: editable.value.dpmPaytoType || null,
      vcsVendorCode: editable.value.vcsVendorCode || null,
      dpmVendorName: editable.value.dpmVendorName || null,
      dpmContractNo: editable.value.dpmContractNo || null,
      dpmStartDate: editable.value.dpmStartDate
        ? fromIsoToLegacy(editable.value.dpmStartDate)
        : null,
      dpmEndDate: editable.value.dpmEndDate
        ? fromIsoToLegacy(editable.value.dpmEndDate)
        : null,
    });
    if (master.value) {
      master.value = { ...master.value, ...res.data };
    }
    toast.success("Saved", "Deposit master updated.");
  } catch (e) {
    toast.error(
      "Save failed",
      e instanceof Error ? e.message : "Unable to save master.",
    );
  } finally {
    savingMaster.value = false;
  }
}

function openEdit(row: DepositDetailRow) {
  editing.value = { ...row };
  modalMasterRefNo.value = master.value?.dpmRefNo ?? "";
}
function closeEdit() {
  editing.value = null;
}

async function saveDetail() {
  if (!editing.value || !masterId.value) return;
  savingDetail.value = true;
  try {
    await updateDepositFormDetail(masterId.value, editing.value.ddtDepositDetlId, {
      ddtDescription: editing.value.ddtDescription,
      ddtCurrencyCode: editing.value.ddtCurrencyCode,
      ddtEntAmt: editing.value.ddtEntAmt,
      ddtTransactionRef: editing.value.ddtTransactionRef,
      dpmRefNo: modalMasterRefNo.value,
    });
    if (master.value) {
      master.value.dpmRefNo = modalMasterRefNo.value;
    }
    toast.success("Saved", "Deposit detail updated.");
    closeEdit();
    await loadDetails();
  } catch (e) {
    toast.error(
      "Save failed",
      e instanceof Error ? e.message : "Unable to save detail.",
    );
  } finally {
    savingDetail.value = false;
  }
}

function prevPage() {
  if (page.value > 1) {
    page.value -= 1;
    void loadDetails();
  }
}
function nextPage() {
  if (page.value < totalPages.value) {
    page.value += 1;
    void loadDetails();
  }
}

function back() {
  void router.push("/admin/kerisi/m/3066");
}

const exportColumns = [
  "Doc No",
  "Description",
  "Fund",
  "Activity",
  "PTJ",
  "Cost Centre",
  "Account",
  "Trans Ref",
  "Currency",
  "Entry Amt",
  "Debit",
  "Credit",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Credit Control - Detail of Deposit",
  apiDataPath: "/credit-control/deposit-form",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Doc No": r.ddtDocNo ?? "",
      Description: r.ddtDescription ?? "",
      Fund: r.ftyFundType ?? "",
      Activity: r.atActivityCode ?? "",
      PTJ: r.ounCode ?? "",
      "Cost Centre": r.ccrCostcentre ?? "",
      Account: r.acmAcctCode ?? "",
      "Trans Ref": r.ddtTransactionRef ?? "",
      Currency: r.ddtCurrencyCode ?? "",
      "Entry Amt": currencyMyr(r.ddtEntAmt),
      Debit: currencyMyr(r.debitAmt),
      Credit: currencyMyr(r.creditAmt),
    })),
  datatableRef,
  searchKeyword: q,
  smartFilter: ref({}),
  applyFilters: () => void loadDetails(),
});

async function exportExcel() {
  try {
    if (rows.value.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Deposit Details");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, i) => {
      ws.addRow([
        i + 1,
        r.ddtDocNo ?? "",
        r.ddtDescription ?? "",
        r.ftyFundType ?? "",
        r.atActivityCode ?? "",
        r.ounCode ?? "",
        r.ccrCostcentre ?? "",
        r.acmAcctCode ?? "",
        r.ddtTransactionRef ?? "",
        r.ddtCurrencyCode ?? "",
        r.ddtEntAmt,
        r.debitAmt,
        r.creditAmt,
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Deposit_Details_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    void loadDetails();
  }, 350);
});

onMounted(async () => {
  if (!masterId.value) {
    toast.error("Missing ID", "Open this page from the List of Deposit.");
    return;
  }
  await Promise.all([loadMaster(), loadDetails()]);
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

      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-50"
          @click="back"
        >
          <ChevronLeft class="h-3.5 w-3.5" />
          Back
        </button>
        <p class="text-base font-semibold text-slate-500">
          Credit Control / Detail of Deposit
        </p>
      </div>

      <article v-if="!masterId" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        No deposit id specified in the URL.
      </article>

      <article v-else class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Deposit Master</h1>
          <button
            type="button"
            :disabled="savingMaster || loadingMaster"
            class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50"
            @click="saveMaster"
          >
            <Save class="h-3.5 w-3.5" />
            {{ savingMaster ? "Saving..." : "Save" }}
          </button>
        </div>

        <div class="grid gap-4 p-4 md:grid-cols-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Deposit No</label>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-medium text-slate-900">
              {{ master?.dpmDepositNo ?? "-" }}
            </div>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Category</label>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm">
              {{ master?.dpmDepositCategoryDesc ?? master?.dpmDepositCategory ?? "-" }}
            </div>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm">
              {{ master?.dpmStatus ?? "-" }}
            </div>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Pay-to Type</label>
            <input v-model="editable.dpmPaytoType" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm" />
          </div>
          <div class="relative">
            <label class="mb-1 block text-xs font-medium text-slate-600">Customer / Vendor</label>
            <input
              v-model="custQuery"
              type="search"
              placeholder="Type to search..."
              class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
              @input="onCustInput"
              @focus="showCustDropdown = true"
              @blur="closeCustSoon"
            />
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
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Vendor Name</label>
            <input v-model="editable.dpmVendorName" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm" />
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Contract No</label>
            <input v-model="editable.dpmContractNo" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Start Date</label>
            <input v-model="editable.dpmStartDate" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">End Date</label>
            <input v-model="editable.dpmEndDate" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm" />
          </div>

          <div class="md:col-span-3">
            <label class="mb-1 block text-xs font-medium text-slate-600">Reference Note</label>
            <textarea
              v-model="editable.dpmRefNoNote"
              rows="2"
              class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
            />
          </div>
        </div>
      </article>

      <article v-if="masterId" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Deposit Details</h1>
        </div>
        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select
                v-model.number="limit"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                @change="page = 1; loadDetails()"
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
                  @keyup.enter="page = 1; void loadDetails()"
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
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Doc No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Description</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Fund</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Activity</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">PTJ</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Cost Centre</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Account</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Trans Ref</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Ccy</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Debit</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Credit</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="detailsLoading">
                    <td colspan="13" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="13" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="row.ddtDepositDetlId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.ddtDocNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ddtDescription ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ftyFundType ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.atActivityCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ounCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ccrCostcentre ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.acmAcctCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ddtTransactionRef ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.ddtCurrencyCode ?? "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ currencyMyr(row.debitAmt) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ currencyMyr(row.creditAmt) }}</td>
                    <td class="px-3 py-2">
                      <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs"
                        @click="openEdit(row)"
                      >
                        Edit
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="rows.length > 0" class="bg-slate-50">
                  <tr>
                    <td colspan="10" class="px-3 py-2 text-right text-xs font-semibold uppercase">Total</td>
                    <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ currencyMyr(footer.debitAmt) }}</td>
                    <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ currencyMyr(footer.creditAmt) }}</td>
                    <td></td>
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

    <Teleport to="body">
      <div
        v-if="editing"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        role="dialog"
        aria-modal="true"
      >
        <div class="w-full max-w-xl rounded-lg bg-white shadow-xl">
          <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Edit Deposit Detail</h2>
            <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="closeEdit">
              <X class="h-4 w-4" />
            </button>
          </div>
          <div class="grid gap-3 p-5 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="mb-1 block text-xs font-medium text-slate-600">Description</label>
              <textarea
                v-model="editing.ddtDescription"
                rows="2"
                class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
              />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Currency</label>
              <input v-model="editing.ddtCurrencyCode" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Entry Amount</label>
              <input v-model.number="editing.ddtEntAmt" type="number" step="0.0001" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Transaction Ref</label>
              <input v-model="editing.ddtTransactionRef" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Master Ref No</label>
              <input v-model="modalMasterRefNo" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm" />
            </div>
          </div>
          <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-3">
            <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="closeEdit">
              Cancel
            </button>
            <button
              type="button"
              :disabled="savingDetail"
              class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50"
              @click="saveDetail"
            >
              {{ savingDetail ? "Saving..." : "Save" }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
