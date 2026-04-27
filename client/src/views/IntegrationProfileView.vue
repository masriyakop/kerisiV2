<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, Eye, FileDown, FileSpreadsheet, Filter, MoreVertical, Search, X } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import FimsListTable, { type FimsColumn } from "@/components/fims/FimsListTable.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { getIntegrationProfile, listIntegrationProfiles } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { IntegrationProfileRow } from "@/types";

// Setup and Maintenance > Integration > Integration - Profile (PAGEID 2000 / MENUID 2443).
// Legacy BL: SNA_API_SM_INTEGRATION_PROFILE — read-only datatable + smart-filter
// modal + read-only popup. Lists `int_capital_project` rows that have not been
// pushed to production yet (`icp_send_date IS NULL`) and whose project status
// is anything other than OPEN.
const PAGE_NAME = "Integration - Profile";
const PAGE_BREADCRUMB = "Setup and Maintenance / Integration / Integration - Profile";

const toast = useToast();

const rows = ref<IntegrationProfileRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(5);
const q = ref("");
const sortBy = ref("icp_project_id");
const sortDir = ref<"asc" | "desc">("desc");

const datatableRef = ref<DatatableRefApi | null>(null);
const overflowOpen = ref(false);
const overflowRoot = ref<HTMLElement | null>(null);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const showModal = ref(false);
const detail = ref<IntegrationProfileRow | null>(null);

const showSmartFilter = ref(false);
const smartFilter = ref<Record<string, string>>({
  icp_project_no: "",
  subsystemcode: "",
  fty_fund_type: "",
  lat_activity_code: "",
  ccr_costcentre: "",
  oun_code: "",
  icp_so_code: "",
  icp_yearnum: "",
  icp_period: "",
  icp_project_status: "",
});

const columns: FimsColumn<IntegrationProfileRow>[] = [
  { key: "no", label: "No", value: (r) => r.index },
  { key: "icpProjectNo", label: "Project No", sortable: true, sortKey: "icp_project_no", hideable: true, value: (r) => r.icpProjectNo ?? "" },
  { key: "subsystemcode", label: "Sub-system", sortable: true, sortKey: "subsystemcode", hideable: true, value: (r) => r.subsystemcode ?? "" },
  { key: "ftyFundType", label: "Fund Type", sortable: true, sortKey: "fty_fund_type", hideable: true, value: (r) => r.ftyFundType ?? "" },
  { key: "latActivityCode", label: "Activity Code", sortable: true, sortKey: "lat_activity_code", hideable: true, value: (r) => r.latActivityCode ?? "" },
  { key: "ccrCostcentre", label: "Cost Centre", sortable: true, sortKey: "ccr_costcentre", hideable: true, value: (r) => r.ccrCostcentre ?? "" },
  { key: "ounCode", label: "PTJ Code", sortable: true, sortKey: "oun_code", hideable: true, value: (r) => r.ounCode ?? "" },
  { key: "icpSoCode", label: "SO Code", sortable: true, sortKey: "icp_so_code", hideable: true, value: (r) => r.icpSoCode ?? "" },
  { key: "icpYearnum", label: "Year", sortable: true, sortKey: "icp_yearnum", hideable: true, value: (r) => r.icpYearnum ?? "" },
  { key: "icpPeriod", label: "Period", sortable: true, sortKey: "icp_period", hideable: true, value: (r) => r.icpPeriod ?? "" },
  { key: "icpProjectStatus", label: "Status", sortable: true, sortKey: "icp_project_status", hideable: true, value: (r) => r.icpProjectStatus ?? "" },
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
    Object.entries(smartFilter.value).forEach(([k, v]) => {
      if (v && String(v).trim() !== "") params.append(k, String(v).trim());
    });
    const res = await listIntegrationProfiles(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load integration profiles.");
  } finally {
    loading.value = false;
  }
}

function onSort(sortKey: string) {
  if (sortBy.value === sortKey) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
    sortBy.value = sortKey;
    sortDir.value = "desc";
  }
  page.value = 1;
  void loadRows();
}

function prevPage() { if (page.value > 1) { page.value -= 1; void loadRows(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; void loadRows(); } }

function applySmartFilter() {
  showSmartFilter.value = false;
  page.value = 1;
  void loadRows();
}
function resetSmartFilter() {
  Object.keys(smartFilter.value).forEach((k) => { smartFilter.value[k] = ""; });
}

async function openDetail(row: IntegrationProfileRow) {
  try {
    const res = await getIntegrationProfile(row.icpProjectId);
    detail.value = res.data;
    showModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load row.");
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
  apiDataPath: "/integration/profile",
  defaultExportColumns: ["Project No", "Sub-system", "Fund Type", "Activity Code", "Cost Centre", "PTJ Code", "SO Code", "Year", "Period", "Status"],
  getFilteredList: () => (datatableRef.value?.getExportConfig?.()?.data as Record<string, unknown>[]) ?? [],
  datatableRef,
  searchKeyword: q,
  smartFilter,
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
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="showSmartFilter = true">
                <Filter class="h-3.5 w-3.5" /> Filter
              </button>
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
            :row-key="(r) => r.icpProjectId"
            :group-by="(r) => `Status: ${r.icpProjectStatus ?? '(none)'}`"
            min-width="1300px"
            @sort="onSort"
          >
            <template #action="{ row }">
              <button type="button" class="rounded p-1 text-slate-600 hover:bg-slate-100" title="View" @click="openDetail(row as IntegrationProfileRow)">
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
      <div v-if="showSmartFilter" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showSmartFilter = false">
        <div class="w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart Filter</h3>
            <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100" @click="showSmartFilter = false">
              <X class="h-4 w-4" />
            </button>
          </div>
          <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Project No</label>
              <input v-model="smartFilter.icp_project_no" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Sub-system</label>
              <input v-model="smartFilter.subsystemcode" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Fund Type</label>
              <input v-model="smartFilter.fty_fund_type" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Activity Code</label>
              <input v-model="smartFilter.lat_activity_code" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Cost Centre</label>
              <input v-model="smartFilter.ccr_costcentre" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">PTJ Code</label>
              <input v-model="smartFilter.oun_code" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">SO Code</label>
              <input v-model="smartFilter.icp_so_code" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Year</label>
              <input v-model="smartFilter.icp_yearnum" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Period</label>
              <input v-model="smartFilter.icp_period" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
              <input v-model="smartFilter.icp_project_status" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="flex justify-between border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="resetSmartFilter">Reset</button>
            <div class="flex gap-2">
              <button type="button" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600" @click="showSmartFilter = false">Cancel</button>
              <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="applySmartFilter">OK</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showModal = false">
        <div class="w-full max-w-3xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Project — {{ detail?.icpProjectNo ?? "" }}</h3>
          </div>
          <div v-if="detail" class="grid grid-cols-1 gap-3 p-4 text-sm md:grid-cols-2">
            <div><dt class="text-xs text-slate-500">Project No</dt><dd class="text-slate-800">{{ detail.icpProjectNo ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">Sub-system</dt><dd class="text-slate-800">{{ detail.subsystemcode ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">Fund Type</dt><dd class="text-slate-800">{{ detail.ftyFundType ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">Activity Code</dt><dd class="text-slate-800">{{ detail.latActivityCode ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">Cost Centre</dt><dd class="text-slate-800">{{ detail.ccrCostcentre ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">PTJ Code</dt><dd class="text-slate-800">{{ detail.ounCode ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">SO Code</dt><dd class="text-slate-800">{{ detail.icpSoCode ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">Project Type</dt><dd class="text-slate-800">{{ detail.icpProjectType ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">Start Date</dt><dd class="text-slate-800">{{ detail.icpStartDate ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">End Date</dt><dd class="text-slate-800">{{ detail.icpEndDate ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">Year</dt><dd class="text-slate-800">{{ detail.icpYearnum ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">Period</dt><dd class="text-slate-800">{{ detail.icpPeriod ?? "-" }}</dd></div>
            <div class="md:col-span-2"><dt class="text-xs text-slate-500">Description</dt><dd class="text-slate-800">{{ detail.icpProjectDesc ?? "-" }}</dd></div>
            <div><dt class="text-xs text-slate-500">Status</dt><dd class="text-slate-800">{{ detail.icpProjectStatus ?? "-" }}</dd></div>
          </div>
          <div class="flex justify-end border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="showModal = false">Close</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
