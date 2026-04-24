<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Ban,
  Download,
  Eye,
  FileDown,
  FileSpreadsheet,
  FileText,
  Filter,
  MoreVertical,
  Pencil,
  Search,
  Trash2,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { getBudgetMovementOptions, listBudgetMovements } from "@/api/cms";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { useToast } from "@/composables/useToast";
import type { BudgetMovementOption, BudgetMovementRow, BudgetMovementType } from "@/types";

const props = defineProps<{ type: BudgetMovementType }>();

// Page labels / breadcrumb strings mirror legacy PAGETITLE + PAGEBREADCRUMBS
// (docs/migration/fims-budget/PAGE_1273.json / PAGE_1274.json / PAGE_1275.json).
const meta = computed(() => {
  switch (props.type) {
    case "increment":
      return { title: "Increment", breadcrumb: "Budget / Increment", pageName: "Budget Increment" };
    case "decrement":
      return { title: "Decrement", breadcrumb: "Budget / Decrement", pageName: "Budget Decrement" };
    case "virement":
      return { title: "Virement", breadcrumb: "Budget / Virement", pageName: "Budget Virement" };
  }
});

const toast = useToast();
const rows = ref<BudgetMovementRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const showSmartFilter = ref(false);
const smartFilter = ref<{ smBmmYear: string; smBmmStatus: string; smBmmMovementType: string }>({
  smBmmYear: "",
  smBmmStatus: "",
  smBmmMovementType: "",
});
const options = ref<{ year: BudgetMovementOption[]; status: BudgetMovementOption[]; movementType: BudgetMovementOption[] }>({
  year: [],
  status: [],
  movementType: [],
});

async function loadOptions() {
  try {
    const res = await getBudgetMovementOptions(props.type);
    options.value = res.data.smartFilter;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Could not load filter options.");
  }
}

async function loadRows() {
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      limit: String(limit.value),
      ...(q.value ? { q: q.value } : {}),
      ...(smartFilter.value.smBmmYear ? { smBmmYear: smartFilter.value.smBmmYear } : {}),
      ...(smartFilter.value.smBmmStatus ? { smBmmStatus: smartFilter.value.smBmmStatus } : {}),
      ...(props.type === "virement" && smartFilter.value.smBmmMovementType
        ? { smBmmMovementType: smartFilter.value.smBmmMovementType }
        : {}),
    });
    const res = await listBudgetMovements(props.type, `?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Could not load rows.");
  }
}

const currency = new Intl.NumberFormat("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function formatAmount(v: unknown): string {
  if (v === null || v === undefined || v === "") return "";
  const n = typeof v === "number" ? v : Number(String(v).replace(/,/g, ""));
  if (!Number.isFinite(n)) return String(v);
  return currency.format(n);
}
function formatDate(v: unknown): string {
  if (!v) return "";
  const d = new Date(String(v));
  if (Number.isNaN(d.getTime())) return "";
  return `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;
}

// Legacy dt_js for the Action column disabled certain icons by status. Mirror
// that here so the page feels familiar even though every action is inert until
// the editor pages (menuID 1557/1558/1559) are migrated.
function canEdit(row: BudgetMovementRow): boolean {
  return row.bmmStatus === "DRAFT";
}
function canDelete(row: BudgetMovementRow): boolean {
  return row.bmmStatus === "DRAFT";
}
function canCancel(row: BudgetMovementRow): boolean {
  return row.bmmStatus === "APPROVE" || row.bmmStatus === "APPROVED";
}
function notMigrated() {
  toast.info(
    "Not migrated yet",
    "The Budget editor / cancel / warrant flows live on separate legacy pages that are not part of this migration batch.",
  );
}

// Export columns shared across PDF / CSV / Excel — the legacy table shows
// Year, Reference No, Authority Approval, Remark/Reason, Amount, Status, Date.
// (The bmm_budget_movement_id column has class `d-none` in dt_class so we
// intentionally omit it here too — hidden columns must not leak into exports.)
const exportColumns = [
  "Year",
  "Reference No",
  "Authority Approval",
  "Remark/Reason",
  "Amount",
  "Status",
  "Date",
];

function toExportRow(r: BudgetMovementRow): Record<string, string | number> {
  return {
    Year: r.bmmYear ?? "",
    "Reference No": r.bmmBudgetMovementNo ?? "",
    "Authority Approval": r.bmmEndorseDoc ?? "",
    "Remark/Reason": r.bmmDescription ?? "",
    Amount: formatAmount(r.bmmTotalAmt),
    Status: r.bmmStatus ?? "",
    Date: formatDate(r.date),
  };
}

const datatableRef = ref<DatatableRefApi | null>(null);
const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: meta.value.pageName,
  apiDataPath: `/budget/movements/${props.type}`,
  defaultExportColumns: exportColumns,
  getFilteredList: () => rows.value.map(toExportRow),
  datatableRef,
  searchKeyword: q,
  smartFilter: smartFilter as unknown as import("vue").Ref<Record<string, unknown>>,
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
    const ws = wb.addWorksheet(meta.value.pageName);
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      const row = toExportRow(r);
      ws.addRow([idx + 1, ...exportColumns.map((c) => row[c] ?? "")]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${meta.value.pageName.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = { smBmmYear: "", smBmmStatus: "", smBmmMovementType: "" };
}

// Reload when the route switches between increment / decrement / virement
// without unmounting the view (e.g. via router navigation between sibling
// Budget menus).
watch(
  () => props.type,
  async () => {
    page.value = 1;
    q.value = "";
    resetSmartFilter();
    await loadOptions();
    await loadRows();
  },
);

onMounted(async () => {
  await loadOptions();
  await loadRows();
});
onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / Math.max(1, limit.value))));
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
      <h1 class="page-title">{{ meta.breadcrumb }}</h1>
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">{{ meta.title }}</h1>
          <button
            type="button"
            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
            aria-label="More options"
          >
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
                @change="
                  page = 1;
                  loadRows();
                "
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
                  @keyup.enter="
                    page = 1;
                    void loadRows();
                  "
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
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm"
                @click="showSmartFilter = true"
              >
                <Filter class="h-4 w-4" />Filter
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1100px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Year</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Reference No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Authority Approval</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Remark/Reason</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Amount</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Status</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Date</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in rows"
                    :key="row.bmmBudgetMovementId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2">{{ row.bmmYear ?? "" }}</td>
                    <td class="px-3 py-2">{{ row.bmmBudgetMovementNo ?? "" }}</td>
                    <td class="px-3 py-2">{{ row.bmmEndorseDoc ?? "" }}</td>
                    <td class="px-3 py-2">{{ row.bmmDescription ?? "" }}</td>
                    <td class="px-3 py-2 text-right">{{ formatAmount(row.bmmTotalAmt) }}</td>
                    <td class="px-3 py-2">{{ row.bmmStatus ?? "" }}</td>
                    <td class="px-3 py-2">{{ formatDate(row.date) }}</td>
                    <td class="px-3 py-2">
                      <div class="flex items-center gap-1">
                        <button
                          type="button"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                          :disabled="!canEdit(row)"
                          title="Edit"
                          @click="notMigrated"
                        >
                          <Pencil class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100"
                          title="View"
                          @click="notMigrated"
                        >
                          <Eye class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100"
                          :title="`Warrant ${meta.title}`"
                          @click="notMigrated"
                        >
                          <FileText class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                          :disabled="!canDelete(row)"
                          title="Delete"
                          @click="notMigrated"
                        >
                          <Trash2 class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                          :disabled="!canCancel(row)"
                          title="Cancel"
                          @click="notMigrated"
                        >
                          <Ban class="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="rows.length === 0">
                    <td class="px-3 py-6 text-center text-sm text-slate-400" colspan="9">No records.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">
              Page {{ page }} of {{ totalPages }} &middot; {{ total }} record(s)
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="page <= 1"
                @click="
                  page = Math.max(1, page - 1);
                  void loadRows();
                "
              >
                Previous
              </button>
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="page >= totalPages"
                @click="
                  page = Math.min(totalPages, page + 1);
                  void loadRows();
                "
              >
                Next
              </button>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-3">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
              @click="handleDownloadPDF"
            >
              <Download class="h-3.5 w-3.5" />PDF
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
              @click="handleDownloadCSV"
            >
              <FileDown class="h-3.5 w-3.5" />CSV
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
              @click="exportExcel"
            >
              <FileSpreadsheet class="h-3.5 w-3.5" />Excel
            </button>
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Year</label>
              <select
                v-model="smartFilter.smBmmYear"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.year" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select
                v-model="smartFilter.smBmmStatus"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.status" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div v-if="props.type === 'virement'">
              <label class="mb-1 block text-sm font-medium text-slate-700">Movement Type</label>
              <select
                v-model="smartFilter.smBmmMovementType"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.movementType" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
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
