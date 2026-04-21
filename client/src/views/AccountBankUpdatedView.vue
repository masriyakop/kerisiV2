<script setup lang="ts">
/**
 * Account Payable / Account Bank Updated (PAGEID 1719, MENUID 2078)
 *
 * Source: FIMS BL `SNA_API_AP_ACCOUNTBANKUPDATED` + onload JS
 * `SNA_JS_AP_ACCOUNTBANKUPDATED`.
 *
 * Page shows two independent datatables after the user picks a Payee Type +
 * Payee ID from the top filter:
 *   1. Bills whose line-level bank account (`bills_details.vsa_bank_accno`)
 *      has drifted from the payee master's current active bank.
 *   2. Vouchers with the same drift on `voucher_details.vde_bank_acctno`.
 *
 * Each datatable supports multi-row selection and an `Update` button that
 * re-syncs the selected rows to the payee master bank via the BL equivalent
 * of `processkemaskini` / `processkemaskinivoucher`.
 *
 * Payee type codes:
 *   A = Student, B = Staff, C = Creditor, D = Debtor, E = Sponsor,
 *   G = Other payee. See controller for master-table mapping.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import {
  getAccountBankUpdatedOptions,
  listAccountBankUpdatedBills,
  listAccountBankUpdatedVouchers,
  processAccountBankUpdatedBills,
  processAccountBankUpdatedVouchers,
} from "@/api/cms";
import type {
  AccountBankUpdatedBillRow,
  AccountBankUpdatedOptions,
  AccountBankUpdatedPayeeType,
  AccountBankUpdatedVoucherRow,
} from "@/types";

const toast = useToast();
const { confirm } = useConfirmDialog();

const payeeType = ref<AccountBankUpdatedPayeeType | "">("");
const payeeId = ref<string>("");
const options = ref<AccountBankUpdatedOptions>({ payeeType: [], ids: [] });

// Bills datatable state ------------------------------------------------------
const billsRef = ref<DatatableRefApi | null>(null);
const billRows = ref<AccountBankUpdatedBillRow[]>([]);
const billPage = ref(1);
const billLimit = ref(10);
const billQ = ref("");
const billTotal = ref(0);
const billLoading = ref(false);
const billSelection = ref<Set<string>>(new Set());
const billProcessing = ref(false);

// Vouchers datatable state ---------------------------------------------------
const voucherRef = ref<DatatableRefApi | null>(null);
const voucherRows = ref<AccountBankUpdatedVoucherRow[]>([]);
const voucherPage = ref(1);
const voucherLimit = ref(10);
const voucherQ = ref("");
const voucherTotal = ref(0);
const voucherLoading = ref(false);
const voucherSelection = ref<Set<string>>(new Set());
const voucherProcessing = ref(false);

const hasFilter = computed(() => Boolean(payeeType.value && payeeId.value));

const billTotalPages = computed(() =>
  billTotal.value ? Math.max(1, Math.ceil(billTotal.value / billLimit.value)) : 1,
);
const billStartIdx = computed(() => (billTotal.value === 0 ? 0 : (billPage.value - 1) * billLimit.value + 1));
const billEndIdx = computed(() => Math.min(billPage.value * billLimit.value, billTotal.value));

const voucherTotalPages = computed(() =>
  voucherTotal.value ? Math.max(1, Math.ceil(voucherTotal.value / voucherLimit.value)) : 1,
);
const voucherStartIdx = computed(() =>
  voucherTotal.value === 0 ? 0 : (voucherPage.value - 1) * voucherLimit.value + 1,
);
const voucherEndIdx = computed(() => Math.min(voucherPage.value * voucherLimit.value, voucherTotal.value));

const billAllSelected = computed(
  () => billRows.value.length > 0 && billRows.value.every((r) => billSelection.value.has(r.billId)),
);
const voucherAllSelected = computed(
  () =>
    voucherRows.value.length > 0 &&
    voucherRows.value.every((r) => voucherSelection.value.has(r.voucherId)),
);

async function loadOptions() {
  try {
    const res = await getAccountBankUpdatedOptions(
      (payeeType.value || undefined) as AccountBankUpdatedPayeeType | undefined,
    );
    options.value = res.data;
  } catch (e) {
    toast.error("Failed to load options", e instanceof Error ? e.message : "Unable to fetch options.");
  }
}

function buildParams(extra: Record<string, string>) {
  if (!payeeType.value || !payeeId.value) return "";
  const query: Record<string, string> = {
    payee_type: payeeType.value,
    payee_id: payeeId.value,
    ...extra,
  };
  return `?${new URLSearchParams(query).toString()}`;
}

async function loadBills() {
  if (!hasFilter.value) {
    billRows.value = [];
    billTotal.value = 0;
    billSelection.value = new Set();
    return;
  }
  billLoading.value = true;
  const params: Record<string, string> = { page: String(billPage.value), limit: String(billLimit.value) };
  if (billQ.value) params.q = billQ.value;
  try {
    const res = await listAccountBankUpdatedBills(buildParams(params));
    billRows.value = res.data;
    billTotal.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load bills.");
  } finally {
    billLoading.value = false;
  }
}

async function loadVouchers() {
  if (!hasFilter.value) {
    voucherRows.value = [];
    voucherTotal.value = 0;
    voucherSelection.value = new Set();
    return;
  }
  voucherLoading.value = true;
  const params: Record<string, string> = {
    page: String(voucherPage.value),
    limit: String(voucherLimit.value),
  };
  if (voucherQ.value) params.q = voucherQ.value;
  try {
    const res = await listAccountBankUpdatedVouchers(buildParams(params));
    voucherRows.value = res.data;
    voucherTotal.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load vouchers.");
  } finally {
    voucherLoading.value = false;
  }
}

async function runSearch() {
  if (!hasFilter.value) {
    toast.error("Missing filter", "Please select Payee Type and ID No.");
    return;
  }
  billPage.value = 1;
  voucherPage.value = 1;
  billSelection.value = new Set();
  voucherSelection.value = new Set();
  await Promise.all([loadBills(), loadVouchers()]);
}

async function onPayeeTypeChange() {
  payeeId.value = "";
  billRows.value = [];
  voucherRows.value = [];
  billTotal.value = 0;
  voucherTotal.value = 0;
  billSelection.value = new Set();
  voucherSelection.value = new Set();
  await loadOptions();
}

function toggleBill(id: string) {
  const next = new Set(billSelection.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  billSelection.value = next;
}
function toggleAllBills() {
  if (billAllSelected.value) {
    billSelection.value = new Set();
  } else {
    billSelection.value = new Set(billRows.value.map((r) => r.billId));
  }
}
function toggleVoucher(id: string) {
  const next = new Set(voucherSelection.value);
  if (next.has(id)) next.delete(id);
  else next.add(id);
  voucherSelection.value = next;
}
function toggleAllVouchers() {
  if (voucherAllSelected.value) {
    voucherSelection.value = new Set();
  } else {
    voucherSelection.value = new Set(voucherRows.value.map((r) => r.voucherId));
  }
}

async function updateBills() {
  if (!payeeType.value) return;
  if (billSelection.value.size === 0) {
    toast.error("No selection", "Please select at least one bill row.");
    return;
  }
  const ok = await confirm({
    title: "Update bill bank accounts",
    message: `Apply the payee's current bank to ${billSelection.value.size} bill(s)?`,
    confirmText: "Update",
  });
  if (!ok) return;
  billProcessing.value = true;
  try {
    const res = await processAccountBankUpdatedBills({
      payeeType: payeeType.value,
      ids: Array.from(billSelection.value),
    });
    toast.success("Updated", res.data.message);
    billSelection.value = new Set();
    await loadBills();
  } catch (e) {
    toast.error("Update failed", e instanceof Error ? e.message : "Unable to update bills.");
  } finally {
    billProcessing.value = false;
  }
}

async function updateVouchers() {
  if (!payeeType.value) return;
  if (voucherSelection.value.size === 0) {
    toast.error("No selection", "Please select at least one voucher row.");
    return;
  }
  const ok = await confirm({
    title: "Update voucher bank accounts",
    message: `Apply the payee's current bank to ${voucherSelection.value.size} voucher(s)?`,
    confirmText: "Update",
  });
  if (!ok) return;
  voucherProcessing.value = true;
  try {
    const res = await processAccountBankUpdatedVouchers({
      payeeType: payeeType.value,
      ids: Array.from(voucherSelection.value),
    });
    toast.success("Updated", res.data.message);
    voucherSelection.value = new Set();
    await loadVouchers();
  } catch (e) {
    toast.error("Update failed", e instanceof Error ? e.message : "Unable to update vouchers.");
  } finally {
    voucherProcessing.value = false;
  }
}

function prevBillPage() {
  if (billPage.value > 1) {
    billPage.value -= 1;
    void loadBills();
  }
}
function nextBillPage() {
  if (billPage.value < billTotalPages.value) {
    billPage.value += 1;
    void loadBills();
  }
}
function prevVoucherPage() {
  if (voucherPage.value > 1) {
    voucherPage.value -= 1;
    void loadVouchers();
  }
}
function nextVoucherPage() {
  if (voucherPage.value < voucherTotalPages.value) {
    voucherPage.value += 1;
    void loadVouchers();
  }
}

const billExportColumns = ["Bill No", "Bill Desc", "Payee Id", "Payee Name", "Current Bank", "Current Acc No", "New Bank", "New Acc No"];
const voucherExportColumns = ["Voucher No", "Voucher Desc", "Payee Id", "Payee Name", "Current Bank", "Current Acc No", "New Bank", "New Acc No"];

const {
  templateFileInputRef: billsTplRef,
  onTemplateFileChange: onBillsTplChange,
  handleDownloadPDF: handleBillsPDF,
  handleDownloadCSV: handleBillsCSV,
} = useDatatableFeatures({
  pageName: "Account Bank Updated - Bills",
  apiDataPath: "/account-payable/account-bank-updated/bills",
  defaultExportColumns: billExportColumns,
  getFilteredList: () =>
    billRows.value.map((r) => ({
      "Bill No": r.billNo ?? "",
      "Bill Desc": r.billDesc ?? "",
      "Payee Id": r.payeeId ?? "",
      "Payee Name": r.payeeName ?? "",
      "Current Bank": r.currentBank ?? "",
      "Current Acc No": r.currentAccNo ?? "",
      "New Bank": r.newBank ?? "",
      "New Acc No": r.newAccNo ?? "",
    })),
  datatableRef: billsRef,
  searchKeyword: billQ,
  applyFilters: () => void loadBills(),
});

const {
  templateFileInputRef: vouchersTplRef,
  onTemplateFileChange: onVouchersTplChange,
  handleDownloadPDF: handleVouchersPDF,
  handleDownloadCSV: handleVouchersCSV,
} = useDatatableFeatures({
  pageName: "Account Bank Updated - Vouchers",
  apiDataPath: "/account-payable/account-bank-updated/vouchers",
  defaultExportColumns: voucherExportColumns,
  getFilteredList: () =>
    voucherRows.value.map((r) => ({
      "Voucher No": r.voucherNo ?? "",
      "Voucher Desc": r.voucherDesc ?? "",
      "Payee Id": r.payeeId ?? "",
      "Payee Name": r.payeeName ?? "",
      "Current Bank": r.currentBank ?? "",
      "Current Acc No": r.currentAccNo ?? "",
      "New Bank": r.newBank ?? "",
      "New Acc No": r.newAccNo ?? "",
    })),
  datatableRef: voucherRef,
  searchKeyword: voucherQ,
  applyFilters: () => void loadVouchers(),
});

async function exportBillsExcel() {
  if (billRows.value.length === 0) {
    toast.info("No data", "There is nothing to export.");
    return;
  }
  try {
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Bills");
    ws.addRow(["No", ...billExportColumns]);
    billRows.value.forEach((r, idx) =>
      ws.addRow([
        idx + 1,
        r.billNo ?? "",
        r.billDesc ?? "",
        r.payeeId ?? "",
        r.payeeName ?? "",
        r.currentBank ?? "",
        r.currentAccNo ?? "",
        r.newBank ?? "",
        r.newAccNo ?? "",
      ]),
    );
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Account_Bank_Updated_Bills_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
  }
}

async function exportVouchersExcel() {
  if (voucherRows.value.length === 0) {
    toast.info("No data", "There is nothing to export.");
    return;
  }
  try {
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Vouchers");
    ws.addRow(["No", ...voucherExportColumns]);
    voucherRows.value.forEach((r, idx) =>
      ws.addRow([
        idx + 1,
        r.voucherNo ?? "",
        r.voucherDesc ?? "",
        r.payeeId ?? "",
        r.payeeName ?? "",
        r.currentBank ?? "",
        r.currentAccNo ?? "",
        r.newBank ?? "",
        r.newAccNo ?? "",
      ]),
    );
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Account_Bank_Updated_Vouchers_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
  }
}

let billDebounce: ReturnType<typeof setTimeout> | null = null;
let voucherDebounce: ReturnType<typeof setTimeout> | null = null;

watch(billQ, () => {
  if (!hasFilter.value) return;
  if (billDebounce) clearTimeout(billDebounce);
  billDebounce = setTimeout(() => {
    billDebounce = null;
    billPage.value = 1;
    void loadBills();
  }, 350);
});

watch(voucherQ, () => {
  if (!hasFilter.value) return;
  if (voucherDebounce) clearTimeout(voucherDebounce);
  voucherDebounce = setTimeout(() => {
    voucherDebounce = null;
    voucherPage.value = 1;
    void loadVouchers();
  }, 350);
});

onMounted(async () => {
  await loadOptions();
});

onUnmounted(() => {
  if (billDebounce) clearTimeout(billDebounce);
  if (voucherDebounce) clearTimeout(voucherDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <input
        ref="billsTplRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onBillsTplChange"
      />
      <input
        ref="vouchersTplRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onVouchersTplChange"
      />
      <p class="text-base font-semibold text-slate-500">Account Payable / Account Bank Updated</p>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Filter By</h1>
        </div>
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-3">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Payee Type <span class="text-rose-600">*</span>
            </label>
            <select
              v-model="payeeType"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              @change="onPayeeTypeChange"
            >
              <option value="">-- Please select --</option>
              <option v-for="opt in options.payeeType" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              ID No <span class="text-rose-600">*</span>
            </label>
            <select
              v-model="payeeId"
              :disabled="!payeeType"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
            >
              <option value="">-- Please select --</option>
              <option v-for="opt in options.ids" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex items-end">
            <button
              type="button"
              :disabled="!hasFilter"
              class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
              @click="runSearch"
            >
              <Search class="h-4 w-4" />Search Bank Details
            </button>
          </div>
        </div>
      </article>

      <!-- Bills datatable -->
      <article v-if="hasFilter" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Bill Account Bank Changes</h1>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More">
            <MoreVertical class="h-4 w-4" />
          </button>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select
                v-model.number="billLimit"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                @change="billPage = 1; loadBills()"
              >
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="billQ"
                  type="search"
                  placeholder="Filter rows..."
                  class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm"
                  @keyup.enter="billPage = 1; void loadBills()"
                />
                <button
                  v-if="billQ"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  aria-label="Clear search"
                  @click="billQ = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
              <button
                type="button"
                :disabled="billProcessing || billSelection.size === 0"
                class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                @click="updateBills"
              >
                Update (Bills)
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="billRows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1200px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="w-10 px-3 py-2 text-xs font-semibold uppercase">
                      <input
                        type="checkbox"
                        :checked="billAllSelected"
                        :disabled="billRows.length === 0"
                        @change="toggleAllBills"
                      />
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bill No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bill Desc</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Payee Id</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Payee Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Current Bank</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Current Acc No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">New Bank</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">New Acc No</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="billLoading">
                    <td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="billRows.length === 0">
                    <td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr
                    v-for="row in billRows"
                    :key="row.billId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">
                      <input
                        type="checkbox"
                        :checked="billSelection.has(row.billId)"
                        @change="toggleBill(row.billId)"
                      />
                    </td>
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.billNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.billDesc ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.payeeId ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.payeeName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.currentBank ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.currentAccNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.newBank ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.newAccNo ?? "-" }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">
              Showing {{ billStartIdx }}-{{ billEndIdx }} of {{ billTotal }} &middot; Selected {{ billSelection.size }}
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                :disabled="billPage <= 1"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="prevBillPage"
              >
                Prev
              </button>
              <span class="text-xs text-slate-600">Page {{ billPage }} / {{ billTotalPages }}</span>
              <button
                type="button"
                :disabled="billPage >= billTotalPages"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="nextBillPage"
              >
                Next
              </button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleBillsPDF"
              >
                <Download class="h-3.5 w-3.5" />PDF
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleBillsCSV"
              >
                <FileDown class="h-3.5 w-3.5" />CSV
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="exportBillsExcel"
              >
                <FileSpreadsheet class="h-3.5 w-3.5" />Excel
              </button>
            </div>
          </div>
        </div>
      </article>

      <!-- Vouchers datatable -->
      <article v-if="hasFilter" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Voucher Account Bank Changes</h1>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More">
            <MoreVertical class="h-4 w-4" />
          </button>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select
                v-model.number="voucherLimit"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                @change="voucherPage = 1; loadVouchers()"
              >
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="voucherQ"
                  type="search"
                  placeholder="Filter rows..."
                  class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm"
                  @keyup.enter="voucherPage = 1; void loadVouchers()"
                />
                <button
                  v-if="voucherQ"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  aria-label="Clear search"
                  @click="voucherQ = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
              <button
                type="button"
                :disabled="voucherProcessing || voucherSelection.size === 0"
                class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                @click="updateVouchers"
              >
                Update (Vouchers)
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="voucherRows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1200px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="w-10 px-3 py-2 text-xs font-semibold uppercase">
                      <input
                        type="checkbox"
                        :checked="voucherAllSelected"
                        :disabled="voucherRows.length === 0"
                        @change="toggleAllVouchers"
                      />
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Voucher No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Voucher Desc</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Payee Id</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Payee Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Current Bank</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Current Acc No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">New Bank</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">New Acc No</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="voucherLoading">
                    <td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="voucherRows.length === 0">
                    <td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr
                    v-for="row in voucherRows"
                    :key="row.voucherId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">
                      <input
                        type="checkbox"
                        :checked="voucherSelection.has(row.voucherId)"
                        @change="toggleVoucher(row.voucherId)"
                      />
                    </td>
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.voucherNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.voucherDesc ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.payeeId ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.payeeName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.currentBank ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.currentAccNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.newBank ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.newAccNo ?? "-" }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">
              Showing {{ voucherStartIdx }}-{{ voucherEndIdx }} of {{ voucherTotal }} &middot; Selected {{ voucherSelection.size }}
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                :disabled="voucherPage <= 1"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="prevVoucherPage"
              >
                Prev
              </button>
              <span class="text-xs text-slate-600">Page {{ voucherPage }} / {{ voucherTotalPages }}</span>
              <button
                type="button"
                :disabled="voucherPage >= voucherTotalPages"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="nextVoucherPage"
              >
                Next
              </button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleVouchersPDF"
              >
                <Download class="h-3.5 w-3.5" />PDF
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleVouchersCSV"
              >
                <FileDown class="h-3.5 w-3.5" />CSV
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="exportVouchersExcel"
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
