<script setup lang="ts">
import { computed, ref } from "vue";
import { ArrowDownToLine, ChevronUp, ChevronDown, Columns3, Eye, Pencil } from "lucide-vue-next";
import type { DatatableExportConfig, DatatableTemplateState } from "@/composables/useDatatableFeatures";
import type { PettyCashApplicationListRow } from "@/types";
import { useToast } from "@/composables/useToast";
import { getPettyCashApplication } from "@/api/cms";
import { downloadRequestPettyCashPdf } from "@/composables/usePettyCashFormPdf";

type ColumnKey =
  | "no"
  | "pmsApplicationNo"
  | "pmsRequestBy"
  | "pmsRequestDate"
  | "pmsTotalAmt"
  | "rejectAmt"
  | "cancelAmt"
  | "paidAmount"
  | "pmsStatus"
  | "action";

type ColumnDef = {
  key: ColumnKey;
  label: string;
  sortable?: boolean;
  sortKey?: string;
  hideable?: boolean;
  align?: "right";
};

const props = defineProps<{
  rows: PettyCashApplicationListRow[];
  grouped: boolean;
  sortBy: string;
  sortDir: "asc" | "desc";
}>();

const emit = defineEmits<{
  (e: "sort", sortKey: string): void;
}>();

const toast = useToast();

const columns = ref<ColumnDef[]>([
  { key: "no", label: "No" },
  { key: "pmsApplicationNo", label: "Application No", sortable: true, sortKey: "pms_application_no", hideable: true },
  { key: "pmsRequestBy", label: "Request By", sortable: true, sortKey: "pms_request_by", hideable: true },
  { key: "pmsRequestDate", label: "Application Date", sortable: true, sortKey: "pms_request_date", hideable: true },
  { key: "pmsTotalAmt", label: "Total Amount", sortable: true, sortKey: "pms_total_amt", hideable: true, align: "right" },
  { key: "rejectAmt", label: "Reject Amount", sortable: true, sortKey: "reject_amt", hideable: true, align: "right" },
  { key: "cancelAmt", label: "Cancel Amount", sortable: true, sortKey: "cancel_amt", hideable: true, align: "right" },
  { key: "paidAmount", label: "Paid Amount", sortable: true, sortKey: "paid_amount", hideable: true, align: "right" },
  { key: "pmsStatus", label: "Status", sortable: true, sortKey: "pms_status", hideable: true },
  { key: "action", label: "Action" },
]);

const hiddenKeys = ref<Set<ColumnKey>>(new Set());
const showColumnsPanel = ref(false);
const draggingKey = ref<ColumnKey | null>(null);

const hiddenColumns = computed(() => columns.value.filter((c) => hiddenKeys.value.has(c.key)));
const visibleColumns = computed(() => columns.value.filter((c) => !hiddenKeys.value.has(c.key)));

const displayedRows = computed(() => props.rows);
const hasScrollableBody = computed(() => props.rows.length > 10);

const groupBreakGlobal = computed(() => {
  const set = new Set<number>();
  if (!props.grouped) return set;
  let prev = "";
  displayedRows.value.forEach((row, idx) => {
    const v = row.pmsStatus || "";
    if (idx === 0 || v !== prev) {
      set.add(idx);
      prev = v;
    }
  });
  return set;
});

function showGroupBar(pageRowIndex: number): boolean {
  if (!props.grouped) return false;
  return groupBreakGlobal.value.has(pageRowIndex);
}

function formatMoney(v: number | null | undefined): string {
  if (v == null || Number.isNaN(Number(v))) return "-";
  return new Intl.NumberFormat("en-MY", {
    style: "currency",
    currency: "MYR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(v));
}

function valueForKey(row: PettyCashApplicationListRow, key: ColumnKey): string | number {
  if (key === "no") return row.index;
  if (key === "pmsApplicationNo") return row.pmsApplicationNo ?? "";
  if (key === "pmsRequestBy") return row.pmsRequestBy ?? "";
  if (key === "pmsRequestDate") return row.pmsRequestDate ?? "";
  if (key === "pmsTotalAmt") return formatMoney(row.pmsTotalAmt);
  if (key === "rejectAmt") return formatMoney(row.rejectAmt);
  if (key === "cancelAmt") return formatMoney(row.cancelAmt);
  if (key === "paidAmount") return formatMoney(row.paidAmount);
  if (key === "pmsStatus") return row.pmsStatus ?? "";
  return "";
}

function canEdit(row: PettyCashApplicationListRow): boolean {
  return row.pmsStatus === "APPLY" || row.editable === "Y";
}

function toggleSort(key: ColumnKey) {
  const col = columns.value.find((c) => c.key === key);
  if (!col?.sortable || !col.sortKey) return;
  emit("sort", col.sortKey);
}

function moveColumn(key: ColumnKey, delta: number) {
  const idx = columns.value.findIndex((c) => c.key === key);
  if (idx < 0) return;
  const next = idx + delta;
  if (next < 0 || next >= columns.value.length) return;
  const current = columns.value[idx];
  const target = columns.value[next];
  if (current.key === "no" || current.key === "action" || target.key === "no" || target.key === "action") return;
  [columns.value[idx], columns.value[next]] = [columns.value[next], columns.value[idx]];
}

function hideColumn(key: ColumnKey) {
  const col = columns.value.find((c) => c.key === key);
  if (!col?.hideable) return;
  const next = new Set(hiddenKeys.value);
  next.add(key);
  hiddenKeys.value = next;
}

function showColumn(key: ColumnKey) {
  const next = new Set(hiddenKeys.value);
  next.delete(key);
  hiddenKeys.value = next;
}

function canDragOrHide(key: ColumnKey): boolean {
  return !!columns.value.find((c) => c.key === key)?.hideable;
}

function onHeaderDragStart(key: ColumnKey, event: DragEvent) {
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

function onHeaderDrop(targetKey: ColumnKey, event: DragEvent) {
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

const downloadingId = ref<number | null>(null);

function stubDownload(label: string) {
  toast.info("Download not migrated", `${label} is not available in this build yet.`);
}

async function onRequestPettyCashDownload(row: PettyCashApplicationListRow) {
  if (downloadingId.value !== null) return;
  downloadingId.value = row.pmsId;
  try {
    const res = await getPettyCashApplication(row.pmsId);
    if (res.data) {
      await downloadRequestPettyCashPdf(res.data);
    } else {
      toast.error("Download failed", "Could not load application data.");
    }
  } catch {
    toast.error("Download failed", "An error occurred while generating the PDF.");
  } finally {
    downloadingId.value = null;
  }
}

function onPortalFormDownload(row: PettyCashApplicationListRow) {
  if (row.pmsStatus === "DRAFT") return;
  stubDownload("Borang Permohonan Portal");
}

function getTemplateState(): DatatableTemplateState | null {
  return {
    columnOrder: columns.value.map((c) => c.key),
    hiddenColumns: [...hiddenKeys.value],
    sortColumn: props.sortBy,
    sortDirection: props.sortDir,
  };
}

function applyTemplateState(t: Partial<DatatableTemplateState> & { columnOrder?: string[]; hiddenColumns?: string[] }) {
  if (Array.isArray(t.columnOrder) && t.columnOrder.length) {
    const map = new Map(columns.value.map((c) => [c.key, c] as const));
    const ordered: ColumnDef[] = [];
    t.columnOrder.forEach((k) => {
      const c = map.get(k as ColumnKey);
      if (c) ordered.push(c);
    });
    columns.value.forEach((c) => {
      if (!ordered.some((x) => x.key === c.key)) ordered.push(c);
    });
    columns.value = ordered;
  }

  if (Array.isArray(t.hiddenColumns)) {
    const allowed = new Set(columns.value.filter((c) => c.hideable).map((c) => c.key));
    hiddenKeys.value = new Set(t.hiddenColumns.filter((k) => allowed.has(k as ColumnKey)) as ColumnKey[]);
  }
}

function getExportConfig(): DatatableExportConfig | null {
  const exportColKeys = visibleColumns.value.map((c) => c.key).filter((k) => k !== "no" && k !== "action");

  const keyToLabel: Record<string, string> = {
    pmsApplicationNo: "Application No",
    pmsRequestBy: "Request By",
    pmsRequestDate: "Application Date",
    pmsTotalAmt: "Total Amount",
    rejectAmt: "Reject Amount",
    cancelAmt: "Cancel Amount",
    paidAmount: "Paid Amount",
    pmsStatus: "Status",
  };

  const keyToValue: Record<string, (r: PettyCashApplicationListRow) => string> = {
    pmsApplicationNo: (r) => String(r.pmsApplicationNo ?? ""),
    pmsRequestBy: (r) => String(r.pmsRequestBy ?? ""),
    pmsRequestDate: (r) => r.pmsRequestDate ?? "",
    pmsTotalAmt: (r) => formatMoney(r.pmsTotalAmt),
    rejectAmt: (r) => formatMoney(r.rejectAmt),
    cancelAmt: (r) => formatMoney(r.cancelAmt),
    paidAmount: (r) => formatMoney(r.paidAmount),
    pmsStatus: (r) => r.pmsStatus ?? "",
  };

  const columnsOut = exportColKeys.map((k) => keyToLabel[k] ?? k);
  const data = displayedRows.value.map((r) => {
    const row: Record<string, unknown> = {};
    exportColKeys.forEach((k) => {
      const label = keyToLabel[k] ?? k;
      row[label] = keyToValue[k]?.(r) ?? "";
    });
    return row;
  });

  return { columns: columnsOut, data };
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
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Order &amp; visibility</p>
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
            <button type="button" class="rounded border border-slate-200 px-2 py-1 text-[11px] text-slate-600 hover:bg-slate-50" @click="hiddenKeys = new Set()">
              Show all
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200">
      <div :class="hasScrollableBody ? 'max-h-[420px] overflow-y-auto' : ''">
        <table class="w-full min-w-[1020px] text-sm">
          <thead class="sticky top-0 bg-slate-50">
            <tr class="border-b border-slate-200 text-left">
              <th
                v-for="col in visibleColumns"
                :key="col.key"
                class="px-3 py-2 text-xs font-semibold uppercase text-slate-600"
                :class="col.align === 'right' ? 'text-right' : ''"
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
                  <span v-if="col.sortKey && sortBy === col.sortKey">{{ sortDir === "asc" ? "↑" : "↓" }}</span>
                </button>
                <span v-else @contextmenu.prevent>{{ col.label }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(row, idx) in displayedRows" :key="row.pmsId">
              <tr v-if="showGroupBar(idx)" class="bg-slate-100/80">
                <td :colspan="visibleColumns.length" class="px-3 py-1 text-xs font-semibold text-slate-600">Status {{ row.pmsStatus }}</td>
              </tr>
              <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td
                  v-for="col in visibleColumns"
                  :key="col.key"
                  class="px-3 py-2"
                  :class="col.align === 'right' ? 'text-right tabular-nums' : ''"
                >
                  <template v-if="col.key === 'action'">
                    <div class="flex flex-wrap items-center gap-0.5">
                      <a :href="row.urlView" class="rounded p-1 text-slate-600 hover:bg-slate-100" title="View">
                        <Eye class="h-3.5 w-3.5" />
                      </a>
                      <a
                        :href="row.urlEdit"
                        class="rounded p-1"
                        :class="canEdit(row) ? 'text-slate-600 hover:bg-slate-100' : 'pointer-events-none text-slate-300'"
                        title="Edit"
                      >
                        <Pencil class="h-3.5 w-3.5" />
                      </a>
                      <button type="button" class="rounded p-1 text-slate-600 hover:bg-slate-100" title="Download Baucer Panjar" @click="stubDownload('Baucer Panjar')">
                        <ArrowDownToLine class="h-3.5 w-3.5" />
                      </button>
                      <button
                        type="button"
                        class="rounded p-1"
                        :class="downloadingId === row.pmsId ? 'cursor-wait text-slate-400' : 'text-slate-600 hover:bg-slate-100'"
                        :disabled="downloadingId === row.pmsId"
                        title="Download Request Petty Cash"
                        @click="onRequestPettyCashDownload(row)"
                      >
                        <ArrowDownToLine class="h-3.5 w-3.5" />
                      </button>
                      <button
                        type="button"
                        class="rounded p-1"
                        :class="row.pmsStatus !== 'DRAFT' ? 'text-slate-600 hover:bg-slate-100' : 'cursor-not-allowed text-slate-300'"
                        :disabled="row.pmsStatus === 'DRAFT'"
                        title="Download Borang Permohonan dari Portal"
                        @click="onPortalFormDownload(row)"
                      >
                        <ArrowDownToLine class="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </template>
                  <template v-else>{{ valueForKey(row, col.key) }}</template>
                </td>
              </tr>
            </template>

            <tr v-if="displayedRows.length === 0">
              <td :colspan="Math.max(visibleColumns.length, 1)" class="px-3 py-8 text-center text-sm text-slate-500">No records found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
