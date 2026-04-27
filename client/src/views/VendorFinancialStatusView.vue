<script setup lang="ts">
/**
 * Vendor Portal / Financial Status
 * (PAGEID 1714 / MENUID 2072)
 *
 * Source: FIMS BL `NF_BL_PURCHASING_FINANCIAL_STATUS`. Three vendor-scoped
 * read-only datatables presented as tabs (legacy pattern):
 *
 *   - Billing Information
 *   - Voucher Information
 *   - Payment Information
 *
 * Each tab is a default kitchen-sink "Table" with debounced global
 * search, server pagination, sortable columns, and PDF / CSV / Excel
 * exports.
 *
 * Scoping: bim_payto_id / vde_payto_id / pre_payto_id = auth user's name
 * (FIMS convention — username == vendor code).
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { listVendorBillings, listVendorPayments, listVendorVouchers } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { VendorBillingRow, VendorPaymentRow, VendorVoucherRow } from "@/types";

type TabId = "billings" | "vouchers" | "payments";
const activeTab = ref<TabId>("billings");
const toast = useToast();
const currency = new Intl.NumberFormat("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ---- Billings ----
const billingsRows = ref<VendorBillingRow[]>([]);
const billingsTotal = ref(0);
const billingsPage = ref(1);
const billingsLimit = ref(5);
const billingsQ = ref("");
const billingsSortBy = ref<"bill_voucher_no" | "bill_ref_no" | "bill_desc" | "bill_received_date" | "bill_amount" | "bill_status">("bill_received_date");
const billingsSortDir = ref<"asc" | "desc">("desc");
const billingsLoading = ref(false);
const billingsFooter = ref(0);

// ---- Vouchers ----
const vouchersRows = ref<VendorVoucherRow[]>([]);
const vouchersTotal = ref(0);
const vouchersPage = ref(1);
const vouchersLimit = ref(5);
const vouchersQ = ref("");
const vouchersSortBy = ref<"vou_voucher_no" | "vou_desc" | "vou_date" | "vou_status" | "vou_amount" | "vou_ref_no">("vou_date");
const vouchersSortDir = ref<"asc" | "desc">("desc");
const vouchersLoading = ref(false);
const vouchersFooter = ref(0);

// ---- Payments ----
const paymentsRows = ref<VendorPaymentRow[]>([]);
const paymentsTotal = ref(0);
const paymentsPage = ref(1);
const paymentsLimit = ref(5);
const paymentsQ = ref("");
const paymentsSortBy = ref<"pay_voucher_no" | "pay_desc" | "pay_ep_cheque" | "pay_mode_type" | "pay_amount" | "pay_trans_date" | "pay_collection_mode" | "pay_status_eft" | "pay_ref_no">("pay_ep_cheque");
const paymentsSortDir = ref<"asc" | "desc">("desc");
const paymentsLoading = ref(false);
const paymentsFooter = ref(0);

const billingsTotalPages = computed(() => (billingsTotal.value ? Math.max(1, Math.ceil(billingsTotal.value / billingsLimit.value)) : 1));
const vouchersTotalPages = computed(() => (vouchersTotal.value ? Math.max(1, Math.ceil(vouchersTotal.value / vouchersLimit.value)) : 1));
const paymentsTotalPages = computed(() => (paymentsTotal.value ? Math.max(1, Math.ceil(paymentsTotal.value / paymentsLimit.value)) : 1));

async function loadBillings() {
  billingsLoading.value = true;
  const params = new URLSearchParams({
    page: String(billingsPage.value), limit: String(billingsLimit.value),
    sort_by: billingsSortBy.value, sort_dir: billingsSortDir.value,
    ...(billingsQ.value ? { q: billingsQ.value } : {}),
  });
  try {
    const res = await listVendorBillings(`?${params.toString()}`);
    billingsRows.value = res.data;
    billingsTotal.value = Number(res.meta?.total ?? 0);
    const f = res.meta?.footer as { billAmount?: number } | undefined;
    billingsFooter.value = Number(f?.billAmount ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load billing rows.");
  } finally {
    billingsLoading.value = false;
  }
}

async function loadVouchers() {
  vouchersLoading.value = true;
  const params = new URLSearchParams({
    page: String(vouchersPage.value), limit: String(vouchersLimit.value),
    sort_by: vouchersSortBy.value, sort_dir: vouchersSortDir.value,
    ...(vouchersQ.value ? { q: vouchersQ.value } : {}),
  });
  try {
    const res = await listVendorVouchers(`?${params.toString()}`);
    vouchersRows.value = res.data;
    vouchersTotal.value = Number(res.meta?.total ?? 0);
    const f = res.meta?.footer as { vouAmount?: number } | undefined;
    vouchersFooter.value = Number(f?.vouAmount ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load voucher rows.");
  } finally {
    vouchersLoading.value = false;
  }
}

async function loadPayments() {
  paymentsLoading.value = true;
  const params = new URLSearchParams({
    page: String(paymentsPage.value), limit: String(paymentsLimit.value),
    sort_by: paymentsSortBy.value, sort_dir: paymentsSortDir.value,
    ...(paymentsQ.value ? { q: paymentsQ.value } : {}),
  });
  try {
    const res = await listVendorPayments(`?${params.toString()}`);
    paymentsRows.value = res.data;
    paymentsTotal.value = Number(res.meta?.total ?? 0);
    const f = res.meta?.footer as { payAmount?: number } | undefined;
    paymentsFooter.value = Number(f?.payAmount ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load payment rows.");
  } finally {
    paymentsLoading.value = false;
  }
}

function toggleSortBillings(col: typeof billingsSortBy.value) {
  if (billingsSortBy.value === col) billingsSortDir.value = billingsSortDir.value === "asc" ? "desc" : "asc";
  else { billingsSortBy.value = col; billingsSortDir.value = "asc"; }
  void loadBillings();
}
function toggleSortVouchers(col: typeof vouchersSortBy.value) {
  if (vouchersSortBy.value === col) vouchersSortDir.value = vouchersSortDir.value === "asc" ? "desc" : "asc";
  else { vouchersSortBy.value = col; vouchersSortDir.value = "asc"; }
  void loadVouchers();
}
function toggleSortPayments(col: typeof paymentsSortBy.value) {
  if (paymentsSortBy.value === col) paymentsSortDir.value = paymentsSortDir.value === "asc" ? "desc" : "asc";
  else { paymentsSortBy.value = col; paymentsSortDir.value = "asc"; }
  void loadPayments();
}

function billingsPrev() { if (billingsPage.value > 1) { billingsPage.value -= 1; void loadBillings(); } }
function billingsNext() { if (billingsPage.value < billingsTotalPages.value) { billingsPage.value += 1; void loadBillings(); } }
function vouchersPrev() { if (vouchersPage.value > 1) { vouchersPage.value -= 1; void loadVouchers(); } }
function vouchersNext() { if (vouchersPage.value < vouchersTotalPages.value) { vouchersPage.value += 1; void loadVouchers(); } }
function paymentsPrev() { if (paymentsPage.value > 1) { paymentsPage.value -= 1; void loadPayments(); } }
function paymentsNext() { if (paymentsPage.value < paymentsTotalPages.value) { paymentsPage.value += 1; void loadPayments(); } }

let billingsDebounce: ReturnType<typeof setTimeout> | null = null;
let vouchersDebounce: ReturnType<typeof setTimeout> | null = null;
let paymentsDebounce: ReturnType<typeof setTimeout> | null = null;

watch(billingsQ, () => {
  if (billingsDebounce) clearTimeout(billingsDebounce);
  billingsDebounce = setTimeout(() => { billingsDebounce = null; billingsPage.value = 1; void loadBillings(); }, 350);
});
watch(vouchersQ, () => {
  if (vouchersDebounce) clearTimeout(vouchersDebounce);
  vouchersDebounce = setTimeout(() => { vouchersDebounce = null; vouchersPage.value = 1; void loadVouchers(); }, 350);
});
watch(paymentsQ, () => {
  if (paymentsDebounce) clearTimeout(paymentsDebounce);
  paymentsDebounce = setTimeout(() => { paymentsDebounce = null; paymentsPage.value = 1; void loadPayments(); }, 350);
});

async function exportTab(tab: TabId, format: "pdf" | "csv" | "excel") {
  let header: string[] = [];
  let body: (string | number)[][] = [];
  let filename = "";
  if (tab === "billings") {
    header = ["Reference No.", "Description", "Received Date", "Amount (RM)", "Status"];
    body = billingsRows.value.map((r) => [r.refNo ?? "", r.description ?? "", r.receivedDate ?? "", currency.format(r.amount), r.status ?? ""]);
    filename = `Vendor_Billings_${new Date().toISOString().slice(0, 10)}`;
  } else if (tab === "vouchers") {
    header = ["Voucher No.", "Voucher Date", "Description", "Status", "Amount (RM)", "Reference No."];
    body = vouchersRows.value.map((r) => [r.voucherNo ?? "", r.date ?? "", r.description ?? "", r.status ?? "", currency.format(r.amount), r.refNo ?? ""]);
    filename = `Vendor_Vouchers_${new Date().toISOString().slice(0, 10)}`;
  } else {
    header = ["Voucher No.", "Description", "EP/Cheque No.", "Mode Type", "Amount (RM)", "Sign/Transfer Date", "Collection Mode", "Status EFT", "Reference No."];
    body = paymentsRows.value.map((r) => [r.voucherNo ?? "", r.description ?? "", r.epChequeNo ?? "", r.modeType ?? "", r.amount !== null ? currency.format(r.amount) : "", r.transDate ?? "", r.collectionMode ?? "", r.statusEft ?? "", r.refNo ?? ""]);
    filename = `Vendor_Payments_${new Date().toISOString().slice(0, 10)}`;
  }

  if (body.length === 0) { toast.info("No data", "There is nothing to export."); return; }

  if (format === "csv") {
    const escape = (v: string | number) => { const s = String(v); return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; };
    const csv = [["No", ...header], ...body.map((r, i) => [i + 1, ...r])].map((r) => r.map(escape).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a"); a.href = url; a.download = `${filename}.csv`; a.click(); URL.revokeObjectURL(url);
    toast.success("CSV downloaded");
  } else if (format === "excel") {
    try {
      const ExcelJS = await import("exceljs");
      const wb = new ExcelJS.Workbook();
      const ws = wb.addWorksheet("Sheet1");
      ws.addRow(["No", ...header]);
      body.forEach((r, i) => ws.addRow([i + 1, ...r]));
      const buf = await wb.xlsx.writeBuffer();
      const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a"); a.href = url; a.download = `${filename}.xlsx`; a.click(); URL.revokeObjectURL(url);
      toast.success("Excel downloaded");
    } catch (e) {
      toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
    }
  } else {
    try {
      const { default: jsPDF } = await import("jspdf");
      const autoTable = (await import("jspdf-autotable")).default;
      const doc = new jsPDF({ orientation: "landscape" });
      doc.text(filename, 14, 14);
      autoTable(doc, { head: [["No", ...header]], body: body.map((r, i) => [i + 1, ...r]), startY: 20, styles: { fontSize: 8 } });
      doc.save(`${filename}.pdf`);
      toast.success("PDF downloaded");
    } catch (e) {
      toast.error("Export failed", e instanceof Error ? e.message : "PDF export failed.");
    }
  }
}

onMounted(() => {
  void loadBillings();
  void loadVouchers();
  void loadPayments();
});
onUnmounted(() => {
  if (billingsDebounce) clearTimeout(billingsDebounce);
  if (vouchersDebounce) clearTimeout(vouchersDebounce);
  if (paymentsDebounce) clearTimeout(paymentsDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">Vendor Portal / Financial Status</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <div class="flex items-center gap-2">
            <button type="button" :class="['rounded-lg px-3 py-1.5 text-sm font-medium', activeTab === 'billings' ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50']" @click="activeTab = 'billings'">Billing Information</button>
            <button type="button" :class="['rounded-lg px-3 py-1.5 text-sm font-medium', activeTab === 'vouchers' ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50']" @click="activeTab = 'vouchers'">Voucher Information</button>
            <button type="button" :class="['rounded-lg px-3 py-1.5 text-sm font-medium', activeTab === 'payments' ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50']" @click="activeTab = 'payments'">Payment Information</button>
          </div>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More"><MoreVertical class="h-4 w-4" /></button>
        </div>

        <!-- Billings -->
        <div v-show="activeTab === 'billings'" class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="billingsLimit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="billingsPage = 1; loadBillings()">
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="relative">
              <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <input v-model="billingsQ" type="search" placeholder="Filter rows..." class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm" @keyup.enter="billingsPage = 1; void loadBillings()" />
              <button v-if="billingsQ" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="billingsQ = ''"><X class="h-3.5 w-3.5" /></button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="billingsRows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortBillings('bill_ref_no')">Reference No.</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortBillings('bill_desc')">Description</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortBillings('bill_received_date')">Received Date</th>
                    <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase" @click="toggleSortBillings('bill_amount')">Amount (RM)</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortBillings('bill_status')">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="billingsLoading"><td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="billingsRows.length === 0"><td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">No billings found.</td></tr>
                  <tr v-for="row in billingsRows" :key="`b-${row.index}`" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.refNo ?? "-" }}</td>
                    <td class="px-3 py-2 text-slate-700">{{ row.description ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.receivedDate ?? "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ currency.format(row.amount) }}</td>
                    <td class="px-3 py-2">{{ row.status ?? "-" }}</td>
                  </tr>
                </tbody>
                <tfoot v-if="billingsRows.length > 0" class="bg-slate-50">
                  <tr class="border-t-2 border-slate-300">
                    <td colspan="4" class="px-3 py-2 text-right text-xs font-semibold uppercase">Total (filtered)</td>
                    <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums">{{ currency.format(billingsFooter) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">{{ billingsTotal === 0 ? "No rows" : `Page ${billingsPage} / ${billingsTotalPages} (${billingsTotal})` }}</div>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="billingsPage <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="billingsPrev">Prev</button>
              <button type="button" :disabled="billingsPage >= billingsTotalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="billingsNext">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('billings', 'pdf')"><Download class="h-3.5 w-3.5" />PDF</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('billings', 'csv')"><FileDown class="h-3.5 w-3.5" />CSV</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('billings', 'excel')"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
            </div>
          </div>
        </div>

        <!-- Vouchers -->
        <div v-show="activeTab === 'vouchers'" class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="vouchersLimit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="vouchersPage = 1; loadVouchers()">
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="relative">
              <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <input v-model="vouchersQ" type="search" placeholder="Filter rows..." class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm" @keyup.enter="vouchersPage = 1; void loadVouchers()" />
              <button v-if="vouchersQ" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="vouchersQ = ''"><X class="h-3.5 w-3.5" /></button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="vouchersRows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortVouchers('vou_voucher_no')">Voucher No.</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortVouchers('vou_date')">Voucher Date</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortVouchers('vou_desc')">Description</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortVouchers('vou_status')">Status</th>
                    <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase" @click="toggleSortVouchers('vou_amount')">Amount (RM)</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortVouchers('vou_ref_no')">Reference No.</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="vouchersLoading"><td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="vouchersRows.length === 0"><td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">No vouchers found.</td></tr>
                  <tr v-for="row in vouchersRows" :key="`v-${row.index}`" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.voucherNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.date ?? "-" }}</td>
                    <td class="px-3 py-2 text-slate-700">{{ row.description ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.status ?? "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ currency.format(row.amount) }}</td>
                    <td class="px-3 py-2">{{ row.refNo ?? "-" }}</td>
                  </tr>
                </tbody>
                <tfoot v-if="vouchersRows.length > 0" class="bg-slate-50">
                  <tr class="border-t-2 border-slate-300">
                    <td colspan="5" class="px-3 py-2 text-right text-xs font-semibold uppercase">Total (filtered)</td>
                    <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums">{{ currency.format(vouchersFooter) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">{{ vouchersTotal === 0 ? "No rows" : `Page ${vouchersPage} / ${vouchersTotalPages} (${vouchersTotal})` }}</div>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="vouchersPage <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="vouchersPrev">Prev</button>
              <button type="button" :disabled="vouchersPage >= vouchersTotalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="vouchersNext">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('vouchers', 'pdf')"><Download class="h-3.5 w-3.5" />PDF</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('vouchers', 'csv')"><FileDown class="h-3.5 w-3.5" />CSV</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('vouchers', 'excel')"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
            </div>
          </div>
        </div>

        <!-- Payments -->
        <div v-show="activeTab === 'payments'" class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="paymentsLimit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="paymentsPage = 1; loadPayments()">
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="relative">
              <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <input v-model="paymentsQ" type="search" placeholder="Filter rows..." class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm" @keyup.enter="paymentsPage = 1; void loadPayments()" />
              <button v-if="paymentsQ" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="paymentsQ = ''"><X class="h-3.5 w-3.5" /></button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="paymentsRows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1200px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortPayments('pay_voucher_no')">Voucher No.</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortPayments('pay_desc')">Description</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortPayments('pay_ep_cheque')">EP/Cheque No.</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortPayments('pay_mode_type')">Mode Type</th>
                    <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase" @click="toggleSortPayments('pay_amount')">Amount (RM)</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortPayments('pay_trans_date')">Sign/Transfer Date</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortPayments('pay_collection_mode')">Collection Mode</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortPayments('pay_status_eft')">Status EFT</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortPayments('pay_ref_no')">Reference No.</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="paymentsLoading"><td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="paymentsRows.length === 0"><td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">No payments found.</td></tr>
                  <tr v-for="row in paymentsRows" :key="`p-${row.index}`" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.voucherNo ?? "-" }}</td>
                    <td class="px-3 py-2 text-slate-700">{{ row.description ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.epChequeNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.modeType ?? "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ row.amount !== null ? currency.format(row.amount) : "-" }}</td>
                    <td class="px-3 py-2">{{ row.transDate ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.collectionMode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.statusEft ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.refNo ?? "-" }}</td>
                  </tr>
                </tbody>
                <tfoot v-if="paymentsRows.length > 0" class="bg-slate-50">
                  <tr class="border-t-2 border-slate-300">
                    <td colspan="5" class="px-3 py-2 text-right text-xs font-semibold uppercase">Total (filtered)</td>
                    <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums">{{ currency.format(paymentsFooter) }}</td>
                    <td colspan="4"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">{{ paymentsTotal === 0 ? "No rows" : `Page ${paymentsPage} / ${paymentsTotalPages} (${paymentsTotal})` }}</div>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="paymentsPage <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="paymentsPrev">Prev</button>
              <button type="button" :disabled="paymentsPage >= paymentsTotalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="paymentsNext">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('payments', 'pdf')"><Download class="h-3.5 w-3.5" />PDF</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('payments', 'csv')"><FileDown class="h-3.5 w-3.5" />CSV</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('payments', 'excel')"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
