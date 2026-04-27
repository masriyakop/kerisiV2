<script setup lang="ts">
/**
 * Audit Trail / System Transaction
 * (PAGEID 3 / MENUID 5)
 *
 * Source: FIMS BL `V2_AUDIT_SYSTEM_TRANSACTION_API`. Read-only datatable
 * over the legacy `fims_audit.system_transaction` ledger. Mirrors the
 * "Datatable — smart filter pattern" from the kitchen sink: a single
 * search box + Filter modal on the table header, paginated results,
 * sortable columns, and PDF / CSV / Excel exports. Each row exposes a
 * "View SQL" action that pops the full audited SQL.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Code, Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  getAuditSystemTransactionOptions,
  getAuditSystemTransactionSql,
  listAuditSystemTransactions,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { AuditSystemTransactionOptions, AuditSystemTransactionRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<AuditSystemTransactionRow[]>([]);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const sortBy = ref<"AUDIT_TIMESTAMP" | "AUDIT_ACTION" | "AUDIT_REQUEST_MENU_PATH" | "AUDIT_BROWSER" | "AUDIT_CLIENT_IP" | "AUDIT_ID">("AUDIT_TIMESTAMP");
const sortDir = ref<"asc" | "desc">("desc");
const loading = ref(false);

const showSmartFilter = ref(false);
const smartFilter = ref({
  searchDateFrom: "",
  searchDateTo: "",
  searchTransType: "",
  searchMenu: "",
  searchBrowser: "",
  searchUserType: "",
  searchUser: "",
});

const options = ref<AuditSystemTransactionOptions>({
  browsers: [],
  userTypes: [],
  transTypes: [],
});

const showSqlModal = ref(false);
const sqlText = ref("");
const sqlAuditId = ref<number | null>(null);
const sqlLoading = ref(false);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

async function loadOptions() {
  try {
    const res = await getAuditSystemTransactionOptions();
    options.value = res.data;
  } catch {
    // Non-fatal — filter dropdowns will simply be empty.
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
    ...(smartFilter.value.searchDateFrom ? { search_date_from: smartFilter.value.searchDateFrom } : {}),
    ...(smartFilter.value.searchDateTo ? { search_date_to: smartFilter.value.searchDateTo } : {}),
    ...(smartFilter.value.searchTransType ? { search_trans_type: smartFilter.value.searchTransType } : {}),
    ...(smartFilter.value.searchMenu ? { search_menu: smartFilter.value.searchMenu } : {}),
    ...(smartFilter.value.searchBrowser ? { search_browser: smartFilter.value.searchBrowser } : {}),
    ...(smartFilter.value.searchUserType ? { search_user_type: smartFilter.value.searchUserType } : {}),
    ...(smartFilter.value.searchUser ? { search_user: smartFilter.value.searchUser } : {}),
  });
  try {
    const res = await listAuditSystemTransactions(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load audit rows.");
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: typeof sortBy.value) {
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
    searchDateFrom: "",
    searchDateTo: "",
    searchTransType: "",
    searchMenu: "",
    searchBrowser: "",
    searchUserType: "",
    searchUser: "",
  };
}

async function viewSql(row: AuditSystemTransactionRow) {
  if (!row.hasSql) {
    toast.info("No SQL", "This audit row has no recorded SQL statement.");
    return;
  }
  sqlAuditId.value = row.auditId;
  sqlText.value = "";
  showSqlModal.value = true;
  sqlLoading.value = true;
  try {
    const res = await getAuditSystemTransactionSql(row.auditId);
    sqlText.value = res.data.sql || "";
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load SQL.");
    showSqlModal.value = false;
  } finally {
    sqlLoading.value = false;
  }
}

async function copySql() {
  try {
    await navigator.clipboard.writeText(sqlText.value);
    toast.success("Copied", "SQL copied to clipboard.");
  } catch {
    toast.error("Copy failed", "Clipboard access denied.");
  }
}

const exportColumns = [
  "Timestamp",
  "Action",
  "Menu",
  "Menu ID",
  "Browser",
  "Client IP",
  "User Type",
  "User",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Audit Trail - System Transaction",
  apiDataPath: "/audit/system-transactions",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      Timestamp: r.auditTimestamp ?? "",
      Action: r.auditAction ?? "",
      Menu: r.auditMenuPath ?? "",
      "Menu ID": r.auditMenuId ?? "",
      Browser: r.auditBrowser ?? "",
      "Client IP": r.auditClientIp ?? "",
      "User Type": r.auditUserType ?? "",
      User: r.auditUser ?? "",
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
    const ws = wb.addWorksheet("Audit");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.auditTimestamp ?? "",
        r.auditAction ?? "",
        r.auditMenuPath ?? "",
        r.auditMenuId ?? "",
        r.auditBrowser ?? "",
        r.auditClientIp ?? "",
        r.auditUserType ?? "",
        r.auditUser ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Audit_System_Transaction_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <h1 class="page-title">Audit Trail / System Transaction</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of System Transaction</h1>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More">
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
                @change="page = 1; loadRows()"
              >
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
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
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50"
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
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('AUDIT_TIMESTAMP')">Timestamp</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('AUDIT_ACTION')">Action</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('AUDIT_REQUEST_MENU_PATH')">Menu</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('AUDIT_BROWSER')">Browser</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('AUDIT_CLIENT_IP')">Client IP</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">User Type</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">User</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">SQL</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">No audit rows found.</td>
                  </tr>
                  <tr v-for="row in rows" :key="row.auditId" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.auditTimestamp ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{
                          'bg-emerald-100 text-emerald-700': row.auditAction === 'INSERT',
                          'bg-amber-100 text-amber-700': row.auditAction === 'UPDATE',
                          'bg-rose-100 text-rose-700': row.auditAction === 'DELETE',
                          'bg-sky-100 text-sky-700': row.auditAction === 'SELECT',
                          'bg-slate-200 text-slate-700': !['INSERT', 'UPDATE', 'DELETE', 'SELECT'].includes(row.auditAction ?? ''),
                        }"
                      >
                        {{ row.auditAction ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2 text-slate-700">{{ row.auditMenuPath ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.auditBrowser ?? "-" }}</td>
                    <td class="px-3 py-2 font-mono text-xs">{{ row.auditClientIp ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.auditUserType ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.auditUser ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <button
                        type="button"
                        :disabled="!row.hasSql"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="viewSql(row)"
                      >
                        <Code class="h-3 w-3" />View
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</div>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="page <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="prevPage">Prev</button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" :disabled="page >= totalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="nextPage">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadPDF">
                <Download class="h-3.5 w-3.5" />PDF
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadCSV">
                <FileDown class="h-3.5 w-3.5" />CSV
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportExcel">
                <FileSpreadsheet class="h-3.5 w-3.5" />Excel
              </button>
            </div>
          </div>
        </div>
      </article>

      <!-- Smart Filter modal -->
      <Teleport to="body">
        <div
          v-if="showSmartFilter"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
          @click.self="showSmartFilter = false"
        >
          <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <h2 class="text-base font-semibold text-slate-900">Filter Audit Rows</h2>
              <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" aria-label="Close" @click="showSmartFilter = false">
                <X class="h-4 w-4" />
              </button>
            </div>
            <div class="grid gap-3 px-4 py-4 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Date From</label>
                <input v-model="smartFilter.searchDateFrom" type="text" placeholder="DD/MM/YYYY" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Date To</label>
                <input v-model="smartFilter.searchDateTo" type="text" placeholder="DD/MM/YYYY" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Transaction Type</label>
                <select v-model="smartFilter.searchTransType" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                  <option value="">Any</option>
                  <option v-for="opt in options.transTypes" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Menu ID</label>
                <input v-model="smartFilter.searchMenu" type="text" inputmode="numeric" pattern="[0-9]*" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Browser</label>
                <select v-model="smartFilter.searchBrowser" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                  <option value="">Any</option>
                  <option v-for="opt in options.browsers" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">User Type</label>
                <select v-model="smartFilter.searchUserType" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                  <option value="">Any</option>
                  <option v-for="opt in options.userTypes" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">User ID</label>
                <input v-model="smartFilter.searchUser" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Numeric USER_ID" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
              </div>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-4 py-3">
              <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="resetSmartFilter">Reset</button>
              <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="applySmartFilter">OK</button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- View SQL modal -->
      <Teleport to="body">
        <div
          v-if="showSqlModal"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
          @click.self="showSqlModal = false"
        >
          <div class="w-full max-w-3xl rounded-lg border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <h2 class="text-base font-semibold text-slate-900">
                Audit SQL <span class="text-slate-400">#{{ sqlAuditId }}</span>
              </h2>
              <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" aria-label="Close" @click="showSqlModal = false">
                <X class="h-4 w-4" />
              </button>
            </div>
            <div class="px-4 py-4">
              <pre
                v-if="!sqlLoading"
                class="max-h-[60vh] overflow-auto rounded-lg border border-slate-200 bg-slate-900 p-3 font-mono text-xs leading-relaxed text-emerald-200"
              >{{ sqlText || "(empty)" }}</pre>
              <p v-else class="text-sm text-slate-500">Loading...</p>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-4 py-3">
              <button
                type="button"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm"
                :disabled="sqlLoading || !sqlText"
                @click="copySql"
              >Copy</button>
              <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="showSqlModal = false">Close</button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>
