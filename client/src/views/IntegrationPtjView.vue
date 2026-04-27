<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { ChevronDown, Download, Eye, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import FimsListTable, { type FimsColumn } from "@/components/fims/FimsListTable.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  getIntegrationPtjOptions,
  getIntegrationPtjParents,
  getIntegrationPtjRow,
  listIntegrationPtj,
  promoteIntegrationPtj,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { IntegrationPtjPromoteInput, IntegrationPtjRow } from "@/types";

// Setup and Maintenance > Integration > Integration - PTJ (PAGEID 1860 / MENUID 2277).
// Legacy BL: AS_BL_SM_INTEGRATIONPTJ — datatable + popup-modal that promotes a
// staged `int_organization_unit` row into the production `organization_unit`
// table. The list is auto-filtered on the server to rows whose `iou_code` has
// not yet been promoted.
const PAGE_NAME = "Integration - PTJ";
const PAGE_BREADCRUMB = "Setup and Maintenance / Integration / Integration - PTJ";

const toast = useToast();

const rows = ref<IntegrationPtjRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(5);
const q = ref("");
const sortBy = ref("iou_id");
const sortDir = ref<"asc" | "desc">("asc");

const datatableRef = ref<DatatableRefApi | null>(null);
const overflowOpen = ref(false);
const overflowRoot = ref<HTMLElement | null>(null);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const showModal = ref(false);
const editing = ref<IntegrationPtjRow | null>(null);
const saving = ref(false);
const form = ref<IntegrationPtjPromoteInput>({
  iouCode: "",
  iouDesc: "",
  iouCodePersis: "",
  iouBursarFlag: "",
  orgCode: "",
  orgDesc: "",
  iouAddress: "",
  iouTelNo: "",
  iouFaxNo: "",
  ounLevel: "",
  ounCodeParent: "",
});
const levelOptions = ref<{ id: string; label: string }[]>([]);
const parentOptions = ref<{ id: string; label: string }[]>([]);

const columns: FimsColumn<IntegrationPtjRow>[] = [
  { key: "no", label: "No", value: (r) => r.index },
  { key: "iouCode", label: "PTJ Code", sortable: true, sortKey: "iou_code", hideable: true, value: (r) => r.iouCode ?? "" },
  { key: "iouCodePersis", label: "Persis Code", sortable: true, sortKey: "iou_code_persis", hideable: true, value: (r) => r.iouCodePersis ?? "" },
  { key: "iouDesc", label: "Description", sortable: true, sortKey: "iou_desc", hideable: true, value: (r) => r.iouDesc ?? "" },
  { key: "iouBursarFlag", label: "Bursar Flag", sortable: true, sortKey: "iou_bursar_flag", hideable: true, value: (r) => r.iouBursarFlag ?? "" },
  { key: "orgCode", label: "Org Code", sortable: true, sortKey: "org_code", hideable: true, value: (r) => r.orgCode ?? "" },
  { key: "orgDesc", label: "Org Desc", sortable: true, sortKey: "org_desc", hideable: true, value: (r) => r.orgDesc ?? "" },
  { key: "iouAddress", label: "Address", hideable: true, value: (r) => r.iouAddress ?? "" },
  { key: "iouTelNo", label: "Tel No", hideable: true, value: (r) => r.iouTelNo ?? "" },
  { key: "iouFaxNo", label: "Fax No", hideable: true, value: (r) => r.iouFaxNo ?? "" },
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
    const res = await listIntegrationPtj(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load integration PTJ.");
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

async function openEdit(row: IntegrationPtjRow) {
  try {
    const res = await getIntegrationPtjRow(row.iouId);
    editing.value = res.data;
    form.value = {
      iouCode: res.data.iouCode ?? "",
      iouDesc: res.data.iouDesc ?? "",
      iouCodePersis: res.data.iouCodePersis ?? "",
      iouBursarFlag: res.data.iouBursarFlag ?? "",
      orgCode: res.data.orgCode ?? "",
      orgDesc: res.data.orgDesc ?? "",
      iouAddress: res.data.iouAddress ?? "",
      iouTelNo: res.data.iouTelNo ?? "",
      iouFaxNo: res.data.iouFaxNo ?? "",
      ounLevel: "",
      ounCodeParent: "",
    };
    if (levelOptions.value.length === 0) {
      const opts = await getIntegrationPtjOptions();
      levelOptions.value = opts.data.levels;
    }
    showModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load row.");
  }
}

watch(
  () => form.value.ounLevel,
  async (level) => {
    if (!level) {
      parentOptions.value = [];
      return;
    }
    try {
      const res = await getIntegrationPtjParents(`?level=${encodeURIComponent(level)}`);
      parentOptions.value = res.data;
    } catch {
      parentOptions.value = [];
    }
  },
);

async function saveRow() {
  if (!editing.value) return;
  if (!form.value.iouCode?.trim()) {
    toast.error("Validation failed", "PTJ Code is required.");
    return;
  }
  if (!form.value.iouDesc?.trim()) {
    toast.error("Validation failed", "Description is required.");
    return;
  }

  saving.value = true;
  try {
    await promoteIntegrationPtj(editing.value.iouId, form.value);
    toast.success("Saved", "Integration PTJ promoted successfully.");
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
  apiDataPath: "/integration/ptj",
  defaultExportColumns: ["PTJ Code", "Persis Code", "Description", "Bursar Flag", "Org Code", "Org Desc", "Address", "Tel No", "Fax No"],
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
            :row-key="(r) => r.iouId"
            :group-by="(r) => `Bursar Flag ${r.iouBursarFlag ?? ''}`"
            min-width="1100px"
            @sort="onSort"
          >
            <template #action="{ row }">
              <button type="button" class="rounded p-1 text-slate-600 hover:bg-slate-100" title="View / Edit" @click="openEdit(row as IntegrationPtjRow)">
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
            <h3 class="text-base font-semibold text-slate-900">Promote PTJ — {{ form.iouCode || "(no code yet)" }}</h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">PTJ Code <span class="text-red-500">*</span></label>
              <input v-model="form.iouCode" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Persis Code</label>
              <input v-model="form.iouCodePersis" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Description <span class="text-red-500">*</span></label>
              <input v-model="form.iouDesc" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Bursar Flag</label>
              <input v-model="form.iouBursarFlag" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Org Code</label>
              <input v-model="form.orgCode" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Org Description</label>
              <input v-model="form.orgDesc" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Address</label>
              <textarea v-model="form.iouAddress" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tel No</label>
              <input v-model="form.iouTelNo" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Fax No</label>
              <input v-model="form.iouFaxNo" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Level</label>
              <div class="relative">
                <select v-model="form.ounLevel" class="w-full appearance-none rounded-lg border border-slate-300 bg-white px-3 py-2 pr-8 text-sm" :disabled="saving">
                  <option value="">— Select level —</option>
                  <option v-for="opt in levelOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
                <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              </div>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Parent PTJ</label>
              <div class="relative">
                <select v-model="form.ounCodeParent" class="w-full appearance-none rounded-lg border border-slate-300 bg-white px-3 py-2 pr-8 text-sm" :disabled="saving || !form.ounLevel">
                  <option value="">— Select parent —</option>
                  <option v-for="opt in parentOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
                <ChevronDown class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              </div>
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
