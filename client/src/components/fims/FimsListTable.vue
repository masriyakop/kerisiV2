<script setup lang="ts" generic="Row extends Record<string, unknown>">
import { computed, ref } from "vue";
import { ChevronUp, ChevronDown, Columns3 } from "lucide-vue-next";
import type { DatatableExportConfig, DatatableTemplateState } from "@/composables/useDatatableFeatures";

export type FimsColumn<R> = {
  key: string;
  label: string;
  sortable?: boolean;
  sortKey?: string;
  hideable?: boolean;
  align?: "right" | "center";
  value?: (row: R) => string | number;
  exportable?: boolean;
  hiddenByDefault?: boolean;
  freezeLeft?: boolean;
};

const props = defineProps<{
  rows: Row[];
  columns: FimsColumn<Row>[];
  grouped: boolean;
  sortBy: string;
  sortDir: "asc" | "desc";
  rowKey: (r: Row) => string | number;
  groupBy?: (r: Row) => string;
  minWidth?: string;
}>();

const emit = defineEmits<{
  (e: "sort", sortKey: string): void;
}>();

const orderedKeys = ref<string[]>(props.columns.map((c) => c.key));
const hiddenKeys = ref<Set<string>>(
  new Set(props.columns.filter((c) => c.hiddenByDefault).map((c) => c.key))
);
const showColumnsPanel = ref(false);
const draggingKey = ref<string | null>(null);

const columnsMap = computed(() => new Map(props.columns.map((c) => [c.key, c])));
const orderedColumns = computed(() =>
  orderedKeys.value.map((k) => columnsMap.value.get(k)).filter((c): c is FimsColumn<Row> => !!c)
);
const visibleColumns = computed(() => orderedColumns.value.filter((c) => !hiddenKeys.value.has(c.key)));
const hiddenColumns = computed(() => orderedColumns.value.filter((c) => hiddenKeys.value.has(c.key)));
const hasScrollableBody = computed(() => props.rows.length > 10);

const groupBreakSet = computed(() => {
  const set = new Set<number>();
  if (!props.grouped || !props.groupBy) return set;
  let prev = "\u0000";
  props.rows.forEach((r, idx) => {
    const v = props.groupBy!(r);
    if (idx === 0 || v !== prev) {
      set.add(idx);
      prev = v;
    }
  });
  return set;
});

function showGroupBar(idx: number): boolean {
  return props.grouped && groupBreakSet.value.has(idx);
}

function resolveValue(row: Row, col: FimsColumn<Row>): string | number {
  if (col.value) return col.value(row);
  const raw = row[col.key];
  if (raw == null) return "";
  return typeof raw === "number" ? raw : String(raw);
}

function toggleSort(col: FimsColumn<Row>) {
  if (!col.sortable || !col.sortKey) return;
  emit("sort", col.sortKey);
}

function isMoveable(key: string): boolean {
  const col = columnsMap.value.get(key);
  return !!col?.hideable;
}

function moveColumn(key: string, delta: number) {
  const idx = orderedKeys.value.indexOf(key);
  if (idx < 0) return;
  const next = idx + delta;
  if (next < 0 || next >= orderedKeys.value.length) return;
  if (!isMoveable(key) || !isMoveable(orderedKeys.value[next])) return;
  const arr = [...orderedKeys.value];
  [arr[idx], arr[next]] = [arr[next], arr[idx]];
  orderedKeys.value = arr;
}

function hideColumn(key: string) {
  const col = columnsMap.value.get(key);
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

function onHeaderDragStart(key: string, event: DragEvent) {
  if (!isMoveable(key)) {
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
  const source = draggingKey.value;
  if (!source || source === targetKey) return;
  if (!isMoveable(source) || !isMoveable(targetKey)) return;
  const arr = [...orderedKeys.value];
  const from = arr.indexOf(source);
  const to = arr.indexOf(targetKey);
  if (from < 0 || to < 0) return;
  [arr[from], arr[to]] = [arr[to], arr[from]];
  orderedKeys.value = arr;
}

function onHeaderDragEnd() {
  draggingKey.value = null;
}

function getTemplateState(): DatatableTemplateState {
  return {
    columnOrder: [...orderedKeys.value],
    hiddenColumns: [...hiddenKeys.value],
    sortColumn: props.sortBy,
    sortDirection: props.sortDir,
  };
}

function applyTemplateState(t: Partial<DatatableTemplateState> & { columnOrder?: string[]; hiddenColumns?: string[] }) {
  if (Array.isArray(t.columnOrder) && t.columnOrder.length) {
    const known = new Set(props.columns.map((c) => c.key));
    const ordered = t.columnOrder.filter((k) => known.has(k));
    props.columns.forEach((c) => {
      if (!ordered.includes(c.key)) ordered.push(c.key);
    });
    orderedKeys.value = ordered;
  }
  if (Array.isArray(t.hiddenColumns)) {
    const hideable = new Set(props.columns.filter((c) => c.hideable).map((c) => c.key));
    hiddenKeys.value = new Set(t.hiddenColumns.filter((k) => hideable.has(k)));
  }
}

function getExportConfig(): DatatableExportConfig {
  const exportCols = visibleColumns.value.filter((c) => c.key !== "no" && c.key !== "action" && c.exportable !== false);
  const columnLabels = exportCols.map((c) => c.label);
  const data = props.rows.map((r) => {
    const row: Record<string, unknown> = {};
    exportCols.forEach((c) => {
      row[c.label] = resolveValue(r, c);
    });
    return row;
  });
  return { columns: columnLabels, data };
}

defineExpose({ getTemplateState, applyTemplateState, getExportConfig });
</script>

<template>
  <div class="space-y-2">
    <div class="flex flex-wrap items-center justify-end gap-2">
      <div v-if="hiddenColumns.length > 0 || columns.some((c) => c.hideable)" class="relative">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50"
          @click="showColumnsPanel = !showColumnsPanel"
        >
          <Columns3 class="h-3.5 w-3.5" />
          Columns
        </button>
        <div v-if="showColumnsPanel" class="absolute right-0 z-30 mt-1 w-72 rounded-lg border border-slate-200 bg-white p-3 shadow-lg" @click.stop>
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Order &amp; visibility</p>
          <ul class="max-h-64 space-y-1 overflow-y-auto">
            <li v-for="col in orderedColumns" :key="col.key" class="flex items-center gap-1 rounded px-1 py-0.5 hover:bg-slate-50">
              <button
                type="button"
                class="rounded p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 disabled:opacity-30"
                :disabled="!col.hideable"
                @click="moveColumn(col.key, -1)"
              >
                <ChevronUp class="h-3.5 w-3.5" />
              </button>
              <button
                type="button"
                class="rounded p-0.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 disabled:opacity-30"
                :disabled="!col.hideable"
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
            <button type="button" class="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600 hover:bg-slate-50" @click="hiddenKeys = new Set()">
              Show all
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200">
      <div :class="hasScrollableBody ? 'max-h-[420px] overflow-y-auto' : ''">
        <table class="w-full text-sm" :style="minWidth ? { minWidth } : {}">
          <thead class="sticky top-0 bg-slate-50">
            <tr class="border-b border-slate-200 text-left">
              <th
                v-for="col in visibleColumns"
                :key="col.key"
                class="px-3 py-2 text-xs font-semibold uppercase text-slate-600"
                :class="[
                  col.align === 'right' ? 'text-right' : '',
                  col.align === 'center' ? 'text-center' : '',
                  col.freezeLeft ? 'sticky left-0 bg-slate-50 z-10' : '',
                ]"
                :draggable="!!col.hideable"
                @dragstart="onHeaderDragStart(col.key, $event)"
                @dragover.prevent
                @drop="onHeaderDrop(col.key, $event)"
                @dragend="onHeaderDragEnd"
              >
                <button
                  v-if="col.sortable"
                  type="button"
                  class="inline-flex items-center gap-1"
                  @click="toggleSort(col)"
                  @contextmenu.prevent="col.hideable && hideColumn(col.key)"
                >
                  {{ col.label }}
                  <span v-if="col.sortKey && sortBy === col.sortKey">{{ sortDir === "asc" ? "\u2191" : "\u2193" }}</span>
                </button>
                <span v-else @contextmenu.prevent="col.hideable && hideColumn(col.key)">{{ col.label }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(row, idx) in rows" :key="rowKey(row)">
              <tr v-if="showGroupBar(idx) && groupBy" class="bg-slate-100/80">
                <td :colspan="visibleColumns.length" class="px-3 py-1 text-xs font-semibold text-slate-600">{{ groupBy(row) }}</td>
              </tr>
              <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td
                  v-for="col in visibleColumns"
                  :key="col.key"
                  class="px-3 py-2"
                  :class="[
                    col.align === 'right' ? 'text-right tabular-nums' : '',
                    col.align === 'center' ? 'text-center' : '',
                    col.freezeLeft ? 'sticky left-0 bg-white z-10' : '',
                  ]"
                >
                  <template v-if="col.key === 'action'">
                    <slot name="action" :row="row" :column="col" />
                  </template>
                  <template v-else>
                    <slot :name="`cell-${col.key}`" :row="row" :column="col" :value="resolveValue(row, col)">
                      {{ resolveValue(row, col) }}
                    </slot>
                  </template>
                </td>
              </tr>
            </template>

            <tr v-if="rows.length === 0">
              <td :colspan="Math.max(visibleColumns.length, 1)" class="px-3 py-8 text-center text-sm text-slate-500">No records found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
