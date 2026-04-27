<script setup lang="ts">
/**
 * Student Finance / List of Offered (PAGEID 2181, MENUID 2636)
 *
 * Source: FIMS BL `MZ_BL_SF_OFFEREDLIST` — datatable + smart filter.
 * Joins offered_student against three optional payment surfaces
 * (receipt_master, receipt_batch_*, manual_journal_master) so each
 * row shows whichever channel actually settled the offer fee.
 *
 * Columns mirror the legacy `dt_bi`:
 *   No / Matric No / Name / No IC / Prog Level / Semester Intake /
 *   Payment / Tarikh / Receipt No
 *
 * The legacy COMPONENT_JS does not declare a `printout` field, so per
 * project policy we expose CSV / Excel / PDF exports for the rendered
 * page set (current page only — same envelope as
 * `StudentLedgerView.vue`).
 *
 * No row actions are migrated: the legacy `urlView` deep-link
 * (`menuID=1598`) is commented out on the FIMS source and the receipt
 * detail page is NOT migrated yet. The grid is therefore read-only.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  FileDown,
  FileSpreadsheet,
  Filter,
  MoreVertical,
  Search,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { getOfferedStudentOptions, listOfferedStudents } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  OfferedStudentOptions,
  OfferedStudentRow,
  OfferedStudentSmartFilter,
} from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<OfferedStudentRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

type OfferedSortKey =
  | "matric"
  | "name"
  | "ic_passport"
  | "program_level"
  | "offered_semester"
  | "payment"
  | "approve_date"
  | "receipt_no";

const sortBy = ref<OfferedSortKey>("matric");
const sortDir = ref<"asc" | "desc">("asc");

const showSmartFilter = ref(false);
const smartFilter = ref<OfferedStudentSmartFilter>({
  programLevel: "",
  offeredSemester: "",
});
const options = ref<OfferedStudentOptions>({
  programLevel: [],
  offeredSemester: [],
});

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function currencyMyr(amount: number | null): string {
  if (amount === null || !Number.isFinite(amount)) return "-";
  return new Intl.NumberFormat("en-MY", {
    style: "currency",
    currency: "MYR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
}

function formatDate(iso: string | null): string {
  if (!iso) return "-";
  // Server returns either YYYY-MM-DD or full ISO timestamp; either way
  // the first 10 chars are the date portion. Convert to dd/mm/yyyy.
  const d = String(iso).slice(0, 10);
  const [y, m, day] = d.split("-");
  if (!y || !m || !day) return iso;
  return `${day}/${m}/${y}`;
}

async function loadOptions() {
  try {
    const res = await getOfferedStudentOptions();
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
  if (smartFilter.value.programLevel)
    params.set("ost_program_level", smartFilter.value.programLevel);
  if (smartFilter.value.offeredSemester)
    params.set("ost_offered_semester", smartFilter.value.offeredSemester);

  try {
    const res = await listOfferedStudents(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load List of Offered.",
    );
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: OfferedSortKey) {
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
    programLevel: "",
    offeredSemester: "",
  };
}

const exportColumns = [
  "Matric No",
  "Name",
  "No IC",
  "Prog Level",
  "Semester Intake",
  "Payment",
  "Tarikh",
  "Receipt No",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "List of Offered",
  apiDataPath: "/student-finance/offered",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Matric No": r.matric,
      Name: r.name ?? "",
      "No IC": r.icPassport ?? "",
      "Prog Level": r.programLevelLabel ?? r.programLevel ?? "",
      "Semester Intake": r.offeredSemester ?? "",
      Payment: r.paymentAmt !== null ? r.paymentAmt.toFixed(2) : "",
      Tarikh: formatDate(r.paymentDate),
      "Receipt No": r.receiptNo ?? "",
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
    const ws = wb.addWorksheet("Offered");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.matric,
        r.name ?? "",
        r.icPassport ?? "",
        r.programLevelLabel ?? r.programLevel ?? "",
        r.offeredSemester ?? "",
        r.paymentAmt ?? "",
        formatDate(r.paymentDate),
        r.receiptNo ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `ListOfOffered_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

      <h1 class="page-title">Student Finance / List of Offered</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Offered Student</h1>
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
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No.</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('matric')"
                    >
                      Matric No
                      <span v-if="sortBy === 'matric'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('name')"
                    >
                      Name
                      <span v-if="sortBy === 'name'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('ic_passport')"
                    >
                      No IC
                      <span v-if="sortBy === 'ic_passport'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('program_level')"
                    >
                      Prog Level
                      <span v-if="sortBy === 'program_level'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('offered_semester')"
                    >
                      Semester Intake
                      <span v-if="sortBy === 'offered_semester'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('payment')"
                    >
                      Payment
                      <span v-if="sortBy === 'payment'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('approve_date')"
                    >
                      Tarikh
                      <span v-if="sortBy === 'approve_date'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('receipt_no')"
                    >
                      Receipt No
                      <span v-if="sortBy === 'receipt_no'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
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
                    :key="`${row.matric}-${row.offeredSemester ?? ''}-${row.receiptNo ?? ''}-${row.index}`"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.matric }}</td>
                    <td class="px-3 py-2">{{ row.name ?? "-" }}</td>
                    <td class="whitespace-nowrap px-3 py-2">{{ row.icPassport ?? "-" }}</td>
                    <td class="whitespace-nowrap px-3 py-2">
                      {{ row.programLevelLabel ?? row.programLevel ?? "-" }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">{{ row.offeredSemester ?? "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">
                      {{ currencyMyr(row.paymentAmt) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">{{ formatDate(row.paymentDate) }}</td>
                    <td class="whitespace-nowrap px-3 py-2">{{ row.receiptNo ?? "-" }}</td>
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
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Offered Semester
                </label>
                <select
                  v-model="smartFilter.offeredSemester"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option
                    v-for="opt in options.offeredSemester"
                    :key="opt.id"
                    :value="opt.id"
                  >
                    {{ opt.label }}
                  </option>
                </select>
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
