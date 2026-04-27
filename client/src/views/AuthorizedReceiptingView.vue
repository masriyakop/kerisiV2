<script setup lang="ts">
/**
 * Account Receivable / Authorized Receipting (PAGEID 1613, MENUID 1952)
 *
 * Source: FIMS BL `V2_AUTHORIZED_RECEIPTING_API` (dt_listing / dt_listingDelete).
 * The legacy BL scoped to logged-in staff OR UUM_UNIT_TERIMAAN user-group
 * membership — that group model is not yet ported, so this view is the global
 * admin list with a smart filter modal (staff id / ptj / event / position /
 * status). Matches the kitchen-sink "Datatable — smart filter pattern".
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  Eye,
  FileDown,
  FileSpreadsheet,
  Filter,
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
import {
  deleteAuthorizedReceipting,
  getAuthorizedReceiptingOptions,
  listAuthorizedReceipting,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type { ArOption, AuthorizedReceiptingRow } from "@/types";

const router = useRouter();
const toast = useToast();
const { confirm } = useConfirmDialog();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<AuthorizedReceiptingRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");
type SortKey =
  | "are_application_no"
  | "stf_staff_id"
  | "oun_code_ptj"
  | "are_event_code"
  | "are_position_code"
  | "are_status"
  | "createddate";
const sortBy = ref<SortKey>("createddate");
const sortDir = ref<"asc" | "desc">("desc");

const showSmartFilter = ref(false);
const smartFilter = ref({
  staff_id: "",
  ptj: "",
  event: "",
  position: "",
  status: "",
});
const statusOptions = ref<ArOption[]>([]);
const ptjOptions = ref<ArOption[]>([]);
const eventOptions = ref<ArOption[]>([]);
const positionOptions = ref<ArOption[]>([]);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

async function loadOptions() {
  try {
    const res = await getAuthorizedReceiptingOptions();
    statusOptions.value = res.data.status ?? [];
    ptjOptions.value = res.data.ptj ?? [];
    eventOptions.value = res.data.event ?? [];
    positionOptions.value = res.data.position ?? [];
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
    ...(smartFilter.value.staff_id ? { staff_id: smartFilter.value.staff_id } : {}),
    ...(smartFilter.value.ptj ? { ptj: smartFilter.value.ptj } : {}),
    ...(smartFilter.value.event ? { event: smartFilter.value.event } : {}),
    ...(smartFilter.value.position ? { position: smartFilter.value.position } : {}),
    ...(smartFilter.value.status ? { status: smartFilter.value.status } : {}),
  });
  try {
    const res = await listAuthorizedReceipting(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load applications.");
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

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = { staff_id: "", ptj: "", event: "", position: "", status: "" };
}

async function handleDelete(row: AuthorizedReceiptingRow) {
  const ok = await confirm({
    title: "Delete application?",
    message: `Delete authorized receipting application \u201C${row.applicationNo ?? row.id}\u201D? This cannot be undone.`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!ok) return;

  try {
    await deleteAuthorizedReceipting(row.id);
    toast.success("Deleted", `Application ${row.applicationNo ?? row.id} removed.`);
    if (rows.value.length === 1 && page.value > 1) page.value -= 1;
    await loadRows();
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "Unable to delete application.");
  }
}

function goCreate() {
  void router.push("/admin/kerisi/m/1953");
}

function goEdit(row: AuthorizedReceiptingRow) {
  void router.push({ path: "/admin/kerisi/m/1953", query: { id: row.id, mode: "edit" } });
}

function goView(row: AuthorizedReceiptingRow) {
  void router.push({ path: "/admin/kerisi/m/1953", query: { id: row.id, mode: "view" } });
}

const exportColumns = [
  "Application No",
  "Staff",
  "PTJ",
  "Event",
  "Position",
  "Reason",
  "Status",
  "Requested At",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Authorized Receipting",
  apiDataPath: "/account-receivable/authorized-receipting",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Application No": r.applicationNo ?? "",
      Staff: `${r.staffId ?? ""} ${r.staffName ?? ""}`.trim(),
      PTJ: `${r.ptjCode ?? ""} ${r.ptjDescription ?? ""}`.trim(),
      Event: `${r.eventCode ?? ""} ${r.eventDescription ?? ""}`.trim(),
      Position: `${r.positionCode ?? ""} ${r.positionDescription ?? ""}`.trim(),
      Reason: r.reason ?? "",
      Status: r.status ?? "",
      "Requested At": r.requestedAt ?? "",
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
    const ws = wb.addWorksheet("Authorized Receipting");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.applicationNo ?? "",
        `${r.staffId ?? ""} ${r.staffName ?? ""}`.trim(),
        `${r.ptjCode ?? ""} ${r.ptjDescription ?? ""}`.trim(),
        `${r.eventCode ?? ""} ${r.eventDescription ?? ""}`.trim(),
        `${r.positionCode ?? ""} ${r.positionDescription ?? ""}`.trim(),
        r.reason ?? "",
        r.status ?? "",
        r.requestedAt ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Authorized_Receipting_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

function statusClass(status: string | null): string {
  switch ((status ?? "").toUpperCase()) {
    case "APPROVE":
      return "bg-emerald-100 text-emerald-700";
    case "ENDORSED":
      return "bg-sky-100 text-sky-700";
    case "ENTRY":
    case "DRAFT":
      return "bg-slate-100 text-slate-700";
    case "RETURN":
      return "bg-amber-100 text-amber-700";
    case "REJECT":
      return "bg-rose-100 text-rose-700";
    default:
      return "bg-slate-100 text-slate-500";
  }
}
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
      <h1 class="page-title">Account Receivable / Authorized Receipting</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Applications</h1>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800"
              @click="goCreate"
            >
              <Plus class="h-3.5 w-3.5" />
              Add
            </button>
            <button
              type="button"
              class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
              aria-label="More"
            >
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
                  class="w-60 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm"
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
              <table class="w-full min-w-[1150px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('are_application_no')"
                    >
                      Application No
                      <span v-if="sortBy === 'are_application_no'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('stf_staff_id')"
                    >
                      Staff
                      <span v-if="sortBy === 'stf_staff_id'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('oun_code_ptj')"
                    >
                      PTJ
                      <span v-if="sortBy === 'oun_code_ptj'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('are_event_code')"
                    >
                      Event
                      <span v-if="sortBy === 'are_event_code'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('are_position_code')"
                    >
                      Position
                      <span v-if="sortBy === 'are_position_code'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('are_status')"
                    >
                      Status
                      <span v-if="sortBy === 'are_status'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('createddate')"
                    >
                      Requested At
                      <span v-if="sortBy === 'createddate'">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr v-for="row in rows" :key="row.id" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.applicationNo ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <div class="font-medium text-slate-800">{{ row.staffName ?? "-" }}</div>
                      <div class="text-xs text-slate-500">{{ row.staffId ?? "" }}</div>
                    </td>
                    <td class="px-3 py-2">
                      <div>{{ row.ptjCode ?? "-" }}</div>
                      <div class="text-xs text-slate-500">{{ row.ptjDescription ?? "" }}</div>
                    </td>
                    <td class="px-3 py-2">
                      <div>{{ row.eventCode ?? "-" }}</div>
                      <div class="text-xs text-slate-500">{{ row.eventDescription ?? "" }}</div>
                    </td>
                    <td class="px-3 py-2">
                      <div>{{ row.positionCode ?? "-" }}</div>
                      <div class="text-xs text-slate-500">{{ row.positionDescription ?? "" }}</div>
                    </td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="statusClass(row.status)"
                      >
                        {{ row.status ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2 text-xs text-slate-600">{{ row.requestedAt ?? "-" }}</td>
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
        <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Staff ID</label>
              <input
                v-model="smartFilter.staff_id"
                type="text"
                placeholder="e.g. 0001234"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">PTJ</label>
                <select
                  v-model="smartFilter.ptj"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in ptjOptions" :key="o.id" :value="o.id">{{ o.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select
                  v-model="smartFilter.status"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in statusOptions" :key="o.id" :value="o.id">{{ o.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Event</label>
                <select
                  v-model="smartFilter.event"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in eventOptions" :key="o.id" :value="o.id">{{ o.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Position</label>
                <select
                  v-model="smartFilter.position"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="o in positionOptions" :key="o.id" :value="o.id">{{ o.label }}</option>
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
