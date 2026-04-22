<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Columns3,
  Download,
  FileDown,
  FileSpreadsheet,
  GripVertical,
  MoreVertical,
  Pencil,
  Search,
  X,
} from "lucide-vue-next";

import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableExportConfig, DatatableRefApi, DatatableTemplateState } from "@/composables/useDatatableFeatures";
import { useToast } from "@/composables/useToast";

export type CheckErrorColumn = {
  key: string;
  label: string;
  sortable?: boolean;
  hideable?: boolean;
  align?: "left" | "right" | "center";
  formatter?: (row: Record<string, unknown>) => string;
};

type Props = {
  title: string;
  columns: CheckErrorColumn[];
  fetcher: (params: string) => Promise<{ data: Record<string, unknown>[]; meta?: Record<string, unknown> }>;
  defaultSortBy: string;
  defaultSortDir?: "asc" | "desc";
  exportName: string;
  pageSize?: number;
  rowKey?: (row: Record<string, unknown>, idx: number) => string | number;
  editKey?: string;
};

const props = withDefaults(defineProps<Props>(), {
  defaultSortDir: "asc",
  pageSize: 5,
  rowKey: () => (_: Record<string, unknown>, idx: number) => idx,
  editKey: "",
});

const emit = defineEmits<{
  (e: "edit", id: string | number): void;
}>();

const toast = useToast();

const rows = ref<Record<string, unknown>[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(props.pageSize);
const q = ref("");

const sortColumn = ref<string>(props.defaultSortBy);
const sortDirection = ref<"asc" | "desc">(props.defaultSortDir);

const columnOrder = ref<string[]>(["no", ...props.columns.map((c) => c.key)]);
const hiddenColumns = ref<Set<string>>(new Set());
const showColumnsPanel = ref(false);
const draggingKey = ref<string | null>(null);
const dragOverKey = ref<string | null>(null);

const overflowOpen = ref(false);
const overflowRoot = ref<HTMLElement | null>(null);

const columnMap = computed(() => {
  const map = new Map<string, CheckErrorColumn>();
  props.columns.forEach((c) => map.set(c.key, c));
  map.set("no", { key: "no", label: "No" });
  if (props.editKey) map.set("action", { key: "action", label: "Action" });
  return map;
});

const fullOrder = computed(() =>
  props.editKey ? [...columnOrder.value, "action"] : columnOrder.value,
);

const visibleColumns = computed(() =>
  fullOrder.value.map((k) => columnMap.value.get(k)!).filter((c) => c && !hiddenColumns.value.has(c.key)),
);

const hiddenList = computed(() =>
  columnOrder.value.filter((k) => hiddenColumns.value.has(k)).map((k) => columnMap.value.get(k)!),
);

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / limit.value)));

const hasScrollableBody = computed(() => rows.value.length > 10);

function canMove(key: string): boolean {
  if (key === "no" || key === "action") return false;
  const col = columnMap.value.get(key);
  return !!col?.hideable;
}

function canHide(key: string): boolean {
  if (key === "no" || key === "action") return false;
  const col = columnMap.value.get(key);
  return !!col?.hideable;
}

function toggleSort(key: string) {
  const col = columnMap.value.get(key);
  if (!col?.sortable) return;
  if (sortColumn.value === key) sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
  else {
    sortColumn.value = key;
    sortDirection.value = "asc";
  }
  void loadRows();
}

function hideColumn(key: string) {
  if (!canHide(key)) return;
  const next = new Set(hiddenColumns.value);
  next.add(key);
  hiddenColumns.value = next;
}

function showColumn(key: string) {
  const next = new Set(hiddenColumns.value);
  next.delete(key);
  hiddenColumns.value = next;
}

function onHeaderDragStart(key: string, event: DragEvent) {
  if (!canMove(key)) {
    event.preventDefault();
    return;
  }
  draggingKey.value = key;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", key);
  }
}

function onHeaderDragOver(key: string, event: DragEvent) {
  if (!draggingKey.value || !canMove(key)) return;
  event.preventDefault();
  dragOverKey.value = key;
}

function onHeaderDrop(targetKey: string, event: DragEvent) {
  event.preventDefault();
  const sourceKey = draggingKey.value;
  dragOverKey.value = null;
  if (!sourceKey || sourceKey === targetKey) return;
  if (!canMove(sourceKey) || !canMove(targetKey)) return;
  const from = columnOrder.value.indexOf(sourceKey);
  const to = columnOrder.value.indexOf(targetKey);
  if (from < 0 || to < 0) return;
  const copy = [...columnOrder.value];
  [copy[from], copy[to]] = [copy[to], copy[from]];
  columnOrder.value = copy;
}

function onHeaderDragEnd() {
  draggingKey.value = null;
  dragOverKey.value = null;
}

function displayValue(row: Record<string, unknown>, key: string, pageIdx: number): string | number {
  if (key === "no") {
    return (page.value - 1) * limit.value + pageIdx + 1;
  }
  const col = columnMap.value.get(key);
  if (col?.formatter) return col.formatter(row);
  const raw = row[key];
  if (raw === null || raw === undefined) return "";
  return String(raw);
}

async function loadRows() {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      limit: String(limit.value),
      sort_by: sortColumn.value,
      sort_dir: sortDirection.value,
      ...(q.value.trim() ? { q: q.value.trim() } : {}),
    });
    const res = await props.fetcher(`?${params.toString()}`);
    rows.value = res.data as Record<string, unknown>[];
    total.value = Number((res.meta ?? {}).total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : `Unable to load ${props.title}.`);
  } finally {
    loading.value = false;
  }
}

function getTemplateState(): DatatableTemplateState | null {
  return {
    columnOrder: columnOrder.value,
    hiddenColumns: [...hiddenColumns.value],
    sortColumn: sortColumn.value,
    sortDirection: sortDirection.value,
  };
}

function applyTemplateState(t: Partial<DatatableTemplateState> & { columnOrder?: string[]; hiddenColumns?: string[] }) {
  if (Array.isArray(t.columnOrder) && t.columnOrder.length) {
    const allowed = new Set(columnOrder.value);
    const ordered: string[] = [];
    t.columnOrder.forEach((k) => {
      if (allowed.has(k)) ordered.push(k);
    });
    columnOrder.value.forEach((k) => {
      if (!ordered.includes(k)) ordered.push(k);
    });
    columnOrder.value = ordered;
  }
  if (Array.isArray(t.hiddenColumns)) {
    const allowed = new Set(columnOrder.value.filter((k) => canHide(k)));
    hiddenColumns.value = new Set(t.hiddenColumns.filter((k) => allowed.has(k)));
  }
  if (t.sortColumn) sortColumn.value = t.sortColumn;
  if (t.sortDirection === "asc" || t.sortDirection === "desc") sortDirection.value = t.sortDirection;
}

function getExportConfig(): DatatableExportConfig {
  const exportColKeys = visibleColumns.value.map((c) => c.key).filter((k) => k !== "no" && k !== "action");
  const columns = exportColKeys.map((k) => columnMap.value.get(k)?.label ?? k);
  const data = rows.value.map((r) => {
    const out: Record<string, unknown> = {};
    exportColKeys.forEach((k) => {
      const col = columnMap.value.get(k)!;
      out[col.label] = col.formatter ? col.formatter(r) : (r[k] ?? "");
    });
    return out;
  });
  return { columns, data };
}

const datatableRef = ref<DatatableRefApi>({
  getTemplateState,
  applyTemplateState,
  getExportConfig,
});

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
  pageName: props.exportName,
  apiDataPath: "",
  defaultExportColumns: props.columns.map((c) => c.label),
  getFilteredList: () => getExportConfig().data,
  datatableRef,
  searchKeyword: q,
  applyFilters: () => {
    void loadRows();
  },
});

async function exportExcel() {
  try {
    const cfg = getExportConfig();
    if (cfg.data.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet(props.exportName.slice(0, 28));
    ws.addRow(["No", ...cfg.columns]);
    cfg.data.forEach((row, idx) => {
      const values = cfg.columns.map((c) => (row[c] ?? "") as string | number);
      ws.addRow([idx + 1, ...values]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${props.exportName.replace(/[\s/]+/g, "_")}_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
  }
}

function onClickOutside(event: MouseEvent) {
  if (overflowOpen.value && overflowRoot.value && !overflowRoot.value.contains(event.target as Node)) {
    overflowOpen.value = false;
  }
}

let qDebounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (qDebounce) clearTimeout(qDebounce);
  qDebounce = setTimeout(() => {
    qDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

watch(limit, () => {
  page.value = 1;
  void loadRows();
});

watch(page, () => {
  void loadRows();
});

onMounted(() => {
  void loadRows();
  document.addEventListener("click", onClickOutside);
});

onUnmounted(() => {
  if (qDebounce) clearTimeout(qDebounce);
  document.removeEventListener("click", onClickOutside);
});

defineExpose({
  reload: () => loadRows(),
});
</script>

<template>
  <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />

    <header class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
      <h2 class="text-sm font-semibold text-slate-900">{{ props.title }}</h2>
      <div ref="overflowRoot" class="relative">
        <button
          type="button"
          class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
          @click.stop="overflowOpen = !overflowOpen"
        >
          <MoreVertical class="h-4 w-4" />
        </button>
        <div v-if="overflowOpen" class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg" @click.stop>
          <button type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleSaveTemplate()">Save template</button>
          <button type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleLoadTemplate()">Load template</button>
          <button v-if="isGrouped" type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleUngroupList()">Ungroup list</button>
          <button v-else type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleGroupList()">Group list</button>
        </div>
      </div>
    </header>

    <div class="space-y-4 p-4">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2">
          <label class="text-xs font-medium text-slate-600">Display</label>
          <select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm shadow-sm">
            <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <label class="text-xs font-medium text-slate-600">Search</label>
          <div class="relative">
            <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
            <input
              v-model="q"
              type="search"
              placeholder="Filter rows..."
              class="w-52 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm shadow-sm"
              @keyup.enter="page = 1; void loadRows()"
            />
            <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="q = ''; page = 1; void loadRows()">
              <X class="h-3.5 w-3.5" />
            </button>
          </div>
          <div v-if="hiddenList.length > 0" class="relative">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50"
              @click="showColumnsPanel = !showColumnsPanel"
            >
              <Columns3 class="h-3.5 w-3.5" />
              Columns
            </button>
            <div v-if="showColumnsPanel" class="absolute right-0 z-30 mt-1 w-64 rounded-lg border border-slate-200 bg-white p-3 shadow-lg" @click.stop>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Hidden columns</p>
              <ul class="max-h-56 space-y-1 overflow-y-auto">
                <li v-for="col in hiddenList" :key="col.key" class="flex items-center justify-between gap-2 rounded px-1 py-0.5 hover:bg-slate-50">
                  <span class="ml-1 flex-1 text-xs text-slate-700">{{ col.label }}</span>
                  <button type="button" class="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600 hover:bg-slate-50" @click="showColumn(col.key)">Show</button>
                </li>
              </ul>
              <div class="mt-2 flex gap-2">
                <button
                  type="button"
                  class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                  :disabled="hiddenList.length === 0"
                  @click="hiddenColumns = new Set()"
                >
                  Show all
                </button>
                <button type="button" class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50" @click="showColumnsPanel = false">Close</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-lg border border-slate-200">
        <div :class="hasScrollableBody ? 'max-h-[420px] overflow-auto' : 'overflow-x-auto'">
          <table class="w-full text-sm">
            <thead class="sticky top-0 bg-slate-50">
              <tr class="border-b border-slate-200 text-left">
                <th
                  v-for="col in visibleColumns"
                  :key="col.key"
                  class="px-3 py-2 text-xs font-semibold uppercase text-slate-600"
                  :class="[
                    col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left',
                    dragOverKey === col.key && draggingKey !== col.key ? 'bg-slate-100' : '',
                  ]"
                  @dragover="onHeaderDragOver(col.key, $event)"
                  @drop="onHeaderDrop(col.key, $event)"
                >
                  <div :class="['inline-flex items-center gap-1.5', col.align === 'right' ? 'justify-end w-full' : '']">
                    <button
                      type="button"
                      class="rounded p-0.5 text-slate-400"
                      :class="canMove(col.key) ? 'cursor-grab hover:bg-slate-100 hover:text-slate-700 active:cursor-grabbing' : 'cursor-not-allowed opacity-50'"
                      :draggable="canMove(col.key)"
                      :disabled="!canMove(col.key)"
                      :aria-label="`Move ${col.label} column`"
                      @dragstart="onHeaderDragStart(col.key, $event)"
                      @dragend="onHeaderDragEnd"
                    >
                      <GripVertical class="h-3.5 w-3.5" />
                    </button>
                    <button
                      v-if="col.sortable"
                      type="button"
                      class="inline-flex items-center gap-1 text-slate-600 hover:text-slate-900"
                      @click="toggleSort(col.key)"
                      @contextmenu.prevent="hideColumn(col.key)"
                    >
                      {{ col.label }}
                      <span v-if="sortColumn === col.key" class="text-slate-900">{{ sortDirection === "asc" ? "↑" : "↓" }}</span>
                    </button>
                    <span v-else @contextmenu.prevent="hideColumn(col.key)">{{ col.label }}</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td :colspan="Math.max(visibleColumns.length, 1)" class="px-3 py-6 text-center text-sm text-slate-500">Loading…</td>
              </tr>
              <tr v-else-if="rows.length === 0">
                <td :colspan="Math.max(visibleColumns.length, 1)" class="px-3 py-8 text-center text-sm text-slate-500">No records found.</td>
              </tr>
              <tr
                v-for="(row, idx) in rows"
                v-else
                :key="props.rowKey(row, idx)"
                class="border-b border-slate-100 last:border-b-0 hover:bg-slate-50"
              >
                <td
                  v-for="col in visibleColumns"
                  :key="col.key"
                  class="px-3 py-2 tabular-nums"
                  :class="col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'"
                >
                  <template v-if="col.key === 'action' && props.editKey">
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 rounded p-1 text-slate-500 hover:bg-slate-100"
                      title="Edit"
                      @click="emit('edit', row[props.editKey] as string | number)"
                    >
                      <Pencil class="h-3.5 w-3.5" />
                    </button>
                  </template>
                  <template v-else>{{ displayValue(row, col.key, idx) }}</template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 text-sm">
        <span class="text-slate-500">
          Showing
          {{ total === 0 ? 0 : (page - 1) * limit + 1 }}-{{ Math.min(page * limit, total) }}
          of {{ total }}
        </span>
        <div class="flex flex-wrap items-center gap-2">
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-slate-50 disabled:opacity-50"
              :disabled="page <= 1 || total === 0"
              @click="page--"
            >
              Previous
            </button>
            <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium">{{ page }} / {{ totalPages }}</span>
            <button
              type="button"
              class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-slate-50 disabled:opacity-50"
              :disabled="page >= totalPages || total === 0"
              @click="page++"
            >
              Next
            </button>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
              @click="handleDownloadPDF"
            >
              <Download class="h-3.5 w-3.5" />
              PDF
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
              @click="handleDownloadCSV"
            >
              <FileDown class="h-3.5 w-3.5" />
              CSV
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
              @click="exportExcel"
            >
              <FileSpreadsheet class="h-3.5 w-3.5" />
              Excell
            </button>
          </div>
        </div>
      </div>

    </div>
  </article>
</template>
