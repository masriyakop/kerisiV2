<script setup lang="ts">
/**
 * General Ledger / List of Year and Month
 * (PAGEID 2721 / MENUID 3287)
 *
 * Source: FIMS BL `MZ_BL_GL_LIST_YEAR_MONTH` (endpoints `dtListing`,
 * `save`, `viewDetails`, `download`).
 *
 * Components declared in PAGE_SECOND_LEVEL_MENU.json:
 *   - datatable (List of Year and Month)
 *   - form (Smart Filter) x3 — Year (text), Month (dropdown), Status (dropdown)
 *   - form (Details popup modal) — Year, Month, Status, Remark, Footer
 *
 * Kitchen Sink "Datatable — smart filter pattern" is applied (rule #3)
 * with an Add button in the header and PDF/CSV/Excel export buttons in
 * the pagination footer (rule #8 — COMPONENT_JS is Fund-Type boilerplate).
 *
 * Legacy BL has no delete endpoint, so only Edit is offered as a row
 * action.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  FileDown,
  FileSpreadsheet,
  Filter,
  MoreVertical,
  Pencil,
  Plus,
  Search,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  createGlYearMonth,
  getGlYearMonth,
  getGlYearMonthOptions,
  listGlYearMonth,
  updateGlYearMonth,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  GlYearMonthInput,
  GlYearMonthOptions,
  GlYearMonthRow,
  GlYearMonthSmartFilter,
} from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<GlYearMonthRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref<"gym_year" | "gym_month" | "gym_status" | "gym_remark">(
  "gym_year",
);
const sortDir = ref<"asc" | "desc">("desc");
const loading = ref(false);

const showSmartFilter = ref(false);
const smartFilter = ref<GlYearMonthSmartFilter>({
  year: "",
  month: "",
  status: "",
});
const options = ref<GlYearMonthOptions>({ months: [], statuses: [] });

const showFormModal = ref(false);
const formLoading = ref(false);
const saving = ref(false);
const editingId = ref<number | null>(null);
const formData = ref<GlYearMonthInput>({
  gym_year: "",
  gym_month: "",
  gym_status: "OPEN",
  gym_remark: "",
});
const formError = ref<string | null>(null);

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

async function loadOptions() {
  try {
    const res = await getGlYearMonthOptions();
    options.value = res.data;
  } catch {
    options.value = { months: [], statuses: [] };
  }
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
    ...(smartFilter.value.year ? { gym_year_filter: smartFilter.value.year } : {}),
    ...(smartFilter.value.month ? { gym_month_filter: smartFilter.value.month } : {}),
    ...(smartFilter.value.status ? { gym_status_filter: smartFilter.value.status } : {}),
  });
  try {
    const res = await listGlYearMonth(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load rows.");
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: "gym_year" | "gym_month" | "gym_status" | "gym_remark") {
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
  smartFilter.value = { year: "", month: "", status: "" };
}

function resetForm() {
  formData.value = {
    gym_year: "",
    gym_month: "",
    gym_status: "OPEN",
    gym_remark: "",
  };
  formError.value = null;
  editingId.value = null;
}

function openCreate() {
  resetForm();
  showFormModal.value = true;
}

async function openEdit(row: GlYearMonthRow) {
  resetForm();
  showFormModal.value = true;
  formLoading.value = true;
  try {
    const res = await getGlYearMonth(row.gymId);
    editingId.value = res.data.gymId;
    formData.value = {
      gym_year: res.data.year,
      gym_month: res.data.month,
      gym_status:
        (res.data.status as GlYearMonthInput["gym_status"]) === "CLOSE"
          ? "CLOSE"
          : "OPEN",
      gym_remark: res.data.remark ?? "",
    };
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load record.");
    showFormModal.value = false;
  } finally {
    formLoading.value = false;
  }
}

function closeForm() {
  if (saving.value) return;
  showFormModal.value = false;
  resetForm();
}

function validateForm(): string | null {
  if (!/^\d{4}$/.test(formData.value.gym_year.trim())) {
    return "Year must be a 4-digit value (e.g. 2026).";
  }
  if (!/^(0[1-9]|1[0-2])$/.test(formData.value.gym_month.trim())) {
    return "Month is required.";
  }
  if (!["OPEN", "CLOSE"].includes(formData.value.gym_status)) {
    return "Status must be OPEN or CLOSE.";
  }
  if ((formData.value.gym_remark ?? "").length > 400) {
    return "Remark must be 400 characters or fewer.";
  }
  return null;
}

async function saveForm() {
  const err = validateForm();
  if (err) {
    formError.value = err;
    return;
  }
  formError.value = null;
  saving.value = true;
  const payload: GlYearMonthInput = {
    gym_year: formData.value.gym_year.trim(),
    gym_month: formData.value.gym_month.trim(),
    gym_status: formData.value.gym_status,
    gym_remark: (formData.value.gym_remark ?? "").trim() || null,
  };
  try {
    if (editingId.value !== null) {
      await updateGlYearMonth(editingId.value, payload);
      toast.success("Record updated");
    } else {
      await createGlYearMonth(payload);
      toast.success("Record created");
    }
    showFormModal.value = false;
    resetForm();
    await loadRows();
  } catch (e) {
    const msg = e instanceof Error ? e.message : "Unable to save record.";
    formError.value = msg;
    toast.error("Save failed", msg);
  } finally {
    saving.value = false;
  }
}

const exportColumns = ["Year", "Month", "Status", "Remark"];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } =
  useDatatableFeatures({
    pageName: "List of Year and Month",
    apiDataPath: "/general-ledger/year-month",
    defaultExportColumns: exportColumns,
    getFilteredList: () =>
      rows.value.map((r) => ({
        Year: r.year ?? "",
        Month: r.month ?? "",
        Status: r.status ?? "",
        Remark: r.remark ?? "",
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
    const ws = wb.addWorksheet("List of Year and Month");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([idx + 1, r.year ?? "", r.month ?? "", r.status ?? "", r.remark ?? ""]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `List_of_Year_and_Month_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
  }
}

function monthLabel(month: string | null): string {
  if (!month) return "-";
  const match = options.value.months.find((m) => m.value === month);
  return match ? match.label : month;
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
      <h1 class="page-title">
        General Ledger / List of Year and Month
      </h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Year and Month</h1>
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
                @change="page = 1; void loadRows()"
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
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm"
                @click="showSmartFilter = true"
              >
                <Filter class="h-4 w-4" />Filter
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-700"
                @click="openCreate"
              >
                <Plus class="h-4 w-4" />Add
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
                      @click="toggleSort('gym_year')"
                    >
                      Year
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('gym_month')"
                    >
                      Month
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('gym_status')"
                    >
                      Status
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('gym_remark')"
                    >
                      Remark
                    </th>
                    <th class="px-3 py-2 text-center text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="row.gymId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.year }}</td>
                    <td class="px-3 py-2">{{ monthLabel(row.month) }}</td>
                    <td class="px-3 py-2">
                      <span
                        :class="[
                          'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                          row.status === 'OPEN'
                            ? 'bg-emerald-100 text-emerald-700'
                            : 'bg-slate-200 text-slate-700',
                        ]"
                      >
                        {{ row.status }}
                      </span>
                    </td>
                    <td class="px-3 py-2 text-slate-600">{{ row.remark ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <div class="flex items-center justify-center gap-1">
                        <button
                          type="button"
                          class="rounded p-1 text-sky-600 hover:bg-sky-50"
                          title="Edit"
                          @click="openEdit(row)"
                        >
                          <Pencil class="h-4 w-4" />
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
          <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Year</label>
              <input
                v-model="smartFilter.year"
                type="text"
                inputmode="numeric"
                maxlength="4"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                placeholder="e.g. 2026"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Month</label>
              <select
                v-model="smartFilter.month"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="m in options.months" :key="m.value" :value="m.value">
                  {{ m.label }}
                </option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select
                v-model="smartFilter.status"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="s in options.statuses" :key="s.value" :value="s.value">
                  {{ s.label }}
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

      <div
        v-if="showFormModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
        @click.self="closeForm"
      >
        <div
          class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl"
        >
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">
              {{ editingId !== null ? "Edit Year / Month" : "Add Year / Month" }}
            </h3>
            <button
              type="button"
              class="rounded-lg p-1 text-slate-500 hover:bg-slate-100"
              aria-label="Close"
              :disabled="saving"
              @click="closeForm"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
          <div class="flex-1 overflow-y-auto px-4 py-4">
            <div v-if="formLoading" class="py-12 text-center text-sm text-slate-500">
              Loading record...
            </div>
            <form v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2" @submit.prevent="saveForm">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Year <span class="text-rose-600">*</span>
                </label>
                <input
                  v-model="formData.gym_year"
                  type="text"
                  inputmode="numeric"
                  maxlength="4"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                  placeholder="e.g. 2026"
                  required
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Month <span class="text-rose-600">*</span>
                </label>
                <select
                  v-model="formData.gym_month"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                  required
                >
                  <option value="">Select month</option>
                  <option v-for="m in options.months" :key="m.value" :value="m.value">
                    {{ m.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Status <span class="text-rose-600">*</span>
                </label>
                <select
                  v-model="formData.gym_status"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                  required
                >
                  <option value="OPEN">OPEN</option>
                  <option value="CLOSE">CLOSE</option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Remark</label>
                <textarea
                  v-model="formData.gym_remark"
                  rows="3"
                  maxlength="400"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                  placeholder="Optional notes..."
                />
                <p class="mt-1 text-xs text-slate-400">
                  {{ (formData.gym_remark ?? "").length }} / 400
                </p>
              </div>
              <div
                v-if="formError"
                class="sm:col-span-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"
              >
                {{ formError }}
              </div>
            </form>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-4 py-2 text-sm"
              :disabled="saving"
              @click="closeForm"
            >
              Cancel
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:opacity-60"
              :disabled="saving || formLoading"
              @click="saveForm"
            >
              {{ saving ? "Saving..." : editingId !== null ? "Save changes" : "Create" }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
