<script setup lang="ts">
/**
 * Student Finance / Student Profile or Ledger (PAGEID 1232, MENUID 1509)
 *
 * Source: FIMS BL `V2_SFSP_LEDGER_API` — datatable + smart filter
 * (Matric, Name, NRIC/Passport, Semester No., Program Level, Status).
 *
 * Legacy deep-links — View Profile (menuID 1512) and View Ledger
 * (api/V2_SFSP_LEDGER_VIEWPROFILE_API) — are NOT in the migrated menu
 * set yet; those action buttons render disabled here until their
 * editors are migrated.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  FileDown,
  FileSpreadsheet,
  FileText,
  Filter,
  MoreVertical,
  Search,
  User,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { getLedgerOptions, listLedger } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { LedgerOptions, LedgerRow, LedgerSmartFilter } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<LedgerRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

type LedgerSortKey =
  | "std_student_id"
  | "std_student_name"
  | "ic_passport"
  | "std_sem_level"
  | "std_program_level"
  | "std_status_desc";

const sortBy = ref<LedgerSortKey>("std_student_id");
const sortDir = ref<"asc" | "desc">("asc");

const showSmartFilter = ref(false);
const smartFilter = ref<LedgerSmartFilter>({
  studentId: "",
  studentName: "",
  icPassport: "",
  semLevel: "",
  programLevel: "",
  statusDesc: [],
});
const options = ref<LedgerOptions>({ programLevel: [], status: [] });

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
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
    const res = await getLedgerOptions();
    options.value = res.data;
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load filter options.",
    );
  }
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
  });
  if (q.value.trim()) params.set("q", q.value.trim());
  if (smartFilter.value.studentId) params.set("std_student_id", smartFilter.value.studentId);
  if (smartFilter.value.studentName) params.set("std_student_name", smartFilter.value.studentName);
  if (smartFilter.value.icPassport) params.set("ic_passport", smartFilter.value.icPassport);
  if (smartFilter.value.semLevel) params.set("std_sem_level", smartFilter.value.semLevel);
  if (smartFilter.value.programLevel)
    params.set("std_program_level", smartFilter.value.programLevel);
  if (smartFilter.value.statusDesc.length)
    params.set("std_status_desc", smartFilter.value.statusDesc.join(","));

  try {
    const res = await listLedger(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load student ledger.",
    );
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: LedgerSortKey) {
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
  smartFilter.value = {
    studentId: "",
    studentName: "",
    icPassport: "",
    semLevel: "",
    programLevel: "",
    statusDesc: [],
  };
}

function toggleStatus(id: string) {
  const list = smartFilter.value.statusDesc;
  const i = list.indexOf(id);
  if (i === -1) list.push(id);
  else list.splice(i, 1);
}

const exportColumns = [
  "Matric",
  "Name",
  "NRIC/Passport",
  "Sems No.",
  "Program Level",
  "Status",
  "Balance",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Student Profile or Ledger",
  apiDataPath: "/student-finance/ledger",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      Matric: r.studentId,
      Name: r.studentName ?? "",
      "NRIC/Passport": r.icPassport ?? "",
      "Sems No.": r.semLevel ?? "",
      "Program Level": r.programLevelLabel ?? r.programLevel ?? "",
      Status: r.statusDesc,
      Balance: currencyMyr(r.outstandingAmt),
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
    const ws = wb.addWorksheet("Ledger");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.studentId,
        r.studentName ?? "",
        r.icPassport ?? "",
        r.semLevel ?? "",
        r.programLevelLabel ?? r.programLevel ?? "",
        r.statusDesc,
        r.outstandingAmt,
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `StudentLedger_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error(
      "Export failed",
      e instanceof Error ? e.message : "Excel export failed.",
    );
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

      <h1 class="page-title">Student Finance / Student Profile or Ledger</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Students</h1>
          <button
            type="button"
            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
            aria-label="More"
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
                      @click="toggleSort('std_student_id')"
                    >
                      Matric
                      <span v-if="sortBy === 'std_student_id'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('std_student_name')"
                    >
                      Name
                      <span v-if="sortBy === 'std_student_name'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('ic_passport')"
                    >
                      NRIC / Passport
                      <span v-if="sortBy === 'ic_passport'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('std_sem_level')"
                    >
                      Sems No.
                      <span v-if="sortBy === 'std_sem_level'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('std_program_level')"
                    >
                      Program Level
                      <span v-if="sortBy === 'std_program_level'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('std_status_desc')"
                    >
                      Status
                      <span v-if="sortBy === 'std_status_desc'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Balance</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="row.studentId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.studentId }}</td>
                    <td class="px-3 py-2">{{ row.studentName ?? "-" }}</td>
                    <td class="whitespace-nowrap px-3 py-2">{{ row.icPassport ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.semLevel ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.programLevelLabel ?? row.programLevel ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{
                          'bg-emerald-100 text-emerald-700':
                            row.statusDesc === 'ACTIVE' || row.statusDesc === 'AKTIF',
                          'bg-slate-200 text-slate-600':
                            row.statusDesc && row.statusDesc !== 'ACTIVE' && row.statusDesc !== 'AKTIF',
                          'bg-slate-100 text-slate-500': !row.statusDesc,
                        }"
                      >
                        {{ row.statusDesc || "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">
                      {{ currencyMyr(row.outstandingAmt) }}
                    </td>
                    <td class="px-3 py-2">
                      <div class="flex items-center gap-1">
                        <button
                          type="button"
                          disabled
                          title="View Profile (editor not yet migrated)"
                          class="cursor-not-allowed rounded p-1 text-slate-300"
                        >
                          <User class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          disabled
                          title="View Ledger (report not yet migrated)"
                          class="cursor-not-allowed rounded p-1 text-slate-300"
                        >
                          <FileText class="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3"
          >
            <div class="text-xs text-slate-500">
              Showing {{ startIdx }}-{{ endIdx }} of {{ total }}
            </div>
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
        <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
          </div>
          <div class="space-y-4 p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Matric</label>
                <input
                  v-model="smartFilter.studentId"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                <input
                  v-model="smartFilter.studentName"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">NRIC / Passport</label>
                <input
                  v-model="smartFilter.icPassport"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Semester No.</label>
                <input
                  v-model="smartFilter.semLevel"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Program Level</label>
                <select
                  v-model="smartFilter.programLevel"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option
                    v-for="opt in options.programLevel"
                    :key="opt.id"
                    :value="opt.id"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <div class="flex flex-wrap gap-2">
                  <label
                    v-for="opt in options.status"
                    :key="opt.id"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-2.5 py-1 text-xs"
                  >
                    <input
                      type="checkbox"
                      :checked="smartFilter.statusDesc.includes(String(opt.id))"
                      class="rounded border-slate-300"
                      @change="toggleStatus(String(opt.id))"
                    />
                    {{ opt.label }}
                  </label>
                  <p v-if="options.status.length === 0" class="text-xs text-slate-400">
                    No status values yet.
                  </p>
                </div>
              </div>
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
