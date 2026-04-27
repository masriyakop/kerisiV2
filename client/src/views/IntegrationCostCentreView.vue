<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, Eye, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import FimsListTable, { type FimsColumn } from "@/components/fims/FimsListTable.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  getIntegrationCostCentre,
  listIntegrationCostCentres,
  promoteIntegrationCostCentre,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { IntegrationCostCentrePromoteInput, IntegrationCostCentreRow } from "@/types";

// Setup and Maintenance > Integration > Integration - Cost center (PAGEID 1861 / MENUID 2278).
// Legacy BL: AS_BL_SM_INTEGRATIONCOSTCENTRE — datatable + popup modal that
// promotes a staged `int_costcentre` row into the production `costcentre`
// table. The list is auto-filtered on the server to rows whose `ics_costcentre`
// has not yet been promoted.
const PAGE_NAME = "Integration - Cost Centre";
const PAGE_BREADCRUMB = "Setup and Maintenance / Integration / Integration - Cost Centre";

const toast = useToast();

const rows = ref<IntegrationCostCentreRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(5);
const q = ref("");
const sortBy = ref("ics_costcentre_id");
const sortDir = ref<"asc" | "desc">("asc");

const datatableRef = ref<DatatableRefApi | null>(null);
const overflowOpen = ref(false);
const overflowRoot = ref<HTMLElement | null>(null);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const showModal = ref(false);
const editing = ref<IntegrationCostCentreRow | null>(null);
const saving = ref(false);
const form = ref<IntegrationCostCentrePromoteInput>({
  icsCostcentre: "",
  icsCostcentreDesc: "",
  icsHostelCode: "",
  icsStatus: "Active",
});

const columns: FimsColumn<IntegrationCostCentreRow>[] = [
  { key: "no", label: "No", value: (r) => r.index },
  { key: "icsCostcentre", label: "Cost Centre", sortable: true, sortKey: "ics_costcentre", hideable: true, value: (r) => r.icsCostcentre ?? "" },
  { key: "icsCostcentreDesc", label: "Description", sortable: true, sortKey: "ics_costcentre_desc", hideable: true, value: (r) => r.icsCostcentreDesc ?? "" },
  { key: "icsHostelCode", label: "Hostel Code", sortable: true, sortKey: "ics_hostel_code", hideable: true, value: (r) => r.icsHostelCode ?? "" },
  { key: "icsStatus", label: "Status", sortable: true, sortKey: "ics_status", hideable: true, value: (r) => r.icsStatus ?? "" },
  { key: "action", label: "Action" },
];

async function loadRows() {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      limit: String(limit.value),
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
      ...(q.value.trim() ? { q: q.value.trim() } : {}),
    });
    const res = await listIntegrationCostCentres(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load integration cost centres.");
  } finally {
    loading.value = false;
  }
}

function onSort(sortKey: string) {
  if (sortBy.value === sortKey) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
    sortBy.value = sortKey;
    sortDir.value = "asc";
  }
  page.value = 1;
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

async function openEdit(row: IntegrationCostCentreRow) {
  try {
    const res = await getIntegrationCostCentre(row.icsCostcentreId);
    editing.value = res.data;
    form.value = {
      icsCostcentre: res.data.icsCostcentre ?? "",
      icsCostcentreDesc: res.data.icsCostcentreDesc ?? "",
      icsHostelCode: res.data.icsHostelCode ?? "",
      icsStatus: res.data.icsStatus ?? "Active",
    };
    showModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load row.");
  }
}

async function saveRow() {
  if (!editing.value) return;
  if (!form.value.icsCostcentre?.trim()) {
    toast.error("Validation failed", "Cost Centre code is required.");
    return;
  }
  if (!form.value.icsCostcentreDesc?.trim()) {
    toast.error("Validation failed", "Description is required.");
    return;
  }

  saving.value = true;
  try {
    await promoteIntegrationCostCentre(editing.value.icsCostcentreId, form.value);
    toast.success("Saved", "Integration cost centre promoted successfully.");
    showModal.value = false;
    await loadRows();
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to save.");
  } finally {
    saving.value = false;
  }
}

const {
  templateFileInputRef,
  isGrouped,
  handleSaveTemplate,
  handleLoadTemplate,
  onTemplateFileChange,
  handleUngroupList,
  handleGroupList,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: PAGE_NAME,
  apiDataPath: "/integration/cost-centre",
  defaultExportColumns: ["Cost Centre", "Description", "Hostel Code", "Status"],
  getFilteredList: () => (datatableRef.value?.getExportConfig?.()?.data as Record<string, unknown>[]) ?? [],
  datatableRef,
  searchKeyword: q,
  applyFilters: () => void loadRows(),
});

async function exportExcel() {
  try {
    const cfg = datatableRef.value?.getExportConfig?.();
    const columnsOut = cfg?.columns ?? [];
    const data = (cfg?.data as Record<string, unknown>[]) ?? [];
    if (data.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet(PAGE_NAME);
    ws.addRow(["No", ...columnsOut]);
    data.forEach((row, idx) => {
      const values = columnsOut.map((c) => (row[c] ?? "") as string | number);
      ws.addRow([idx + 1, ...values]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${PAGE_NAME.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
  }
}

function onClickOutside(event: MouseEvent) {
  if (!overflowOpen.value) return;
  if (!overflowRoot.value?.contains(event.target as Node)) overflowOpen.value = false;
}

let qSearchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (qSearchDebounce) clearTimeout(qSearchDebounce);
  qSearchDebounce = setTimeout(() => {
    qSearchDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

onMounted(async () => {
  await loadRows();
  document.addEventListener("click", onClickOutside);
});

onUnmounted(() => {
  if (qSearchDebounce) clearTimeout(qSearchDebounce);
  document.removeEventListener("click", onClickOutside);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />

      <h1 class="page-title">{{ PAGE_BREADCRUMB }}</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">{{ PAGE_NAME }}</h1>
          <div ref="overflowRoot" class="relative">
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click.stop="overflowOpen = !overflowOpen">
              <MoreVertical class="h-4 w-4" />
            </button>
            <div v-if="overflowOpen" class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg" @click.stop>
              <button type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleSaveTemplate()">Save template</button>
              <button type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleLoadTemplate()">Load template</button>
              <button v-if="isGrouped" type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleUngroupList()">Ungroup list</button>
              <button v-else type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleGroupList()">Group list</button>
            </div>
          </div>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm shadow-sm" @change="page = 1; loadRows()">
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input v-model="q" type="search" placeholder="Filter rows..." class="w-52 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm shadow-sm" @keyup.enter="page = 1; void loadRows()" />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="q = ''; page = 1; loadRows()">
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="loading" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">Loading&hellip;</div>
          <FimsListTable
            v-else
            ref="datatableRef"
            :rows="rows"
            :columns="columns"
            :grouped="isGrouped"
            :sort-by="sortBy"
            :sort-dir="sortDir"
            :row-key="(r) => r.icsCostcentreId"
            :group-by="(r) => `Status ${r.icsStatus ?? ''}`"
            min-width="900px"
            @sort="onSort"
          >
            <template #action="{ row }">
              <button type="button" class="rounded p-1 text-slate-600 hover:bg-slate-100" title="View / Edit" @click="openEdit(row as IntegrationCostCentreRow)">
                <Eye class="h-3.5 w-3.5" />
              </button>
            </template>
          </FimsListTable>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</div>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" :disabled="page <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="prevPage">Prev</button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" :disabled="page >= totalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="nextPage">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadPDF">
                <Download class="h-3.5 w-3.5" /> PDF
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadCSV">
                <FileDown class="h-3.5 w-3.5" /> CSV
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="exportExcel">
                <FileSpreadsheet class="h-3.5 w-3.5" /> Excel
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showModal = false">
        <div class="w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Promote Cost Centre — {{ form.icsCostcentre || "(no code yet)" }}</h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Cost Centre Code <span class="text-red-500">*</span></label>
              <input v-model="form.icsCostcentre" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Hostel Code</label>
              <input v-model="form.icsHostelCode" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Description <span class="text-red-500">*</span></label>
              <input v-model="form.icsCostcentreDesc" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="form.icsStatus" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" :disabled="saving">
                <option value="Active">Active</option>
                <option value="Unactive">Unactive</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600" :disabled="saving" @click="showModal = false">Cancel</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="saving" @click="saveRow">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
