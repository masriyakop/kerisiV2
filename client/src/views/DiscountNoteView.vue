<script setup lang="ts">
/**
 * Account Receivable / Discount Note (PAGEID 1463, MENUID 1043)
 *
 * Source: FIMS BL body at idx-35 of ACCOUNT_RECEIVABLE_BL.json (null-title,
 * module comment outdated as "credit list") — targets discount_note_master
 * / discount_note_details. Same structural shape as Credit/Debit Note lists.
 * Scoped to `dcm_system_id='AR_DC'`. Delete cascades to discount_note_details.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  Eye,
  FileDown,
  FileSpreadsheet,
  MoreVertical,
  Pencil,
  Plus,
  Search,
  Trash2,
  X,
} from "lucide-vue-next";
import { useRouter } from "vue-router";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { deleteDiscountNote, listDiscountNotes } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type { DiscountNoteRow, NoteListFooter } from "@/types";

const router = useRouter();
const toast = useToast();
const { confirm } = useConfirmDialog();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<DiscountNoteRow[]>([]);
const loading = ref(false);
const total = ref(0);
const footer = ref<NoteListFooter>({
  invoiceTotalAmount: 0,
  invoiceBalanceAmount: 0,
  discountNoteTotalAmount: 0,
});
const page = ref(1);
const limit = ref(10);
const q = ref("");
type SortKey =
  | "dcm_dcnote_no"
  | "dcm_dcnote_date"
  | "dcm_cust_id"
  | "dcm_cust_name"
  | "cim_invoice_no"
  | "dcm_dc_total_amount";
const sortBy = ref<SortKey>("dcm_dcnote_no");
const sortDir = ref<"asc" | "desc">("desc");

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function currencyMyr(amount: number): string {
  return new Intl.NumberFormat("en-MY", {
    style: "currency",
    currency: "MYR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0);
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value.trim() ? { q: q.value.trim() } : {}),
  });
  try {
    const res = await listDiscountNotes(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    const f = (res.meta?.footer ?? {}) as Partial<NoteListFooter>;
    footer.value = {
      invoiceTotalAmount: Number(f.invoiceTotalAmount ?? 0),
      invoiceBalanceAmount: Number(f.invoiceBalanceAmount ?? 0),
      discountNoteTotalAmount: Number(f.discountNoteTotalAmount ?? 0),
    };
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load discount notes.");
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: SortKey) {
  if (sortBy.value === col) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
    sortBy.value = col;
    sortDir.value = "asc";
  }
  void loadRows();
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

async function handleDelete(row: DiscountNoteRow) {
  const ok = await confirm({
    title: "Delete discount note?",
    message: `Delete discount note \u201C${row.discountNoteNo ?? row.id}\u201D and all its line items? This cannot be undone.`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!ok) return;

  try {
    await deleteDiscountNote(row.id);
    toast.success("Deleted", `Discount note ${row.discountNoteNo ?? row.id} removed.`);
    if (rows.value.length === 1 && page.value > 1) page.value -= 1;
    await loadRows();
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "Unable to delete discount note.");
  }
}

function goCreate() {
  void router.push("/admin/kerisi/m/1784");
}

function goEdit(row: DiscountNoteRow) {
  void router.push({ path: "/admin/kerisi/m/1784", query: { id: row.id, mode: "edit" } });
}

function goView(row: DiscountNoteRow) {
  void router.push({ path: "/admin/kerisi/m/1784", query: { id: row.id, mode: "view" } });
}

const exportColumns = [
  "Discount Note No",
  "Discount Note Date",
  "Customer",
  "Invoice No",
  "Invoice Total",
  "Invoice Balance",
  "Discount Note Amount",
  "Status",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Discount Note",
  apiDataPath: "/account-receivable/discount-note",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Discount Note No": r.discountNoteNo ?? "",
      "Discount Note Date": r.discountNoteDate ?? "",
      Customer: `${r.customerId ?? ""} ${r.customerName ?? ""}`.trim(),
      "Invoice No": r.invoiceNo ?? "",
      "Invoice Total": currencyMyr(r.invoiceTotalAmount),
      "Invoice Balance": currencyMyr(r.invoiceBalanceAmount),
      "Discount Note Amount": currencyMyr(r.discountNoteTotalAmount),
      Status: r.status ?? "",
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
    const ws = wb.addWorksheet("Discount Note");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.discountNoteNo ?? "",
        r.discountNoteDate ?? "",
        `${r.customerId ?? ""} ${r.customerName ?? ""}`.trim(),
        r.invoiceNo ?? "",
        r.invoiceTotalAmount,
        r.invoiceBalanceAmount,
        r.discountNoteTotalAmount,
        r.status ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Discount_Note_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
  await loadRows();
});

onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input
        ref="templateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onTemplateFileChange"
      />
      <h1 class="page-title">Account Receivable / Discount Note</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Discount Notes</h1>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800"
              @click="goCreate"
            >
              <Plus class="h-3.5 w-3.5" />
              Add
            </button>
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More">
              <MoreVertical class="h-4 w-4" />
            </button>
          </div>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
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
                <Search
                  class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                />
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
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1100px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dcm_dcnote_no')"
                    >
                      Discount Note No
                      <span v-if="sortBy === 'dcm_dcnote_no'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dcm_dcnote_date')"
                    >
                      Date
                      <span v-if="sortBy === 'dcm_dcnote_date'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dcm_cust_name')"
                    >
                      Customer
                      <span v-if="sortBy === 'dcm_cust_name'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('cim_invoice_no')"
                    >
                      Invoice No
                      <span v-if="sortBy === 'cim_invoice_no'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Invoice Total</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Invoice Balance</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('dcm_dc_total_amount')"
                    >
                      Discount Note Amount
                      <span v-if="sortBy === 'dcm_dc_total_amount'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Status</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr v-for="row in rows" :key="row.id" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.discountNoteNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.discountNoteDate ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <div class="font-medium text-slate-800">{{ row.customerName ?? "-" }}</div>
                      <div class="text-xs text-slate-500">
                        {{ row.customerId ?? "" }}
                        <span v-if="row.customerType" class="ml-1 text-slate-400">· {{ row.customerType }}</span>
                      </div>
                    </td>
                    <td class="px-3 py-2">{{ row.invoiceNo ?? "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ currencyMyr(row.invoiceTotalAmount) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ currencyMyr(row.invoiceBalanceAmount) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums font-medium">
                      {{ currencyMyr(row.discountNoteTotalAmount) }}
                    </td>
                    <td class="px-3 py-2">
                      <span
                        v-if="row.status"
                        class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                      >
                        {{ row.status }}
                      </span>
                      <span v-else class="text-xs text-slate-400">-</span>
                    </td>
                    <td class="px-3 py-2">
                      <div class="flex items-center gap-1">
                        <button
                          type="button"
                          title="View"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100"
                          @click="goView(row)"
                        >
                          <Eye class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          title="Edit"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100"
                          @click="goEdit(row)"
                        >
                          <Pencil class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          title="Delete"
                          class="rounded p-1 text-rose-500 hover:bg-rose-50"
                          @click="handleDelete(row)"
                        >
                          <Trash2 class="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="rows.length > 0" class="bg-slate-50">
                  <tr class="border-t-2 border-slate-300 font-medium">
                    <td colspan="5" class="px-3 py-2 text-right text-xs uppercase text-slate-600">Total</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ currencyMyr(footer.invoiceTotalAmount) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">
                      {{ currencyMyr(footer.invoiceBalanceAmount) }}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">
                      {{ currencyMyr(footer.discountNoteTotalAmount ?? 0) }}
                    </td>
                    <td colspan="2" />
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                :disabled="page <= 1"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="prevPage"
              >
                Prev
              </button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button
                type="button"
                :disabled="page >= totalPages"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="nextPage"
              >
                Next
              </button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleDownloadPDF"
              >
                <Download class="h-3.5 w-3.5" />PDF
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleDownloadCSV"
              >
                <FileDown class="h-3.5 w-3.5" />CSV
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="exportExcel"
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
