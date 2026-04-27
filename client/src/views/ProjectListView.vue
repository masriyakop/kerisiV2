<script setup lang="ts">
/**
 * Project Monitoring / List of Project (MENUID 1544).
 * Read-only `capital_project` list. See ProjectMonitoringController.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { listProjectMonitoringProjects } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { ProjectListRow } from "@/types";

const toast = useToast();
const rows = ref<ProjectListRow[]>([]);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const sortBy = ref<
  "cpa_project_id" | "cpa_project_no" | "cpa_project_desc" | "oun_code"
  | "cpa_start_date" | "cpa_end_date" | "cpa_source" | "cpa_project_status"
>("cpa_project_no");
const sortDir = ref<"asc" | "desc">("asc");
const loading = ref(false);

const showSmartFilter = ref(false);
const smartFilter = ref({
  cpaProjectStatus: "",
  cpaSource: "",
  ounCode: "",
  cpaStartDateFrom: "",
  cpaStartDateTo: "",
  cpaEndDateFrom: "",
  cpaEndDateTo: "",
});

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function formatDate(s: string | null | undefined): string {
  if (!s) return "-";
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) return s;
  const dd = String(d.getDate()).padStart(2, "0");
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  return `${dd}/${mm}/${d.getFullYear()}`;
}

function toggleSort(col: typeof sortBy.value) {
  if (sortBy.value === col) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else { sortBy.value = col; sortDir.value = "asc"; }
  void loadRows();
}

async function loadRows() {
  loading.value = true;
  const sf = smartFilter.value;
  const params = new URLSearchParams({
    page: String(page.value), limit: String(limit.value), sort_by: sortBy.value, sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
    ...(sf.cpaProjectStatus ? { cpa_project_status: sf.cpaProjectStatus } : {}),
    ...(sf.cpaSource ? { cpa_source: sf.cpaSource } : {}),
    ...(sf.ounCode ? { oun_code: sf.ounCode } : {}),
    ...(sf.cpaStartDateFrom ? { cpa_start_date_from: sf.cpaStartDateFrom } : {}),
    ...(sf.cpaStartDateTo ? { cpa_start_date_to: sf.cpaStartDateTo } : {}),
    ...(sf.cpaEndDateFrom ? { cpa_end_date_from: sf.cpaEndDateFrom } : {}),
    ...(sf.cpaEndDateTo ? { cpa_end_date_to: sf.cpaEndDateTo } : {}),
  });
  try {
    const res = await listProjectMonitoringProjects(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load projects.");
  } finally { loading.value = false; }
}

function prevPage() { if (page.value > 1) { page.value -= 1; void loadRows(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; void loadRows(); } }
function applySmartFilter() { page.value = 1; showSmartFilter.value = false; void loadRows(); }
function resetSmartFilter() {
  smartFilter.value = { cpaProjectStatus: "", cpaSource: "", ounCode: "", cpaStartDateFrom: "", cpaStartDateTo: "", cpaEndDateFrom: "", cpaEndDateTo: "" };
}

const exportCols = ["Project No", "Description", "PTJ", "Start", "End", "Source", "Status"];
function asExport(r: ProjectListRow) {
  return [r.cpaProjectNo ?? "", r.cpaProjectDesc ?? "", r.ounCode ?? "", formatDate(r.cpaStartDate), formatDate(r.cpaEndDate), r.cpaSource ?? "", r.cpaProjectStatus ?? ""];
}

async function exportRows(kind: "pdf" | "csv" | "excel") {
  if (rows.value.length === 0) { toast.info("No data", "There is nothing to export."); return; }
  const filename = `Project_List_${new Date().toISOString().slice(0, 10)}`;
  const body = rows.value.map(asExport);
  if (kind === "csv") {
    const esc = (v: string | number) => { const s = String(v); return /,|\n|"/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; };
    const csv = [["No", ...exportCols], ...body.map((r, i) => [i + 1, ...r])].map((r) => r.map(esc).join(",")).join("\n");
    const a = document.createElement("a"); a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" })); a.download = `${filename}.csv`; a.click();
    toast.success("CSV downloaded");
  } else if (kind === "excel") {
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook(); const ws = wb.addWorksheet("Projects");
    ws.addRow(["No", ...exportCols]);
    body.forEach((r, i) => ws.addRow([i + 1, ...r]));
    const buf = await wb.xlsx.writeBuffer();
    const a = document.createElement("a"); a.href = URL.createObjectURL(new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" })); a.download = `${filename}.xlsx`; a.click();
    URL.revokeObjectURL(a.href);
    toast.success("Excel downloaded");
  } else {
    const { default: jsPDF } = await import("jspdf");
    const autoTable = (await import("jspdf-autotable")).default;
    const doc = new jsPDF({ orientation: "landscape" });
    doc.text(filename, 14, 14);
    autoTable(doc, { head: [["No", ...exportCols]], body: body.map((r, i) => [i + 1, ...r]), startY: 20, styles: { fontSize: 7 } });
    doc.save(`${filename}.pdf`);
    toast.success("PDF downloaded");
  }
}

let qTimer: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (qTimer) clearTimeout(qTimer);
  qTimer = setTimeout(() => { qTimer = null; page.value = 1; void loadRows(); }, 350);
});

onMounted(() => { void loadRows(); });
onUnmounted(() => { if (qTimer) clearTimeout(qTimer); });
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">Project Monitoring / List of Project</h1>
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of project</h1>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More"><MoreVertical class="h-4 w-4" /></button>
        </div>
        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="page = 1; loadRows()">
                <option v-for="n in [10, 25, 50, 100, 200]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input v-model="q" type="search" placeholder="Filter rows..." class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm" @keyup.enter="page = 1; void loadRows()" />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="q = ''" aria-label="Clear"><X class="h-3.5 w-3.5" /></button>
              </div>
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50" @click="showSmartFilter = true"><Filter class="h-4 w-4" />Filter</button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[480px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1000px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('cpa_project_no')">Project No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('cpa_project_desc')">Description</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('oun_code')">PTJ</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('cpa_start_date')">Start</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('cpa_end_date')">End</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('cpa_source')">Source</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('cpa_project_status')">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td colspan="8" class="px-3 py-6 text-center text-slate-500">Loading…</td></tr>
                  <tr v-else-if="!rows.length"><td colspan="8" class="px-3 py-6 text-center text-slate-500">No projects found.</td></tr>
                  <tr v-for="row in rows" :key="row.cpaProjectId" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.cpaProjectNo ?? "—" }}</td>
                    <td class="px-3 py-2 text-slate-700">{{ row.cpaProjectDesc ?? "—" }}</td>
                    <td class="px-3 py-2">{{ row.ounCode ?? "—" }}</td>
                    <td class="px-3 py-2">{{ formatDate(row.cpaStartDate) }}</td>
                    <td class="px-3 py-2">{{ formatDate(row.cpaEndDate) }}</td>
                    <td class="px-3 py-2">{{ row.cpaSource ?? "—" }}</td>
                    <td class="px-3 py-2">{{ row.cpaProjectStatus ?? "—" }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <p class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</p>
            <div class="flex items-center gap-2">
              <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" :disabled="page <= 1" @click="prevPage">Prev</button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" :disabled="page >= totalPages" @click="nextPage">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportRows('pdf')"><Download class="h-3.5 w-3.5" />PDF</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportRows('csv')"><FileDown class="h-3.5 w-3.5" />CSV</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportRows('excel')"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
            </div>
          </div>
        </div>
      </article>

      <Teleport to="body">
        <div v-if="showSmartFilter" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showSmartFilter = false">
          <div class="w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <h2 class="text-base font-semibold text-slate-900">Filter — projects</h2>
              <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="showSmartFilter = false" aria-label="Close"><X class="h-4 w-4" /></button>
            </div>
            <div class="grid gap-3 px-4 py-4 sm:grid-cols-2">
              <div><label class="mb-1 block text-sm text-slate-700">Status</label><input v-model="smartFilter.cpaProjectStatus" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm text-slate-700">Source</label><input v-model="smartFilter.cpaSource" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm text-slate-700">PTJ (code)</label><input v-model="smartFilter.ounCode" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm text-slate-700">Start from</label><input v-model="smartFilter.cpaStartDateFrom" type="text" placeholder="DD/MM/YYYY" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm text-slate-700">Start to</label><input v-model="smartFilter.cpaStartDateTo" type="text" placeholder="DD/MM/YYYY" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm text-slate-700">End from</label><input v-model="smartFilter.cpaEndDateFrom" type="text" placeholder="DD/MM/YYYY" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm text-slate-700">End to</label><input v-model="smartFilter.cpaEndDateTo" type="text" placeholder="DD/MM/YYYY" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-4 py-3">
              <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="resetSmartFilter">Reset</button>
              <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="applySmartFilter">OK</button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>
