<script setup lang="ts">
import { computed, ref, watch } from "vue";
import {
  Check,
  Eye,
  Info,
  Bell,
  Pencil,
  Trash2,
  Layers,
  Palette,
  MousePointerClick,
  Tag,
  TextCursorInput,
  ListFilter,
  PanelTop,
  MessageSquare,
  ChevronDown,
  HelpCircle,
  Table2,
  ChevronsLeftRight,
  CheckCircle2,
  XCircle,
  Clock3,
  ListChecks,
  Search,
  ChevronLeft,
  ChevronRight,
  Download,
  FileDown,
  FileSpreadsheet,
  Plus,
  GripVertical,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { useToast } from "@/composables/useToast";

const selectValue = ref("active");
const dropdownStatus = ref("published");
const showMeta = ref(true);
const page = ref(2);
const dropdownOpen = ref(false);
const dialogOpen = ref(false);
const activeTab = ref("content");
const toast = useToast();

const totalItems = 48;
const itemsPerPage = 10;
const totalPages = computed(() => Math.ceil(totalItems / itemsPerPage));

const sectionLinks = [
  { id: "overview", label: "Overview", icon: Layers, color: "text-slate-600" },
  { id: "tokens", label: "Color & Typography", icon: Palette, color: "text-violet-600" },
  { id: "buttons", label: "Buttons", icon: MousePointerClick, color: "text-blue-600" },
  { id: "badges", label: "Badges", icon: Tag, color: "text-emerald-600" },
  { id: "inputs", label: "Inputs", icon: TextCursorInput, color: "text-amber-600" },
  { id: "select", label: "Select", icon: ListFilter, color: "text-cyan-600" },
  { id: "tabs", label: "Tabs", icon: PanelTop, color: "text-pink-600" },
  { id: "toast", label: "Toast", icon: Bell, color: "text-blue-600" },
  { id: "dialog", label: "Dialog", icon: MessageSquare, color: "text-indigo-600" },
  { id: "dropdown", label: "Dropdown", icon: ChevronDown, color: "text-teal-600" },
  { id: "tooltip", label: "Tooltip", icon: HelpCircle, color: "text-orange-600" },
  { id: "table", label: "Table", icon: Table2, color: "text-rose-600" },
  { id: "timeline", label: "Timeline", icon: Clock3, color: "text-sky-600" },
  { id: "steps", label: "Steps", icon: ListChecks, color: "text-indigo-600" },
  { id: "pagination", label: "Pagination", icon: ChevronsLeftRight, color: "text-fuchsia-600" },
];

function nextPage(delta: number) {
  const next = Math.max(1, Math.min(totalPages.value, page.value + delta));
  page.value = next;
}

function demoToast(variant: "success" | "error" | "info") {
  if (variant === "success") {
    toast.success("Changes saved", "Your content has been updated.");
    return;
  }
  if (variant === "error") {
    toast.error("Save failed", "Please retry in a few seconds.");
    return;
  }
  toast.info("Sync in progress", "Background update is running.");
}

const tableSearchQuery = ref("");
const tablePageSize = ref(10);
const tableCurrentPage = ref(1);

type TableDemoRow = {
  title: string;
  author: string;
  status: "Published" | "Draft" | "Pending";
  statusClass: string;
  updated: string;
};

const tableDemoRows = ref<TableDemoRow[]>([
  {
    title: "Welcome Post",
    author: "Admin",
    status: "Published",
    statusClass: "rounded-full bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-white",
    updated: "2h ago",
  },
  {
    title: "SEO Checklist",
    author: "Editor",
    status: "Draft",
    statusClass: "rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700",
    updated: "1d ago",
  },
  {
    title: "Product Launch",
    author: "Manager",
    status: "Pending",
    statusClass: "rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700",
    updated: "3d ago",
  },
  {
    title: "Quarterly Report",
    author: "Admin",
    status: "Published",
    statusClass: "rounded-full bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-white",
    updated: "5h ago",
  },
  {
    title: "Brand Guidelines",
    author: "Editor",
    status: "Draft",
    statusClass: "rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700",
    updated: "6h ago",
  },
  {
    title: "Holiday Notice",
    author: "Admin",
    status: "Published",
    statusClass: "rounded-full bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-white",
    updated: "1d ago",
  },
  {
    title: "API Changelog",
    author: "Editor",
    status: "Pending",
    statusClass: "rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700",
    updated: "2d ago",
  },
  {
    title: "Onboarding Tips",
    author: "Manager",
    status: "Published",
    statusClass: "rounded-full bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-white",
    updated: "2d ago",
  },
  {
    title: "Archive: 2024 News",
    author: "Admin",
    status: "Draft",
    statusClass: "rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700",
    updated: "4d ago",
  },
  {
    title: "Press Kit",
    author: "Editor",
    status: "Published",
    statusClass: "rounded-full bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-white",
    updated: "5d ago",
  },
  {
    title: "Incident Postmortem",
    author: "Manager",
    status: "Draft",
    statusClass: "rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700",
    updated: "1w ago",
  },
  {
    title: "Roadmap Teaser",
    author: "Admin",
    status: "Pending",
    statusClass: "rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700",
    updated: "1w ago",
  },
]);

type TableSortColumn = "no" | "title" | "author" | "status" | "updated";
type KitchenTableColumn = "no" | "title" | "author" | "status" | "updated" | "actions";

const tableSortColumn = ref<TableSortColumn | null>(null);
const tableSortDirection = ref<"asc" | "desc">("asc");
const tableColumnOrder = ref<KitchenTableColumn[]>(["no", "title", "author", "status", "updated", "actions"]);
const tableHiddenColumns = ref(new Set<KitchenTableColumn>());
const tableColumnsPanelOpen = ref(false);
const tableDraggingColumn = ref<KitchenTableColumn | null>(null);
const tableDragOverColumn = ref<KitchenTableColumn | null>(null);

const tableColumnLabels: Record<KitchenTableColumn, string> = {
  no: "No.",
  title: "Title",
  author: "Author",
  status: "Status",
  updated: "Updated",
  actions: "Actions",
};

const tableVisibleColumns = computed(() => tableColumnOrder.value.filter((c) => !tableHiddenColumns.value.has(c)));
const tableHiddenColumnList = computed(() => tableColumnOrder.value.filter((c) => tableHiddenColumns.value.has(c)));

const filteredTableRows = computed(() => {
  const q = tableSearchQuery.value.trim().toLowerCase();
  if (!q) return [...tableDemoRows.value];
  return tableDemoRows.value.filter(
    (row) =>
      row.title.toLowerCase().includes(q) ||
      row.author.toLowerCase().includes(q) ||
      row.status.toLowerCase().includes(q) ||
      row.updated.toLowerCase().includes(q),
  );
});

function tableDemoRowIndex(row: TableDemoRow): number {
  return tableDemoRows.value.indexOf(row);
}

function tableUpdatedRank(s: string): number {
  const m = /^(\d+)(h|d|w)\s+ago$/.exec(s);
  if (!m) return 0;
  const n = Number(m[1]);
  const u = m[2];
  if (u === "h") return n;
  if (u === "d") return n * 100 + 50;
  return n * 1000 + 500;
}

function toggleTableSort(col: TableSortColumn) {
  if (tableSortColumn.value === col) {
    tableSortDirection.value = tableSortDirection.value === "asc" ? "desc" : "asc";
  } else {
    tableSortColumn.value = col;
    tableSortDirection.value = "asc";
  }
  tableCurrentPage.value = 1;
}

function canMoveTableColumn(col: KitchenTableColumn): boolean {
  return col !== "no" && col !== "actions";
}

function canHideTableColumn(col: KitchenTableColumn): boolean {
  return col !== "no" && col !== "actions";
}

function hideTableColumn(col: KitchenTableColumn) {
  if (!canHideTableColumn(col)) return;
  const next = new Set(tableHiddenColumns.value);
  next.add(col);
  tableHiddenColumns.value = next;
}

function showTableColumn(col: KitchenTableColumn) {
  if (!tableHiddenColumns.value.has(col)) return;
  const next = new Set(tableHiddenColumns.value);
  next.delete(col);
  tableHiddenColumns.value = next;
}

function reorderVisibleTableColumns(dragCol: KitchenTableColumn, targetCol: KitchenTableColumn) {
  const visible = [...tableVisibleColumns.value];
  const from = visible.indexOf(dragCol);
  const to = visible.indexOf(targetCol);
  if (from < 0 || to < 0 || from === to) return;
  const [moved] = visible.splice(from, 1);
  visible.splice(to, 0, moved);

  const nextOrder = [...tableColumnOrder.value];
  let cursor = 0;
  for (let i = 0; i < nextOrder.length; i += 1) {
    if (!tableHiddenColumns.value.has(nextOrder[i])) {
      nextOrder[i] = visible[cursor];
      cursor += 1;
    }
  }
  tableColumnOrder.value = nextOrder;
}

function onTableHeaderDragStart(col: KitchenTableColumn, event: DragEvent) {
  if (!canMoveTableColumn(col)) {
    event.preventDefault();
    return;
  }
  tableDraggingColumn.value = col;
  tableDragOverColumn.value = col;
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", col);
  }
}

function onTableHeaderDragOver(targetCol: KitchenTableColumn, event: DragEvent) {
  event.preventDefault();
  if (!tableDraggingColumn.value || tableDraggingColumn.value === targetCol) return;
  tableDragOverColumn.value = targetCol;
}

function onTableHeaderDrop(targetCol: KitchenTableColumn, event: DragEvent) {
  event.preventDefault();
  const dragCol = tableDraggingColumn.value;
  if (!dragCol || dragCol === targetCol) return;
  reorderVisibleTableColumns(dragCol, targetCol);
  tableDragOverColumn.value = targetCol;
}

function onTableHeaderDragEnd() {
  tableDraggingColumn.value = null;
  tableDragOverColumn.value = null;
}

const sortedFilteredTableRows = computed(() => {
  const rows = [...filteredTableRows.value];
  const col = tableSortColumn.value;
  if (!col) return rows;
  const dir = tableSortDirection.value === "asc" ? 1 : -1;
  rows.sort((a, b) => {
    let cmp = 0;
    if (col === "no") {
      cmp = tableDemoRowIndex(a) - tableDemoRowIndex(b);
    } else if (col === "title") {
      cmp = a.title.localeCompare(b.title, undefined, { sensitivity: "base" });
    } else if (col === "author") {
      cmp = a.author.localeCompare(b.author, undefined, { sensitivity: "base" });
    } else if (col === "status") {
      cmp = a.status.localeCompare(b.status, undefined, { sensitivity: "base" });
    } else {
      cmp = tableUpdatedRank(a.updated) - tableUpdatedRank(b.updated);
    }
    return cmp * dir;
  });
  return rows;
});

const tablePageCount = computed(() => Math.max(1, Math.ceil(sortedFilteredTableRows.value.length / tablePageSize.value)));

const pagedTableRows = computed(() => {
  const start = (tableCurrentPage.value - 1) * tablePageSize.value;
  return sortedFilteredTableRows.value.slice(start, start + tablePageSize.value);
});

function tableRowDisplayNo(pageRowIndex: number): number {
  const globalIndex = (tableCurrentPage.value - 1) * tablePageSize.value + pageRowIndex;
  return sortedFilteredTableRows.value.length - globalIndex;
}

watch(
  () => [sortedFilteredTableRows.value.length, tablePageSize.value] as const,
  () => {
    if (tableCurrentPage.value > tablePageCount.value) tableCurrentPage.value = tablePageCount.value;
    if (tableCurrentPage.value < 1) tableCurrentPage.value = 1;
  },
);

watch(tableSearchQuery, () => {
  tableCurrentPage.value = 1;
});

function getTableExportRows() {
  return sortedFilteredTableRows.value.map((row, idx) => ({
    no: idx + 1,
    Title: row.title,
    Author: row.author,
    Status: row.status,
    Updated: row.updated,
  }));
}

async function handleTableDownloadPDF() {
  try {
    const { default: jsPDF } = await import("jspdf");
    const autoTable = (await import("jspdf-autotable")).default;
    const data = getTableExportRows();
    if (data.length === 0) {
      toast.info("No data", "Nothing to export.");
      return;
    }
    const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
    doc.setFontSize(16);
    doc.setFont("helvetica", "bold");
    doc.text("Kitchen Sink Table", 105, 15, { align: "center" });
    autoTable(doc, {
      head: [["No.", "Title", "Author", "Status", "Updated"]],
      body: data.map((row) => [row.no, row.Title, row.Author, row.Status, row.Updated]),
      startY: 22,
      styles: { fontSize: 9 },
      headStyles: { fillColor: [30, 41, 59], textColor: [255, 255, 255], fontStyle: "bold" },
    });
    doc.save(`Kitchen_Sink_Table_${new Date().toISOString().split("T")[0]}.pdf`);
    toast.success("PDF downloaded");
  } catch (e) {
    const err = e as Error;
    toast.error("Export failed", err?.message || "PDF export failed.");
  }
}

function handleTableDownloadCSV() {
  try {
    const data = getTableExportRows();
    if (data.length === 0) {
      toast.info("No data", "Nothing to export.");
      return;
    }
    const escape = (value: unknown) => {
      if (value == null) return "";
      const str = String(value);
      return str.includes(",") || str.includes('"') ? `"${str.replace(/"/g, '""')}"` : str;
    };
    const headers = ["No.", "Title", "Author", "Status", "Updated"];
    let csv = headers.map(escape).join(",") + "\n";
    data.forEach((row) => {
      csv += [row.no, row.Title, row.Author, row.Status, row.Updated].map(escape).join(",") + "\n";
    });
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `Kitchen_Sink_Table_${new Date().toISOString().split("T")[0]}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
    toast.success("CSV downloaded");
  } catch (e) {
    const err = e as Error;
    toast.error("Export failed", err?.message || "CSV export failed.");
  }
}

async function handleTableDownloadExcel() {
  try {
    const ExcelJS = await import("exceljs");
    const data = getTableExportRows();
    if (data.length === 0) {
      toast.info("No data", "Nothing to export.");
      return;
    }
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Kitchen Sink Table");
    sheet.addRow(["No.", "Title", "Author", "Status", "Updated"]);
    data.forEach((row) => sheet.addRow([row.no, row.Title, row.Author, row.Status, row.Updated]));
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `Kitchen_Sink_Table_${new Date().toISOString().split("T")[0]}.xlsx`;
    a.click();
    URL.revokeObjectURL(a.href);
    toast.success("Excel downloaded");
  } catch (e) {
    const err = e as Error;
    toast.error("Export failed", err?.message || "Excel export failed.");
  }
}

function handleTableAdd() {
  toast.info("Add action", "Dummy button. In real implementation, open modal or navigate to add page.");
}
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <!-- ───── Slim Title Bar ───── -->
      <div class="flex items-center justify-between">
        <h1 class="page-title">UI Standard Reference</h1>
        <div class="flex items-center gap-2">
          <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ sectionLinks.length }} Components</span>
        </div>
      </div>

      <!-- ───── Quick Jump Nav ───── -->
      <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Quick Jump</p>
        <div class="grid gap-1.5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
          <a
            v-for="link in sectionLinks"
            :key="link.id"
            :href="`#${link.id}`"
            class="group flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 transition-all hover:bg-slate-50 hover:text-slate-900"
          >
            <component :is="link.icon" class="h-4 w-4 text-slate-400 transition-colors group-hover:text-slate-600" />
            {{ link.label }}
          </a>
        </div>
      </div>

      <section class="space-y-4">
        <!-- ═══════ OVERVIEW ═══════ -->
        <article id="overview" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Layers class="h-4 w-4 text-slate-600" />
            <h2 class="text-sm font-semibold text-slate-900">Overview</h2>
          </div>
          <div class="p-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-5">
                <p class="mb-3 text-sm font-semibold text-slate-900">Usage rules</p>
                <ul class="space-y-2 text-sm text-slate-600">
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">1</span> Use existing <code class="rounded bg-slate-200 px-1 py-0.5 text-xs">components/ui</code> primitives first.</li>
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">2</span> Keep labels explicit and action-oriented.</li>
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">3</span> Always include loading, empty, and error states.</li>
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">4</span> Validate accessibility before shipping.</li>
                </ul>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-5">
                <p class="mb-3 text-sm font-semibold text-slate-900">Naming conventions</p>
                <ul class="space-y-2 text-sm text-slate-600">
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">1</span> Use semantic labels: <code class="rounded bg-slate-200 px-1 py-0.5 text-xs">Save changes</code>, not <code class="rounded bg-slate-200 px-1 py-0.5 text-xs">Submit</code>.</li>
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">2</span> Use consistent status terms: Draft, Published, Archived.</li>
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">3</span> Keep page sections predictable: Header, Filters, Content, Actions.</li>
                </ul>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ COLOR & TYPOGRAPHY ═══════ -->
        <article id="tokens" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Palette class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Color & Typography</h2>
          </div>
          <div class="space-y-4 p-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div class="group overflow-hidden rounded-lg border border-slate-200 transition-shadow hover:shadow-md">
                <div class="h-20 bg-slate-900" />
                <div class="p-3">
                  <p class="text-sm font-semibold">primary</p>
                  <p class="font-mono text-xs text-slate-400">slate-900</p>
                </div>
              </div>
              <div class="group overflow-hidden rounded-lg border border-slate-200 transition-shadow hover:shadow-md">
                <div class="h-20 bg-slate-200" />
                <div class="p-3">
                  <p class="text-sm font-semibold">secondary</p>
                  <p class="font-mono text-xs text-slate-400">slate-200</p>
                </div>
              </div>
              <div class="group overflow-hidden rounded-lg border border-slate-200 transition-shadow hover:shadow-md">
                <div class="h-20 bg-slate-100" />
                <div class="p-3">
                  <p class="text-sm font-semibold">accent</p>
                  <p class="font-mono text-xs text-slate-400">slate-100</p>
                </div>
              </div>
              <div class="group overflow-hidden rounded-lg border border-slate-200 transition-shadow hover:shadow-md">
                <div class="h-20 bg-red-600" />
                <div class="p-3">
                  <p class="text-sm font-semibold">destructive</p>
                  <p class="font-mono text-xs text-slate-400">red-600</p>
                </div>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-lg border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4">
                <p class="text-3xl font-semibold tracking-tight">Heading / 30-36px</p>
                <p class="mt-2 text-slate-500">For page titles and section hierarchy.</p>
                <div class="mt-3 flex gap-2">
                  <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">font-bold</span>
                  <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">tracking-tight</span>
                </div>
              </div>
              <div class="rounded-lg border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4">
                <p class="text-base">Body / 14-16px</p>
                <p class="mt-2 text-sm text-slate-500">For labels, descriptions, and table values.</p>
                <div class="mt-3 flex gap-2">
                  <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">font-normal</span>
                  <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">text-sm / text-base</span>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ BUTTONS ═══════ -->
        <article id="buttons" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <MousePointerClick class="h-4 w-4 text-blue-600" />
            <h2 class="text-sm font-semibold text-slate-900">Buttons</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="flex flex-wrap gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800">Save changes</button>
                <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50">Cancel</button>
                <button class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-medium transition-colors hover:bg-slate-300">Preview</button>
                <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-red-700">Delete</button>
                <button class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100">More</button>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-5">
                <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm">Small</button>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm">Default</button>
                <button class="rounded-lg bg-slate-900 px-5 py-2.5 text-base font-medium text-white shadow-sm">Large</button>
                <button class="text-sm font-medium text-slate-700 underline underline-offset-4 transition-colors hover:text-slate-900">Link style</button>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-5">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm">Default</button>
                <button disabled class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-sm font-medium text-slate-400">Disabled</button>
                <button class="rounded-lg border-2 border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700">Invalid</button>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use one clear primary CTA per section, then fallback actions as outline/ghost.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not place multiple competing primary buttons side by side.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ BADGES ═══════ -->
        <article id="badges" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Tag class="h-4 w-4 text-emerald-600" />
            <h2 class="text-sm font-semibold text-slate-900">Badges</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="flex flex-wrap gap-2.5 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-medium text-white">Published</span>
                <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-medium text-slate-700">Draft</span>
                <span class="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700">Archived</span>
                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">Rejected</span>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">Approved</span>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">Pending</span>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="flex flex-wrap gap-2.5 rounded-lg border border-slate-200 p-5">
                <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-medium text-white">Default</span>
                <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-medium text-slate-700">Secondary</span>
                <span class="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700">Outline</span>
                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">Destructive</span>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-5">
                <span class="flex items-center gap-1.5 rounded-full bg-slate-900 px-3 py-1 text-xs font-medium text-white">
                  <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                  Active
                </span>
                <span class="text-sm text-slate-500">Use dot indicator + text + badge for live-state clarity.</span>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use badges for short statuses and keep wording consistent across screens.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not use badge color alone to communicate meaning; include readable text.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ INPUTS ═══════ -->
        <article id="inputs" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <TextCursorInput class="h-4 w-4 text-amber-600" />
            <h2 class="text-sm font-semibold text-slate-900">Inputs, Label, Textarea</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="grid gap-4 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5 md:grid-cols-2">
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Title</label>
                  <input class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="Enter title" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Slug</label>
                  <input class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="my-first-post" />
                </div>
                <div class="space-y-1.5 md:col-span-2">
                  <label class="text-sm font-medium text-slate-700">Description</label>
                  <textarea class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" rows="3" placeholder="Write short description..." />
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="grid gap-4 rounded-lg border border-slate-200 p-5 md:grid-cols-3">
                <div class="space-y-1.5">
                  <label class="text-xs font-medium text-slate-500">Default</label>
                  <input class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm" placeholder="Default" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-medium text-slate-500">Disabled</label>
                  <input disabled class="w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-400" placeholder="Disabled" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-medium text-red-500">Invalid</label>
                  <input class="w-full rounded-lg border-2 border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900" value="invalid@email" />
                  <p class="text-xs text-red-500">Please enter a valid email address.</p>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="space-y-2 rounded-lg border border-slate-200 p-5 text-sm">
                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-3">
                  <span class="h-2 w-2 rounded-full bg-slate-400" />
                  <span class="font-medium text-slate-700">Default:</span> editable and readable.
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50/50 p-3">
                  <span class="h-2 w-2 rounded-full bg-red-400" />
                  <span class="font-medium text-red-700">Invalid:</span> <span class="text-slate-600">set <code class="rounded bg-slate-200 px-1 py-0.5 text-xs">aria-invalid</code> and show error message nearby.</span>
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                  <span class="h-2 w-2 rounded-full bg-slate-300" />
                  <span class="font-medium text-slate-500">Disabled:</span> <span class="text-slate-600">non-editable for unavailable fields.</span>
                </div>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Pair every input with a clear label and nearby validation message.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not rely on placeholder text as the only field label.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ SELECT ═══════ -->
        <article id="select" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <ListFilter class="h-4 w-4 text-cyan-600" />
            <h2 class="text-sm font-semibold text-slate-900">Select</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <div class="max-w-sm space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Status</label>
                  <select v-model="selectValue" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <option value="active">Active</option>
                    <option value="review">In Review</option>
                    <option value="archived">Archived</option>
                  </select>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="flex flex-wrap gap-4 rounded-lg border border-slate-200 p-5">
                <div class="space-y-1.5">
                  <label class="text-xs font-medium text-slate-500">Default</label>
                  <select class="w-44 rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm"><option>Active</option></select>
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-medium text-slate-500">Compact</label>
                  <select class="w-44 rounded-lg border border-slate-300 px-3 py-1.5 text-xs shadow-sm"><option>Review</option></select>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Use default state for required fields and keep options concise.</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use select when options are finite and mutually exclusive.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not use select for long free-text content or huge option sets without search.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ TABS ═══════ -->
        <article id="tabs" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <PanelTop class="h-4 w-4 text-pink-600" />
            <h2 class="text-sm font-semibold text-slate-900">Tabs</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <div class="inline-flex rounded-lg bg-slate-200/60 p-1 text-sm">
                  <button
                    :class="activeTab === 'content' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="rounded-md px-4 py-1.5 font-medium transition-all"
                    @click="activeTab = 'content'"
                  >Content</button>
                  <button
                    :class="activeTab === 'seo' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="rounded-md px-4 py-1.5 font-medium transition-all"
                    @click="activeTab = 'seo'"
                  >SEO</button>
                  <button
                    :class="activeTab === 'publish' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="rounded-md px-4 py-1.5 font-medium transition-all"
                    @click="activeTab = 'publish'"
                  >Publish</button>
                </div>
                <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">
                  <template v-if="activeTab === 'content'">Main editor settings and content management.</template>
                  <template v-else-if="activeTab === 'seo'">Meta tags, description, and search optimization.</template>
                  <template v-else>Scheduling, visibility, and publishing options.</template>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Use 2-5 tabs max. Keep tab labels short and noun-based.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Active tab must be visually obvious and keyboard accessible.</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use tabs for related content under the same task context.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not put unrelated workflows in tabs; split into separate pages instead.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ TOAST ═══════ -->
        <article id="toast" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Bell class="h-4 w-4 text-blue-600" />
            <h2 class="text-sm font-semibold text-slate-900">Toast</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="flex flex-wrap gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-emerald-700" @click="demoToast('success')">Success Toast</button>
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700" @click="demoToast('info')">Info Toast</button>
                <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-rose-700" @click="demoToast('error')">Error Toast</button>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Notes</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Toast appears in the admin topbar area (current app style), with auto-dismiss progress.
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ DIALOG ═══════ -->
        <article id="dialog" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <MessageSquare class="h-4 w-4 text-indigo-600" />
            <h2 class="text-sm font-semibold text-slate-900">Dialog</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50" @click="dialogOpen = true">Open confirmation dialog</button>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Prefer one primary and one secondary action in dialogs.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">For destructive actions, use <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">destructive</code> button styling and explicit confirmation text.</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Keep dialog content concise and task-specific.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not place long multi-step workflows inside dialogs.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ DROPDOWN ═══════ -->
        <article id="dropdown" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <ChevronDown class="h-4 w-4 text-teal-600" />
            <h2 class="text-sm font-semibold text-slate-900">Dropdown Menu</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <div class="relative inline-block">
                  <button class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50" @click="dropdownOpen = !dropdownOpen">
                    Open menu
                    <ChevronDown class="h-4 w-4 text-slate-400" />
                  </button>
                  <div v-if="dropdownOpen" class="absolute left-0 top-11 z-10 w-56 rounded-lg border border-slate-200 bg-white p-1.5 shadow-lg">
                    <p class="px-2.5 py-1.5 text-xs font-semibold text-slate-400">Post actions</p>
                    <div class="my-1 border-t border-slate-100" />
                    <button class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm transition-colors hover:bg-slate-100">
                      <Pencil class="h-3.5 w-3.5 text-slate-400" /> Edit post
                    </button>
                    <button class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm transition-colors hover:bg-slate-100">
                      <Layers class="h-3.5 w-3.5 text-slate-400" /> Duplicate
                    </button>
                    <label class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors hover:bg-slate-100">
                      <input v-model="showMeta" type="checkbox" class="rounded" />
                      Show meta panel
                    </label>
                    <div class="my-1 border-t border-slate-100" />
                    <p class="px-2.5 py-1.5 text-xs font-semibold text-slate-400">Change status</p>
                    <label class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors hover:bg-slate-100"><input v-model="dropdownStatus" type="radio" value="published" /> Published</label>
                    <label class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors hover:bg-slate-100"><input v-model="dropdownStatus" type="radio" value="draft" /> Draft</label>
                    <label class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors hover:bg-slate-100"><input v-model="dropdownStatus" type="radio" value="archived" /> Archived</label>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Mix item, checkbox, and radio entries only when needed for real context actions.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="flex items-center gap-3 rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Current status:
                <span class="rounded-full bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-white">{{ dropdownStatus }}</span>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">{{ showMeta ? "meta shown" : "meta hidden" }}</span>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use dropdown for secondary actions to reduce visual clutter.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not hide primary actions inside dropdown menus.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ TOOLTIP ═══════ -->
        <article id="tooltip" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <HelpCircle class="h-4 w-4 text-orange-600" />
            <h2 class="text-sm font-semibold text-slate-900">Tooltip</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="flex items-center gap-4 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <button title="Save draft" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white shadow-sm transition-colors hover:bg-slate-50" aria-label="Save draft">
                  <Check class="h-4 w-4 text-slate-600" />
                </button>
                <span title="More information" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white shadow-sm transition-colors hover:bg-slate-50">
                  <Info class="h-4 w-4 text-slate-600" />
                </span>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Keep tooltip text short (1 line), descriptive, and action-focused.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Tooltip appears on hover/focus and should not block nearby controls.</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use tooltip to clarify icon-only actions.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not use tooltip as a replacement for mandatory visible labels.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ TABLE ═══════ -->
        <article id="table" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Table2 class="h-4 w-4 text-rose-600" />
            <h2 class="text-sm font-semibold text-slate-900">Table</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="overflow-hidden rounded-lg border border-dashed border-slate-300 bg-slate-50/50">
                <div class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 bg-white/90 px-4 py-2.5">
                  <div class="flex flex-wrap items-center gap-2">
                    <label class="text-xs font-medium text-slate-600" for="kitchen-sink-table-page-size">Display</label>
                    <select
                      id="kitchen-sink-table-page-size"
                      v-model.number="tablePageSize"
                      class="rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    >
                      <option v-for="n in [5, 10, 25, 50]" :key="n" :value="n">{{ n }}</option>
                    </select>
                  </div>
                  <div class="flex flex-wrap items-center gap-2">
                    <label class="text-xs font-medium text-slate-600" for="kitchen-sink-table-search">Search</label>
                    <div class="relative w-full max-w-xs min-w-[13rem] shrink-0">
                      <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                      <input
                        id="kitchen-sink-table-search"
                        v-model="tableSearchQuery"
                        type="search"
                        placeholder="Filter rows…"
                        aria-label="Filter table rows"
                        class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-3 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                      />
                    </div>
                    <div v-if="tableHiddenColumnList.length > 0" class="relative">
                      <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-slate-50"
                        @click="tableColumnsPanelOpen = !tableColumnsPanelOpen"
                      >
                        <Columns3 class="h-4 w-4" />
                        Columns
                      </button>
                      <div
                        v-if="tableColumnsPanelOpen"
                        class="absolute right-0 z-20 mt-1 w-64 rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
                        @click.stop
                      >
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Hidden columns</p>
                        <ul class="max-h-56 space-y-1 overflow-y-auto">
                          <li
                            v-for="col in tableHiddenColumnList"
                            :key="col"
                            class="flex items-center justify-between gap-2 rounded border border-transparent px-1 py-0.5 hover:bg-slate-50"
                          >
                            <span class="ml-1 flex flex-1 items-center gap-2 text-xs text-slate-700">{{ tableColumnLabels[col] }}</span>
                            <button
                              type="button"
                              class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-50"
                              @click="showTableColumn(col)"
                            >
                              Show
                            </button>
                          </li>
                        </ul>
                        <div class="mt-2 flex gap-2">
                          <button
                            type="button"
                            class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="tableHiddenColumnList.length === 0"
                            @click="tableHiddenColumnList.forEach(showTableColumn)"
                          >
                            Show all
                          </button>
                          <button
                            type="button"
                            class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
                            @click="tableColumnsPanelOpen = false"
                          >
                            Close
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="overflow-x-auto p-5">
                  <table class="w-full min-w-175 text-sm">
                    <thead>
                      <tr class="border-b border-slate-200">
                        <th
                          v-for="col in tableVisibleColumns"
                          :key="col"
                          scope="col"
                          class="pb-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400"
                          :class="[
                            col === 'no' ? 'w-14' : '',
                            col === 'actions' ? 'text-right' : '',
                            tableDragOverColumn === col && tableDraggingColumn !== col ? 'bg-slate-100' : '',
                          ]"
                          :aria-sort="
                            col === tableSortColumn
                              ? tableSortDirection === 'asc'
                                ? 'ascending'
                                : 'descending'
                              : 'none'
                          "
                          @dragover="onTableHeaderDragOver(col, $event)"
                          @drop="onTableHeaderDrop(col, $event)"
                        >
                          <div :class="['inline-flex items-center gap-1.5', col === 'actions' ? 'justify-end w-full' : '']">
                            <button
                              type="button"
                              class="rounded p-0.5 text-slate-400"
                              :class="
                                canMoveTableColumn(col)
                                  ? 'cursor-grab hover:bg-slate-100 hover:text-slate-700 active:cursor-grabbing'
                                  : 'cursor-not-allowed opacity-50'
                              "
                              :draggable="canMoveTableColumn(col)"
                              :disabled="!canMoveTableColumn(col)"
                              :aria-label="`Move ${tableColumnLabels[col]} column`"
                              @dragstart="onTableHeaderDragStart(col, $event)"
                              @dragend="onTableHeaderDragEnd"
                            >
                              <GripVertical class="h-3.5 w-3.5" />
                            </button>
                            <button
                              v-if="col !== 'actions'"
                              type="button"
                              class="inline-flex items-center gap-1 text-slate-400 hover:text-slate-900"
                              @click="toggleTableSort(col as TableSortColumn)"
                              @contextmenu.prevent="hideTableColumn(col)"
                            >
                              {{ tableColumnLabels[col] }}
                              <span v-if="tableSortColumn === col" class="text-slate-900">{{ tableSortDirection === "asc" ? "↑" : "↓" }}</span>
                            </button>
                            <span v-else>{{ tableColumnLabels[col] }}</span>
                          </div>
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="pagedTableRows.length === 0">
                        <td :colspan="Math.max(tableVisibleColumns.length, 1)" class="py-8 text-center text-sm text-slate-500">
                          No rows match your search.
                        </td>
                      </tr>
                      <tr
                        v-for="(row, idx) in pagedTableRows"
                        :key="row.title"
                        class="border-b border-slate-100 transition-colors last:border-b-0 hover:bg-white"
                      >
                        <td v-for="col in tableVisibleColumns" :key="`${row.title}-${col}`" class="py-3">
                          <template v-if="col === 'no'">
                            <span class="tabular-nums text-slate-500">{{ tableRowDisplayNo(idx) }}</span>
                          </template>
                          <template v-else-if="col === 'title'">
                            <span class="font-medium text-slate-900">{{ row.title }}</span>
                          </template>
                          <template v-else-if="col === 'author'">
                            <span class="text-slate-600">{{ row.author }}</span>
                          </template>
                          <template v-else-if="col === 'status'">
                            <span :class="row.statusClass">{{ row.status }}</span>
                          </template>
                          <template v-else-if="col === 'updated'">
                            <span class="text-slate-500">{{ row.updated }}</span>
                          </template>
                          <template v-else>
                            <div class="flex justify-end gap-1">
                              <button type="button" title="View" class="rounded-lg p-2 transition-colors hover:bg-slate-200">
                                <Eye class="h-4 w-4 text-slate-500" />
                              </button>
                              <button type="button" title="Edit" class="rounded-lg p-2 transition-colors hover:bg-slate-200">
                                <Pencil class="h-4 w-4 text-slate-500" />
                              </button>
                              <button type="button" title="Delete" class="rounded-lg p-2 transition-colors hover:bg-red-100">
                                <Trash2 class="h-4 w-4 text-red-500" />
                              </button>
                            </div>
                          </template>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="space-y-2 border-t border-slate-200 bg-white/90 px-4 py-2.5 text-xs text-slate-500">
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500">
                      Showing
                      {{ sortedFilteredTableRows.length === 0 ? 0 : (tableCurrentPage - 1) * tablePageSize + 1 }}-{{ Math.min(tableCurrentPage * tablePageSize, sortedFilteredTableRows.length) }}
                      of {{ sortedFilteredTableRows.length }}
                    </span>
                    <div class="flex items-center gap-2">
                      <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="tableCurrentPage <= 1 || sortedFilteredTableRows.length === 0"
                        @click="tableCurrentPage--"
                      >
                        Previous
                      </button>
                      <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium">{{ tableCurrentPage }} / {{ tablePageCount }}</span>
                      <button
                        type="button"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="tableCurrentPage >= tablePageCount || sortedFilteredTableRows.length === 0"
                        @click="tableCurrentPage++"
                      >
                        Next
                      </button>
                    </div>
                  </div>
                  <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                      @click="handleTableDownloadPDF"
                    >
                      <Download class="h-3.5 w-3.5" />
                      PDF
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                      @click="handleTableDownloadCSV"
                    >
                      <FileDown class="h-3.5 w-3.5" />
                      CSV
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                      @click="handleTableDownloadExcel"
                    >
                      <FileSpreadsheet class="h-3.5 w-3.5" />
                      Excell
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-slate-800"
                      @click="handleTableAdd"
                    >
                      <Plus class="h-3.5 w-3.5" />
                      Add
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Use badges or icons in cells when status clarity is important.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Provide empty, loading, and error table states in real feature pages.</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Keep columns scannable and order by user priority.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not overload table rows with too many inline actions.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ TIMELINE ═══════ -->
        <article id="timeline" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Clock3 class="h-4 w-4 text-sky-600" />
            <h2 class="text-sm font-semibold text-slate-900">Timeline</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <ol class="space-y-5">
                  <li class="relative pl-8">
                    <span class="absolute left-0 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-emerald-500 ring-4 ring-emerald-100" />
                    <span class="absolute left-[7px] top-5 h-[calc(100%+8px)] w-px bg-slate-200" />
                    <p class="text-sm font-medium text-slate-900">Content drafted</p>
                    <p class="text-xs text-slate-500">By Editor · 09:20 AM</p>
                  </li>
                  <li class="relative pl-8">
                    <span class="absolute left-0 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-blue-500 ring-4 ring-blue-100" />
                    <span class="absolute left-[7px] top-5 h-[calc(100%+8px)] w-px bg-slate-200" />
                    <p class="text-sm font-medium text-slate-900">Sent for review</p>
                    <p class="text-xs text-slate-500">By Content Lead · 11:05 AM</p>
                  </li>
                  <li class="relative pl-8">
                    <span class="absolute left-0 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 ring-4 ring-amber-100" />
                    <p class="text-sm font-medium text-slate-900">Scheduled to publish</p>
                    <p class="text-xs text-slate-500">Tomorrow · 08:00 AM</p>
                  </li>
                </ol>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Use compact timeline for activity logs and expanded timeline for release histories.
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Show clear status color coding (done, in review, scheduled) with readable timestamps.
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Keep chronology obvious with consistent spacing and timestamp formatting.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not overload each timeline row with too many metadata fields.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ STEPS ═══════ -->
        <article id="steps" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <ListChecks class="h-4 w-4 text-indigo-600" />
            <h2 class="text-sm font-semibold text-slate-900">Steps</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <div class="grid gap-3 md:grid-cols-4">
                  <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-xs font-semibold text-emerald-700">Step 1</p>
                    <p class="mt-1 text-sm font-medium text-emerald-900">Draft</p>
                  </div>
                  <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                    <p class="text-xs font-semibold text-blue-700">Step 2</p>
                    <p class="mt-1 text-sm font-medium text-blue-900">Review</p>
                  </div>
                  <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs font-semibold text-amber-700">Step 3</p>
                    <p class="mt-1 text-sm font-medium text-amber-900">Approve</p>
                  </div>
                  <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-xs font-semibold text-slate-500">Step 4</p>
                    <p class="mt-1 text-sm font-medium text-slate-700">Publish</p>
                  </div>
                </div>

                <div class="mt-5 rounded-lg border border-slate-200 bg-white p-4">
                  <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Design 2: Connector Stepper</p>
                  <div class="flex items-center justify-between">
                    <div class="flex flex-col items-center gap-1">
                      <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-semibold text-white">1</span>
                      <span class="text-xs font-medium text-slate-700">Setup</span>
                    </div>
                    <div class="mx-2 h-px flex-1 bg-emerald-300" />
                    <div class="flex flex-col items-center gap-1">
                      <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-semibold text-white">2</span>
                      <span class="text-xs font-medium text-slate-700">Details</span>
                    </div>
                    <div class="mx-2 h-px flex-1 bg-blue-300" />
                    <div class="flex flex-col items-center gap-1">
                      <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">3</span>
                      <span class="text-xs font-medium text-blue-700">Review</span>
                    </div>
                    <div class="mx-2 h-px flex-1 bg-slate-200" />
                    <div class="flex flex-col items-center gap-1">
                      <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 bg-white text-xs font-semibold text-slate-500">4</span>
                      <span class="text-xs font-medium text-slate-500">Done</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Use horizontal steps for short wizards and vertical steps for long setup flows.
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Include complete, active, and pending states with labels and color contrast.
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Keep each step title short and action-oriented.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not hide step status solely in color; always show text labels.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ PAGINATION ═══════ -->
        <article id="pagination" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <ChevronsLeftRight class="h-4 w-4 text-fuchsia-600" />
            <h2 class="text-sm font-semibold text-slate-900">Pagination</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-slate-500">Showing {{ (page - 1) * itemsPerPage + 1 }}-{{ Math.min(page * itemsPerPage, totalItems) }} of {{ totalItems }}</span>
                  <div class="flex items-center gap-2">
                    <button
                      class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                      :disabled="page <= 1"
                      @click="nextPage(-1)"
                    >Previous</button>
                    <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium">{{ page }} / {{ totalPages }}</span>
                    <button
                      class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                      :disabled="page >= totalPages"
                      @click="nextPage(1)"
                    >Next</button>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Use with table/list views when results exceed one page.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Disable previous/next controls at page boundaries (already built into component).</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Keep pagination near the list it controls and preserve filters when changing pages.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not paginate very small datasets; keep them on one page.</p>
                </div>
              </div>
            </div>
          </div>
        </article>
      </section>
    </div>

    <!-- ───── Dialog Overlay ───── -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="dialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="dialogOpen = false">
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="scale-95 opacity-0"
            enter-to-class="scale-100 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="scale-100 opacity-100"
            leave-to-class="scale-95 opacity-0"
          >
            <div v-if="dialogOpen" class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-4 shadow-2xl">
              <h4 class="text-lg font-semibold text-slate-900">Publish this article?</h4>
              <p class="mt-2 text-sm text-slate-500">This action will make the article visible to all visitors.</p>
              <div class="mt-5 flex justify-end gap-2">
                <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50" @click="dialogOpen = false">Cancel</button>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800" @click="dialogOpen = false">Publish</button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>
  </AdminLayout>
</template>
