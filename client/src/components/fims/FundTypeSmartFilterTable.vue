<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { ChevronUp, ChevronDown, Columns3 } from "lucide-vue-next";
import type { DatatableExportConfig, DatatableTemplateState } from "@/composables/useDatatableFeatures";
import type { FundTypeRow } from "@/types";

type ColumnDef = {
  key: "no" | "fundType" | "descMalay" | "descEnglish" | "typeBasis" | "status" | "action";
  label: string;
  sortable?: boolean;
  hideable?: boolean;
};

const props = defineProps<{
  rows: FundTypeRow[];
  pageSize: number;
  grouped: boolean;
}>();

const emit = defineEmits<{
  (e: "edit", id: number): void;
}>();

const columns = ref<ColumnDef[]>([
  { key: "no", label: "No" },
  { key: "fundType", label: "Fund Type", sortable: true, hideable: true },
  { key: "descMalay", label: "Description (Malay)", sortable: true, hideable: true },
  { key: "descEnglish", label: "Description (English)", sortable: true, hideable: true },
  { key: "typeBasis", label: "Type Basis", sortable: true, hideable: true },
  { key: "status", label: "Status", sortable: true, hideable: true },
  { key: "action", label: "Action" },
]);

const hiddenKeys = ref<Set<string>>(new Set());
const sortColumn = ref<string>("fundType");
const sortDirection = ref<"asc" | "desc">("asc");
const currentPage = ref(1);
const showColumnsPanel = ref(false);
const draggingKey = ref<string | null>(null);

const hiddenColumns = computed(() => columns.value.filter((c) => hiddenKeys.value.has(c.key)));

const visibleColumns = computed(() => columns.value.filter((c) => !hiddenKeys.value.has(c.key)));

const sortedRows = computed(() => {
  const list = [...props.rows];
  const col = sortColumn.value;
  if (!col) return list;

  const dir = sortDirection.value === "asc" ? 1 : -1;
  const valueOf = (r: FundTypeRow): string | number => {
    if (col === "fundType") return r.ftyFundType || "";
    if (col === "descMalay") return r.ftyFundDesc || "";
    if (col === "descEnglish") return r.ftyFundDescEng || "";
    if (col === "typeBasis") return r.ftyBasis || "";
    if (col === "status") return r.ftyStatus || "";
    return r.index;
  };

  return list.sort((a, b) => String(valueOf(a)).localeCompare(String(valueOf(b)), undefined, { numeric: true }) * dir);
});

const totalPages = computed(() => Math.max(1, Math.ceil(sortedRows.value.length / props.pageSize)));

const pagedRows = computed(() => {
  const start = (currentPage.value - 1) * props.pageSize;
  return sortedRows.value.slice(start, start + props.pageSize);
});

const startIndex = computed(() => (currentPage.value - 1) * props.pageSize);

const hasScrollableBody = computed(() => props.rows.length > 10);

watch(
  () => [props.rows.length, props.pageSize],
  () => {
    if (currentPage.value > totalPages.value) currentPage.value = totalPages.value;
    if (currentPage.value < 1) currentPage.value = 1;
  },
);

function valueForKey(row: FundTypeRow, key: string): string | number {
  if (key === "no") return row.index;
  if (key === "fundType") return row.ftyFundType;
  if (key === "descMalay") return row.ftyFundDesc;
  if (key === "descEnglish") return row.ftyFundDescEng ?? "";
  if (key === "typeBasis") return row.ftyBasis;
  if (key === "status") return row.ftyStatus;
  return "";
}

function toggleSort(key: string) {
  const col = columns.value.find((c) => c.key === key);
  if (!col?.sortable) return;
  if (sortColumn.value === key) sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
  else {
    sortColumn.value = key;
    sortDirection.value = "asc";
  }
}

function moveColumn(key: string, delta: number) {
  const idx = columns.value.findIndex((c) => c.key === key);
  if (idx < 0) return;
  const next = idx + delta;
  if (next < 0 || next >= columns.value.length) return;
  const current = columns.value[idx];
  const target = columns.value[next];
  if (current.key === "no" || current.key === "action" || target.key === "no" || target.key === "action") return;
  [columns.value[idx], columns.value[next]] = [columns.value[next], columns.value[idx]];
}

function hideColumn(key: string) {
  const col = columns.value.find((c) => c.key === key);
  if (!col?.hideable) return;
  const next = new Set(hiddenKeys.value);
  next.add(key);
  hiddenKeys.value = next;
}

function showColumn(key: string) {
  const next = new Set(hiddenKeys.value);
  next.delete(key);
  hiddenKeys.value = next;
}

function canDragOrHide(key: string): boolean {
  const col = columns.value.find((c) => c.key === key);
  return !!col?.hideable;
}

function onHeaderDragStart(key: string, event: DragEvent) {
  if (!canDragOrHide(key)) {
    event.preventDefault();
    return;
  }
  draggingKey.value = key;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", key);
  }
}

function onHeaderDrop(targetKey: string, event: DragEvent) {
  event.preventDefault();
  const sourceKey = draggingKey.value;
  if (!sourceKey || sourceKey === targetKey) return;
  const from = columns.value.findIndex((c) => c.key === sourceKey);
  const to = columns.value.findIndex((c) => c.key === targetKey);
  if (from < 0 || to < 0) return;
  const source = columns.value[from];
  const target = columns.value[to];
  if (!source.hideable || !target.hideable) return;
  [columns.value[from], columns.value[to]] = [columns.value[to], columns.value[from]];
}

function onHeaderDragEnd() {
  draggingKey.value = null;
}

const groupBreakGlobal = computed(() => {
  const set = new Set<number>();
  if (!props.grouped) return set;
  let prev = "";
  sortedRows.value.forEach((row, idx) => {
    const v = row.ftyBasis || "";
    if (idx === 0 || v !== prev) {
      set.add(idx);
      prev = v;
    }
  });
  return set;
});

function showGroupBar(pageRowIndex: number) {
  if (!props.grouped) return false;
  return groupBreakGlobal.value.has(startIndex.value + pageRowIndex);
}

function getTemplateState(): DatatableTemplateState | null {
  return {
    columnOrder: columns.value.map((c) => c.key),
    hiddenColumns: [...hiddenKeys.value],
    sortColumn: sortColumn.value,
    sortDirection: sortDirection.value,
  };
}

function applyTemplateState(t: Partial<DatatableTemplateState> & { columnOrder?: string[]; hiddenColumns?: string[] }) {
  if (Array.isArray(t.columnOrder) && t.columnOrder.length) {
    const map = new Map(columns.value.map((c) => [c.key, c] as const));
    const ordered: ColumnDef[] = [];
    t.columnOrder.forEach((k) => {
      const c = map.get(k as ColumnDef["key"]);
      if (c) ordered.push(c);
    });
    columns.value.forEach((c) => {
      if (!ordered.some((x) => x.key === c.key)) ordered.push(c);
    });
    columns.value = ordered;
  }

  if (Array.isArray(t.hiddenColumns)) {
    const allowed = new Set(columns.value.filter((c) => c.hideable).map((c) => c.key));
    hiddenKeys.value = new Set(t.hiddenColumns.filter((k) => allowed.has(k as ColumnDef["key"])));
  }

  if (t.sortColumn) sortColumn.value = t.sortColumn;
  if (t.sortDirection === "asc" || t.sortDirection === "desc") sortDirection.value = t.sortDirection;
}

function getExportConfig(): DatatableExportConfig | null {
  const exportColKeys = visibleColumns.value
    .map((c) => c.key)
    .filter((k) => k !== "no" && k !== "action");

  const keyToLabel: Record<string, string> = {
    fundType: "Fund Type",
    descMalay: "Description (Malay)",
    descEnglish: "Description (English)",
    typeBasis: "Type Basis",
    status: "Status",
  };
  const keyToValue: Record<string, (r: FundTypeRow) => string | number> = {
    fundType: (r) => r.ftyFundType ?? "",
    descMalay: (r) => r.ftyFundDesc ?? "",
    descEnglish: (r) => r.ftyFundDescEng ?? "",
    typeBasis: (r) => r.ftyBasis ?? "",
    status: (r) => r.ftyStatus ?? "",
  };

  const columns = exportColKeys.map((k) => keyToLabel[k] ?? k);
  const data = sortedRows.value.map((r) => {
    const row: Record<string, unknown> = {};
    exportColKeys.forEach((k) => {
      const label = keyToLabel[k] ?? k;
      row[label] = keyToValue[k] ? keyToValue[k](r) : "";
    });
    return row;
  });

  return { columns, data };
}

defineExpose({ getTemplateState, applyTemplateState, getExportConfig });
</script>

<template>
  <div class="space-y-2">
    <div class="flex flex-wrap items-center justify-end gap-2">
      <div v-if="hiddenColumns.length > 0" class="relative">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50"
          @click="showColumnsPanel = !showColumnsPanel"
        >
          <Columns3 class="h-3.5 w-3.5" />
          Columns
        </button>
        <div v-if="showColumnsPanel" class="absolute right-0 z-30 mt-1 w-72 rounded-lg border border-slate-200 bg-white p-3 shadow-lg" @click.stop>
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Order & visibility</p>
          <ul class="max-h-64 space-y-1 overflow-y-auto">
            <li v-for="col in columns" :key="col.key" class="flex items-center gap-1 rounded px-1 py-0.5 hover:bg-slate-50">
              <button
                type="button"
                class="rounded p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 disabled:opacity-30"
                :disabled="columns.findIndex((c) => c.key === col.key) <= 1 || col.key === 'no' || col.key === 'action'"
                @click="moveColumn(col.key, -1)"
              >
                <ChevronUp class="h-3.5 w-3.5" />
              </button>
              <button
                type="button"
                class="rounded p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 disabled:opacity-30"
                :disabled="columns.findIndex((c) => c.key === col.key) >= columns.length - 2 || col.key === 'no' || col.key === 'action'"
                @click="moveColumn(col.key, 1)"
              >
                <ChevronDown class="h-3.5 w-3.5" />
              </button>
              <span class="ml-1 flex-1 text-xs text-slate-700">{{ col.label }}</span>
              <button
                v-if="col.hideable && !hiddenKeys.has(col.key)"
                type="button"
                class="rounded border border-slate-200 px-2 py-0.5 text-[11px] text-slate-600 hover:bg-slate-50"
                @click="hideColumn(col.key)"
              >
                Hide
              </button>
              <button
                v-else-if="col.hideable"
                type="button"
                class="rounded border border-slate-200 px-2 py-0.5 text-[11px] text-slate-600 hover:bg-slate-50"
                @click="showColumn(col.key)"
              >
                Show
              </button>
            </li>
          </ul>

          <div class="mt-2 flex items-center justify-between">
            <span class="text-[11px] text-slate-500">Hidden: {{ hiddenColumns.length }}</span>
            <button
              type="button"
              class="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600 hover:bg-slate-50"
              @click="hiddenKeys = new Set()"
            >
              Show all
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200">
      <div :class="hasScrollableBody ? 'max-h-[420px] overflow-y-auto' : ''">
        <table class="w-full min-w-[980px] text-sm">
          <thead class="sticky top-0 bg-slate-50">
            <tr class="border-b border-slate-200 text-left">
              <th
                v-for="col in visibleColumns"
                :key="col.key"
                class="px-3 py-2 text-xs font-semibold uppercase text-slate-600"
                :draggable="canDragOrHide(col.key)"
                @dragstart="onHeaderDragStart(col.key, $event)"
                @dragover.prevent
                @drop="onHeaderDrop(col.key, $event)"
                @dragend="onHeaderDragEnd"
              >
                <button
                  v-if="col.sortable"
                  type="button"
                  class="inline-flex items-center gap-1"
                  @click="toggleSort(col.key)"
                  @contextmenu.prevent="hideColumn(col.key)"
                >
                  {{ col.label }}
                  <span v-if="sortColumn === col.key">{{ sortDirection === "asc" ? "↑" : "↓" }}</span>
                </button>
                <span v-else @contextmenu.prevent="hideColumn(col.key)">{{ col.label }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(row, idx) in pagedRows" :key="row.ftyFundId">
              <tr v-if="showGroupBar(idx)" class="bg-slate-100/80">
                <td :colspan="visibleColumns.length" class="px-3 py-1 text-xs font-semibold text-slate-600">Type Basis {{ row.ftyBasis }}</td>
              </tr>
              <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td v-for="col in visibleColumns" :key="col.key" class="px-3 py-2">
                  <template v-if="col.key === 'status'">
                    <span :class="row.ftyStatus === 'ACTIVE' ? 'font-medium text-emerald-600' : 'font-medium text-rose-600'">{{ row.ftyStatus }}</span>
                  </template>
                  <template v-else-if="col.key === 'action'">
                    <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="emit('edit', row.ftyFundId)">✎</button>
                  </template>
                  <template v-else>{{ valueForKey(row, col.key) }}</template>
                </td>
              </tr>
            </template>

            <tr v-if="pagedRows.length === 0">
              <td :colspan="Math.max(visibleColumns.length, 1)" class="px-3 py-8 text-center text-sm text-slate-500">No records found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="sortedRows.length > 0" class="flex items-center justify-between text-sm text-slate-500">
      <span>Showing {{ (currentPage - 1) * props.pageSize + 1 }}-{{ Math.min(currentPage * props.pageSize, sortedRows.length) }} of {{ sortedRows.length }}</span>
      <div class="flex items-center gap-2">
        <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-slate-50 disabled:opacity-50" :disabled="currentPage <= 1" @click="currentPage--">Previous</button>
        <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium">{{ currentPage }} / {{ totalPages }}</span>
        <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-slate-50 disabled:opacity-50" :disabled="currentPage >= totalPages" @click="currentPage++">Next</button>
      </div>
    </div>
  </div>
</template>
