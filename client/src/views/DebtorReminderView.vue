<script setup lang="ts">
/**
 * Debtor Portal > Financial Information > Reminder (MENUID 2584).
 *
 * Read-only datatable of `ccontroller_reminder` rows for the logged-in
 * debtor (joined to `ccontroller_master` by cm_id, restricted to
 * cm_business_type='INVOICE' AND cm_debtor_creditor='DEBTOR'). Scope
 * follows the logged-in user's `name` as the debtor id; an optional
 * `?debtor_id=` override is honoured for admin preview.
 *
 * The legacy page exposed per-row Print / Preview / Confirm actions
 * that drive `ccontroller_reminder.crm_confirm_status`; those are out
 * of scope here because the corresponding BL and PDF renderer are not
 * present in the migrated source material.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { listDebtorReminders } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { DebtorReminderRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<DebtorReminderRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("crm_reminder_date");
const sortDir = ref<"asc" | "desc">("desc");
const loading = ref(false);
const debtorIdDisplay = ref<string | null>(null);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function fmtDate(d: string | null): string {
  if (!d) return "-";
  try { return new Date(d).toLocaleDateString("en-GB"); } catch { return String(d); }
}

function fmtMoney(n: string | number | null): string {
  if (n === null || n === "" || n === undefined) return "-";
  const v = typeof n === "string" ? Number(n) : n;
  if (!Number.isFinite(v)) return String(n);
  return new Intl.NumberFormat("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
  });
  try {
    const res = await listDebtorReminders(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    const d = res.meta?.debtorId;
    debtorIdDisplay.value = typeof d === "string" ? d : null;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load reminders.");
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: string) {
  if (sortBy.value === col) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else { sortBy.value = col; sortDir.value = "asc"; }
  void loadRows();
}

function prevPage() { if (page.value > 1) { page.value -= 1; void loadRows(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; void loadRows(); } }

const exportColumns = [
  "Invoice No",
  "Amount Outstanding",
  "Reminder Date",
  "Reminder Bil",
  "Email Address",
  "Notification Method",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "List Of Debtor Reminder",
  apiDataPath: "/portal/debtor/reminders",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Invoice No": r.invoiceNo ?? "",
      "Amount Outstanding": fmtMoney(r.amountOutstanding),
      "Reminder Date": fmtDate(r.reminderDate),
      "Reminder Bil": r.reminderBil ?? "",
      "Email Address": r.emailAddress ?? "",
      "Notification Method": r.notificationMethod ?? "",
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
    const ws = wb.addWorksheet("Reminders");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.invoiceNo ?? "",
        fmtMoney(r.amountOutstanding),
        fmtDate(r.reminderDate),
        r.reminderBil ?? "",
        r.emailAddress ?? "",
        r.notificationMethod ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Debtor_Reminder_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <h1 class="page-title">Debtor Portal / Financial Information / Reminder</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <div>
            <h1 class="text-base font-semibold text-slate-900">List Of Debtor Reminder</h1>
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
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" aria-label="Clear search" @click="q = ''">
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full min-w-[900px] text-sm">
              <thead class="bg-slate-50">
                <tr class="border-b border-slate-200 text-left">
                  <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                  <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('crm_invoice_no')">Invoice No</th>
                  <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase" @click="toggleSort('crm_amt_outstanding')">Amount Outstanding (RM)</th>
                  <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('crm_reminder_date')">Reminder Date</th>
                  <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase" @click="toggleSort('crm_reminder_bil')">Reminder Bil</th>
                  <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('crm_email_addr')">Email</th>
                  <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('crm_notification_methd')">Notification</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading">
                  <td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                </tr>
                <tr v-else-if="rows.length === 0">
                  <td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                </tr>
                <tr v-for="row in rows" :key="row.id" class="border-b border-slate-100 hover:bg-slate-50">
                  <td class="px-3 py-2">{{ row.index }}</td>
                  <td class="px-3 py-2 font-medium text-slate-900">{{ row.invoiceNo ?? "-" }}</td>
                  <td class="px-3 py-2 text-right">{{ fmtMoney(row.amountOutstanding) }}</td>
                  <td class="px-3 py-2">{{ fmtDate(row.reminderDate) }}</td>
                  <td class="px-3 py-2 text-right">{{ row.reminderBil ?? "-" }}</td>
                  <td class="px-3 py-2">{{ row.emailAddress ?? "-" }}</td>
                  <td class="px-3 py-2">{{ row.notificationMethod ?? "-" }}</td>
                </tr>
              </tbody>
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
