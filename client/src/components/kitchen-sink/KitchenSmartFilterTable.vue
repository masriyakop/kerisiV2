<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Columns3, GripVertical } from "lucide-vue-next";
import type { DatatableExportConfig, DatatableTemplateState } from "@/composables/useDatatableFeatures";

export type SmartFilterRow = {
  no: number;
  Level: string;
  "Budget Code": string;
  Description: string;
  Status: string;
};

const ALLOWED_FIELDS = ["no", "Level", "Budget Code", "Description", "Status"] as const;

const props = defineProps<{
  data: SmartFilterRow[];
  pageSize: number;
  grouped: boolean;
}>();

const columnOrder = ref<string[]>([...ALLOWED_FIELDS]);
const hidden = ref(new Set<string>());
const sortColumn = ref<string | null>(null);
const sortDirection = ref<"asc" | "desc">("asc");
const showColumnsPanel = ref(false);
const currentPage = ref(1);
const draggingColumnKey = ref<string | null>(null);
const dragOverColumnKey = ref<string | null>(null);

function normalizeColumnOrder(order: string[]) {
  const allowed = new Set<string>(ALLOWED_FIELDS);
  const filtered = order.filter((k) => allowed.has(k));
  for (const k of ALLOWED_FIELDS) {
    if (!filtered.includes(k)) filtered.push(k);
  }
  return filtered;
}

function hideColumn(key: string) {
  if (key === "no" || hidden.value.has(key)) return;
  const next = new Set(hidden.value);
  next.add(key);
  hidden.value = next;
}

function unhideColumn(key: string) {
  if (!hidden.value.has(key)) return;
  const next = new Set(hidden.value);
  next.delete(key);
  hidden.value = next;
  if (next.size === 0) showColumnsPanel.value = false;
}

const visibleColumns = computed(() => columnOrder.value.filter((k) => !hidden.value.has(k)));
const hiddenColumns = computed(() => columnOrder.value.filter((k) => hidden.value.has(k)));

const sortedData = computed(() => {
  const rows = [...props.data];
  const col = sortColumn.value;
  if (!col) return rows;
  const dir = sortDirection.value === "asc" ? 1 : -1;
  return rows.sort((a, b) => {
    const va = a[col as keyof SmartFilterRow];
    const vb = b[col as keyof SmartFilterRow];
    const sa = va == null ? "" : String(va);
    const sb = vb == null ? "" : String(vb);
    return sa.localeCompare(sb, undefined, { numeric: true }) * dir;
  });
});

const pageCount = computed(() => Math.max(1, Math.ceil(sortedData.value.length / props.pageSize)));

watch(
  () => [sortedData.value.length, props.pageSize] as const,
  () => {
    if (currentPage.value > pageCount.value) currentPage.value = pageCount.value;
    if (currentPage.value < 1) currentPage.value = 1;
  },
);

watch(
  () => props.data.length,
  () => {
    currentPage.value = 1;
  },
);

const pagedRows = computed(() => {
  const start = (currentPage.value - 1) * props.pageSize;
  return sortedData.value.slice(start, start + props.pageSize);
});

const globalStartIndex = computed(() => (currentPage.value - 1) * props.pageSize);

function headerLabel(key: string) {
  const map: Record<string, string> = {
    no: "No.",
    Level: "Level",
    "Budget Code": "Budget Code",
    Description: "Description",
    Status: "Status",
  };
  return map[key] ?? key;
}

function toggleSort(key: string) {
  if (key === "no") return;
  if (sortColumn.value === key) sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
  else {
    sortColumn.value = key;
    sortDirection.value = "asc";
  }
}

function canDragColumn(key: string): boolean {
  const normalized = key.trim().toLowerCase();
  return normalized !== "no" && normalized !== "actions" && normalized !== "action";
}

function reorderVisibleColumns(dragKey: string, targetKey: string) {
  const visible = [...visibleColumns.value];
  const from = visible.indexOf(dragKey);
  const to = visible.indexOf(targetKey);
  if (from < 0 || to < 0 || from === to) return;
  const [moved] = visible.splice(from, 1);
  visible.splice(to, 0, moved);

  const nextOrder = [...columnOrder.value];
  let visibleCursor = 0;
  for (let i = 0; i < nextOrder.length; i += 1) {
    if (!hidden.value.has(nextOrder[i])) {
      nextOrder[i] = visible[visibleCursor];
      visibleCursor += 1;
    }
  }
  columnOrder.value = nextOrder;
}

function onColumnDragStart(key: string, event: DragEvent) {
  if (!canDragColumn(key)) {
    event.preventDefault();
    return;
  }
  draggingColumnKey.value = key;
  dragOverColumnKey.value = key;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", key);
  }
}

function onColumnDragOver(targetKey: string, event: DragEvent) {
  event.preventDefault();
  if (!draggingColumnKey.value || draggingColumnKey.value === targetKey) return;
  dragOverColumnKey.value = targetKey;
}

function onColumnDrop(targetKey: string, event: DragEvent) {
  event.preventDefault();
  const dragKey = draggingColumnKey.value;
  if (!dragKey || dragKey === targetKey) return;
  reorderVisibleColumns(dragKey, targetKey);
  dragOverColumnKey.value = targetKey;
}

function onColumnDragEnd() {
  draggingColumnKey.value = null;
  dragOverColumnKey.value = null;
}

const groupBreakGlobal = computed(() => {
  const set = new Set<number>();
  if (!props.grouped) return set;
  let prev = "";
  sortedData.value.forEach((row, idx) => {
    const level = String(row.Level ?? "");
    if (idx === 0 || level !== prev) {
      set.add(idx);
      prev = level;
    }
  });
  return set;
});

function showGroupBarForRow(pageRowIndex: number) {
  if (!props.grouped) return false;
  const g = globalStartIndex.value + pageRowIndex;
  return groupBreakGlobal.value.has(g);
}

function getTemplateState(): DatatableTemplateState | null {
  return {
    columnOrder: [...columnOrder.value],
    hiddenColumns: [...hidden.value],
    sortColumn: sortColumn.value,
    sortDirection: sortDirection.value,
  };
}

function applyTemplateState(t: Partial<DatatableTemplateState> & { columnOrder?: string[]; hiddenColumns?: string[] }) {
  if (t.columnOrder?.length) columnOrder.value = normalizeColumnOrder(t.columnOrder);
  if (t.hiddenColumns) hidden.value = new Set(t.hiddenColumns.filter((k) => k !== "no"));
  if (t.sortColumn !== undefined) sortColumn.value = t.sortColumn;
  if (t.sortDirection === "asc" || t.sortDirection === "desc") sortDirection.value = t.sortDirection;
}

function getExportConfig(): DatatableExportConfig | null {
  const cols = columnOrder.value.filter((k) => !hidden.value.has(k) && k !== "no");
  return {
    columns: cols,
    data: sortedData.value.map((r) => ({ ...r }) as Record<string, unknown>),
  };
}

defineExpose({ getTemplateState, applyTemplateState, getExportConfig });

function cellValue(row: SmartFilterRow, key: string): string {
  const v = row[key as keyof SmartFilterRow];
  return v == null ? "" : String(v);
}
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
          Column
        </button>
        <div
          v-if="showColumnsPanel"
          class="absolute right-0 z-20 mt-1 w-64 rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
          @click.stop
        >
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Hidden columns</p>
          <ul class="max-h-56 space-y-1 overflow-y-auto">
            <li
              v-for="key in hiddenColumns"
              :key="key"
              class="flex items-center justify-between gap-2 rounded border border-transparent px-1 py-0.5 hover:bg-slate-50"
            >
              <span class="ml-1 flex flex-1 items-center gap-2 text-xs text-slate-700">
                {{ headerLabel(key) }}
              </span>
              <button
                type="button"
                class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-50"
                @click="unhideColumn(key)"
              >
                Show
              </button>
            </li>
          </ul>
          <div class="mt-2 flex gap-2">
            <button
              type="button"
              class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
              @click="hiddenColumns.forEach(unhideColumn)"
            >
              Show all
            </button>
            <button
              type="button"
              class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
              @click="showColumnsPanel = false"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200">
      <table class="w-full min-w-[520px] border-collapse text-sm">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50">
            <th
              v-for="key in visibleColumns"
              :key="key"
              class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
              :class="{ 'bg-slate-100': dragOverColumnKey === key && draggingColumnKey !== key }"
              @dragover="onColumnDragOver(key, $event)"
              @drop="onColumnDrop(key, $event)"
            >
              <div class="inline-flex items-center gap-1.5">
                <button
                  type="button"
                  class="rounded p-0.5 text-slate-400"
                  :class="
                    canDragColumn(key)
                      ? 'cursor-grab hover:bg-slate-100 hover:text-slate-700 active:cursor-grabbing'
                      : 'cursor-not-allowed opacity-50'
                  "
                  :draggable="canDragColumn(key)"
                  :disabled="!canDragColumn(key)"
                  :aria-label="`Move ${headerLabel(key)} column`"
                  @dragstart="onColumnDragStart(key, $event)"
                  @dragend="onColumnDragEnd"
                >
                  <GripVertical class="h-3.5 w-3.5" />
                </button>
                <button
                  v-if="key !== 'no'"
                  type="button"
                  class="inline-flex items-center gap-1 hover:text-slate-900"
                  @click="toggleSort(key)"
                  @contextmenu.prevent="hideColumn(key)"
                >
                  {{ headerLabel(key) }}
                  <span v-if="sortColumn === key" class="text-slate-900">{{ sortDirection === "asc" ? "↑" : "↓" }}</span>
                </button>
                <span v-else>{{ headerLabel(key) }}</span>
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(row, idx) in pagedRows" :key="`${row.no}-${idx}`">
            <tr v-if="showGroupBarForRow(idx)" class="bg-slate-100/80">
              <td :colspan="visibleColumns.length" class="px-3 py-1 text-xs font-semibold text-slate-600">
                Level {{ row.Level }}
              </td>
            </tr>
            <tr class="border-b border-slate-100 transition-colors hover:bg-slate-50/80">
              <td v-for="col in visibleColumns" :key="col" class="px-3 py-2 text-slate-800">
                <template v-if="col === 'Status'">
                  <span
                    :class="
                      row.Status === 'ACTIVE'
                        ? 'font-medium text-emerald-600'
                        : row.Status === 'INACTIVE'
                          ? 'font-medium text-red-600'
                          : ''
                    "
                  >
                    {{ row.Status }}
                  </span>
                </template>
                <template v-else>
                  {{ cellValue(row, col) }}
                </template>
              </td>
            </tr>
          </template>
          <tr v-if="pagedRows.length === 0">
            <td :colspan="Math.max(visibleColumns.length, 1)" class="px-3 py-8 text-center text-sm text-slate-500">
              No records match the current filters.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="sortedData.length > 0" class="flex items-center justify-between text-sm text-slate-500">
      <span>
        Showing {{ (currentPage - 1) * props.pageSize + 1 }}-{{ Math.min(currentPage * props.pageSize, sortedData.length) }} of
        {{ sortedData.length }}
      </span>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="currentPage <= 1"
          @click="currentPage--"
        >
          Previous
        </button>
        <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700">{{ currentPage }} / {{ pageCount }}</span>
        <button
          type="button"
          class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="currentPage >= pageCount"
          @click="currentPage++"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>
