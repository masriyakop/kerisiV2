<script setup lang="ts">
/**
 * Purchasing / Status PO & PR
 * (PAGEID 1520 / MENUID 1841)
 *
 * Source: FIMS BL `ZR_PURCHASING_STATUSPOPR_API` (dt_statusPOPR=1 and
 * DownloadCSV=1). Read-only datatable joining `purchase_order_master`,
 * `purchase_order_details`, `vend_customer_supplier`, `bills_master` and
 * `requisition_master` from DB_SECOND_DATABASE (mysql_secondary connection).
 *
 * The legacy page shipped a smart-filter popup with 6 fields (date range,
 * PO no, PR no, Vendor Id, PO status) and a CSV download. Here we follow the
 * Kitchen Sink "Datatable — smart filter pattern" which adds the standard
 * PDF / CSV / Excel export buttons.
 *
 * The action column on the legacy datatable deep-linked to two pages that
 * have not yet been migrated:
 *   - menuID 1827 (View PO details)
 *   - menuID 1771 (View PR details)
 * The controller still emits the deep-links so they start working
 * automatically once those pages land.
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
import { getStatusPoPrOptions, listStatusPoPr } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { StatusPoPrOptions, StatusPoPrRow, StatusPoPrSmartFilter } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<StatusPoPrRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("pom_order_no");
const sortDir = ref<"asc" | "desc">("asc");
const loading = ref(false);

const showSmartFilter = ref(false);
const smartFilter = ref<StatusPoPrSmartFilter>({
  poNo: "",
  prNo: "",
  vendorCode: "",
  poStatus: "",
  dateStart: "",
  dateEnd: "",
});
const options = ref<StatusPoPrOptions>({ poStatus: [] });

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

async function loadOptions() {
  try {
    const res = await getStatusPoPrOptions();
    options.value = res.data;
  } catch {
    options.value = { poStatus: [] };
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
    ...(smartFilter.value.poNo ? { pom_order_no: smartFilter.value.poNo } : {}),
    ...(smartFilter.value.prNo
      ? { rqm_requisition_no: smartFilter.value.prNo }
      : {}),
    ...(smartFilter.value.vendorCode
      ? { vcs_vendor_code: smartFilter.value.vendorCode }
      : {}),
    ...(smartFilter.value.poStatus
      ? { pom_order_status: smartFilter.value.poStatus }
      : {}),
    ...(smartFilter.value.dateStart
      ? { date_start: smartFilter.value.dateStart }
      : {}),
    ...(smartFilter.value.dateEnd
      ? { date_end: smartFilter.value.dateEnd }
      : {}),
  });
  try {
    const res = await listStatusPoPr(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load rows.");
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: string) {
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
    poNo: "",
    prNo: "",
    vendorCode: "",
    poStatus: "",
    dateStart: "",
    dateEnd: "",
  };
}

const exportColumns = [
  "PO No",
  "PR No",
  "PO Description",
  "Item Code",
  "Item Description",
  "Status",
  "Vendor Code",
  "Vendor Name",
  "Bill No",
  "Request Date",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } =
  useDatatableFeatures({
    pageName: "Status PO and PR",
    apiDataPath: "/purchasing/status-po-pr",
    defaultExportColumns: exportColumns,
    getFilteredList: () =>
      rows.value.map((r) => ({
        "PO No": r.poNo ?? "",
        "PR No": r.prNo ?? "",
        "PO Description": r.description ?? "",
        "Item Code": r.itemCode ?? "",
        "Item Description": r.itemDesc ?? "",
        Status: r.poStatus ?? "",
        "Vendor Code": r.vendorCode ?? "",
        "Vendor Name": r.vendorName ?? "",
        "Bill No": r.billNo ?? "",
        "Request Date": r.requestDate ?? "",
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
    const ws = wb.addWorksheet("Status PO & PR");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.poNo ?? "",
        r.prNo ?? "",
        r.description ?? "",
        r.itemCode ?? "",
        r.itemDesc ?? "",
        r.poStatus ?? "",
        r.vendorCode ?? "",
        r.vendorName ?? "",
        r.billNo ?? "",
        r.requestDate ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Status_PO_PR_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    <div class="mx-auto max-w-7xl space-y-4">
      <input
        ref="templateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onTemplateFileChange"
      />
      <p class="text-base font-semibold text-slate-500">Purchasing / Status PO &amp; PR</p>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Status PO &amp; PR</h1>
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
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1400px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pom_order_no')"
                    >
                      PO No
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('rqm_requisition_no')"
                    >
                      PR No
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pom_description')"
                    >
                      PO Description
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('itm_item_code')"
                    >
                      Item Code
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pod_item_spec')"
                    >
                      Item Description
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pom_order_status')"
                    >
                      Status
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('vcs_vendor_code')"
                    >
                      Vendor Code
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('vcs_vendor_name')"
                    >
                      Vendor Name
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bill No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pom_request_date')"
                    >
                      Request Date
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="11" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="11" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="`${row.pomOrderId ?? ''}-${row.prNo ?? ''}-${row.index}`"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">
                      <router-link
                        v-if="row.urlViewPo"
                        :to="row.urlViewPo"
                        class="text-sky-600 hover:underline"
                      >
                        {{ row.poNo ?? "-" }}
                      </router-link>
                      <span v-else>{{ row.poNo ?? "-" }}</span>
                    </td>
                    <!--
                      MENUID 1771 ("New Purchase Requisition") — the legacy
                      target of the urlViewPr deep-link — is not in the
                      current PAGE_SECOND_LEVEL_MENU migration scope, so PR
                      No is rendered as plain text for now. The controller
                      still emits urlViewPr on the payload; restore the
                      router-link once MENUID 1771 lands.
                    -->
                    <td class="px-3 py-2">{{ row.prNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.description ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.itemCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.itemDesc ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                      >
                        {{ row.poStatus ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2">{{ row.vendorCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vendorName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.billNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.requestDate || "-" }}</td>
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Date From</label>
              <input
                v-model="smartFilter.dateStart"
                type="date"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Date To</label>
              <input
                v-model="smartFilter.dateEnd"
                type="date"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">PO No</label>
              <input
                v-model="smartFilter.poNo"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                placeholder="PO number"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">PR No</label>
              <input
                v-model="smartFilter.prNo"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                placeholder="PR number"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Vendor Id</label>
              <input
                v-model="smartFilter.vendorCode"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                placeholder="Vendor code"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">PO Status</label>
              <select
                v-model="smartFilter.poStatus"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.poStatus" :key="opt" :value="opt">{{ opt }}</option>
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
