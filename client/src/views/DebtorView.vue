<script setup lang="ts">
/**
 * Account Receivable / Debtor (PAGEID 1415, MENUID 1727)
 *
 * Source: FIMS BL `DT_AR_DEBTOR` — datatable + Status smart filter + delete
 * cascade. Legacy deep-links (View/Edit menuID 1728, Reset Password menuID 2598)
 * are not in the migrated menu set; their buttons render disabled here until
 * those editors are migrated.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  Eye,
  FileDown,
  FileSpreadsheet,
  Filter,
  KeyRound,
  MoreVertical,
  Pencil,
  Search,
  Trash2,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { deleteDebtor, getDebtorOptions, listDebtors } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type { ArOption, DebtorRow } from "@/types";

const toast = useToast();
const { confirm } = useConfirmDialog();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<DebtorRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const sortBy = ref<"vcs_vendor_code" | "vcs_vendor_name" | "status">("vcs_vendor_code");
const sortDir = ref<"asc" | "desc">("asc");

const showSmartFilter = ref(false);
const smartFilter = ref({ status: "" });
const statusOptions = ref<ArOption[]>([]);

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

async function loadOptions() {
  try {
    const res = await getDebtorOptions();
    statusOptions.value = res.data.status ?? [];
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load filter options.");
  }
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value.trim() ? { q: q.value.trim() } : {}),
    ...(smartFilter.value.status ? { status: smartFilter.value.status } : {}),
  });
  try {
    const res = await listDebtors(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load debtors.");
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: "vcs_vendor_code" | "vcs_vendor_name" | "status") {
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

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = { status: "" };
}

async function handleDelete(row: DebtorRow) {
  const ok = await confirm({
    title: "Delete debtor?",
    message: `Delete \u201C${row.vendorCode}\u201D (${row.vendorName ?? ""}) and its bank accounts / addresses? This cannot be undone.`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!ok) return;

  try {
    await deleteDebtor(row.id);
    toast.success("Deleted", `Debtor ${row.vendorCode} removed.`);
    if (rows.value.length === 1 && page.value > 1) page.value -= 1;
    await loadRows();
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "Unable to delete debtor.");
  }
}

const exportColumns = ["Payee Code", "Payee Name", "Outstanding Amount", "Status"];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Debtor",
  apiDataPath: "/account-receivable/debtor",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Payee Code": r.vendorCode,
      "Payee Name": r.vendorName ?? "",
      "Outstanding Amount": currencyMyr(r.outstandingAmount),
      Status: r.status ?? "",
    })),
  datatableRef,
  searchKeyword: q,
  smartFilter,
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
    const ws = wb.addWorksheet("Debtor");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([idx + 1, r.vendorCode, r.vendorName ?? "", r.outstandingAmount, r.status ?? ""]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Debtor_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    <div class="space-y-4">
      <input
        ref="templateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onTemplateFileChange"
      />
      <h1 class="page-title">Account Receivable / Debtor</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List Of Debtors</h1>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More">
            <MoreVertical class="h-4 w-4" />
          </button>
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
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-slate-50"
                @click="showSmartFilter = true"
              >
                <Filter class="h-4 w-4" />
                Filter
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[900px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('vcs_vendor_code')"
                    >
                      Payee Code
                      <span v-if="sortBy === 'vcs_vendor_code'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('vcs_vendor_name')"
                    >
                      Payee Name
                      <span v-if="sortBy === 'vcs_vendor_name'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Outstanding Amount</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('status')"
                    >
                      Status
                      <span v-if="sortBy === 'status'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr v-for="row in rows" :key="row.id" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.vendorCode }}</td>
                    <td class="px-3 py-2">{{ row.vendorName ?? "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ currencyMyr(row.outstandingAmount) }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{
                          'bg-emerald-100 text-emerald-700': row.status === 'ACTIVE',
                          'bg-slate-200 text-slate-600': row.status === 'INACTIVE',
                          'bg-rose-100 text-rose-700': row.status === 'BLACKLIST',
                          'bg-slate-100 text-slate-500': !row.status,
                        }"
                      >
                        {{ row.status ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2">
                      <div class="flex items-center gap-1">
                        <button
                          type="button"
                          disabled
                          title="View (editor not yet migrated)"
                          class="rounded p-1 text-slate-300 cursor-not-allowed"
                        >
                          <Eye class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          disabled
                          title="Edit (editor not yet migrated)"
                          class="rounded p-1 text-slate-300 cursor-not-allowed"
                        >
                          <Pencil class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          disabled
                          title="Reset password (editor not yet migrated)"
                          class="rounded p-1 text-slate-300 cursor-not-allowed"
                        >
                          <KeyRound class="h-3.5 w-3.5" />
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select
                v-model="smartFilter.status"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in statusOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-4 py-2 text-sm"
              @click="resetSmartFilter"
            >
              Reset
            </button>
            <button
              type="button"
              class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white"
              @click="applySmartFilter"
            >
              OK
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
