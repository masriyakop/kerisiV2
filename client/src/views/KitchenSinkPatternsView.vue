<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { RouterLink } from "vue-router";
import {
  ChevronRight,
  Download,
  FileDown,
  FileSpreadsheet,
  Filter,
  MoreVertical,
  Plus,
  Search,
  Table2,
  X,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import KitchenSmartFilterTable from "@/components/kitchen-sink/KitchenSmartFilterTable.vue";
import type { SmartFilterRow } from "@/components/kitchen-sink/KitchenSmartFilterTable.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import { useToast } from "@/composables/useToast";

const toast = useToast();

// ─── Smart filter datatable ─────────────────────────────────────────────
const showSmartFilter = ref(false);
const smartFilterKeyword = ref("");
const smartFilter = ref({
  level: "",
  code: "",
  description: "",
  status: "" as "" | "ACTIVE" | "INACTIVE",
});
const smartFilterDatatableRef = ref<InstanceType<typeof KitchenSmartFilterTable> | null>(null);
const smartFilterPageSize = ref(10);
const smartFilterOverflowOpen = ref(false);
const smartFilterMenuRoot = ref<HTMLElement | null>(null);

const smartFilterSampleData = ref<SmartFilterRow[]>([
  { no: 1, Level: "1", "Budget Code": "BC001", Description: "Operating Expenditure", Status: "ACTIVE" },
  { no: 2, Level: "2", "Budget Code": "BC002", Description: "Capital Expenditure", Status: "ACTIVE" },
  { no: 3, Level: "1", "Budget Code": "BC003", Description: "Personnel Cost", Status: "INACTIVE" },
  { no: 4, Level: "3", "Budget Code": "BC004", Description: "Maintenance", Status: "ACTIVE" },
  { no: 5, Level: "2", "Budget Code": "BC005", Description: "Utilities", Status: "ACTIVE" },
]);

const filteredSmartFilterData = computed(() => {
  let data = [...smartFilterSampleData.value];
  if (smartFilterKeyword.value.trim()) {
    const kw = smartFilterKeyword.value.toLowerCase();
    data = data.filter(
      (r) =>
        (r.Level || "").toLowerCase().includes(kw) ||
        (r["Budget Code"] || "").toLowerCase().includes(kw) ||
        (r.Description || "").toLowerCase().includes(kw) ||
        (r.Status || "").toLowerCase().includes(kw),
    );
  }
  const f = smartFilter.value;
  if (f.level.trim()) {
    const v = f.level.trim().toLowerCase();
    data = data.filter((r) => (r.Level || "").toLowerCase().includes(v));
  }
  if (f.code.trim()) {
    const v = f.code.trim().toLowerCase();
    data = data.filter((r) => (r["Budget Code"] || "").toLowerCase().includes(v));
  }
  if (f.description.trim()) {
    const v = f.description.trim().toLowerCase();
    data = data.filter((r) => (r.Description || "").toLowerCase().includes(v));
  }
  if (f.status) {
    data = data.filter((r) => r.Status === f.status);
  }
  return data;
});

const {
  templateFileInputRef: smartFilterTemplateInputRef,
  isGrouped: smartFilterIsGrouped,
  handleSaveTemplate: handleSmartFilterSaveTemplate,
  handleLoadTemplate: handleSmartFilterLoadTemplate,
  onTemplateFileChange: onSmartFilterTemplateChange,
  handleGenerateApi: handleSmartFilterGenerateApi,
  handleUngroupList: handleSmartFilterUngroup,
  handleGroupList: handleSmartFilterGroup,
  handleDownloadPDF: handleSmartFilterDownloadPDF,
  handleDownloadCSV: handleSmartFilterDownloadCSV,
} = useDatatableFeatures({
  pageName: "Kitchen Sink Smart Filter",
  apiDataPath: "/kitchen-sink",
  defaultExportColumns: ["Level", "Budget Code", "Description", "Status"],
  getFilteredList: () => filteredSmartFilterData.value as unknown as Record<string, unknown>[],
  datatableRef: smartFilterDatatableRef,
  searchKeyword: smartFilterKeyword,
  smartFilter,
  applyFilters: () => {},
});

// ─── Top filter datatable ─────────────────────────────────────────────
const topFilterKeyword = ref("");
const topFilter = ref({
  level: "",
  code: "",
  description: "",
  status: "" as "" | "ACTIVE" | "INACTIVE",
});
const topFilterDatatableRef = ref<InstanceType<typeof KitchenSmartFilterTable> | null>(null);
const topFilterPageSize = ref(10);
const topFilterOverflowOpen = ref(false);
const topFilterMenuRoot = ref<HTMLElement | null>(null);

const topFilterSampleData = ref<SmartFilterRow[]>([
  { no: 1, Level: "1", "Budget Code": "BC001", Description: "Operating Expenditure", Status: "ACTIVE" },
  { no: 2, Level: "2", "Budget Code": "BC002", Description: "Capital Expenditure", Status: "ACTIVE" },
  { no: 3, Level: "1", "Budget Code": "BC003", Description: "Personnel Cost", Status: "INACTIVE" },
  { no: 4, Level: "3", "Budget Code": "BC004", Description: "Maintenance", Status: "ACTIVE" },
  { no: 5, Level: "2", "Budget Code": "BC005", Description: "Utilities", Status: "ACTIVE" },
]);

const filteredTopFilterData = computed(() => {
  let data = [...topFilterSampleData.value];
  if (topFilterKeyword.value.trim()) {
    const kw = topFilterKeyword.value.toLowerCase();
    data = data.filter(
      (r) =>
        (r.Level || "").toLowerCase().includes(kw) ||
        (r["Budget Code"] || "").toLowerCase().includes(kw) ||
        (r.Description || "").toLowerCase().includes(kw) ||
        (r.Status || "").toLowerCase().includes(kw),
    );
  }
  const f = topFilter.value;
  if (f.level.trim()) {
    const v = f.level.trim().toLowerCase();
    data = data.filter((r) => (r.Level || "").toLowerCase().includes(v));
  }
  if (f.code.trim()) {
    const v = f.code.trim().toLowerCase();
    data = data.filter((r) => (r["Budget Code"] || "").toLowerCase().includes(v));
  }
  if (f.description.trim()) {
    const v = f.description.trim().toLowerCase();
    data = data.filter((r) => (r.Description || "").toLowerCase().includes(v));
  }
  if (f.status) {
    data = data.filter((r) => r.Status === f.status);
  }
  return data;
});

const {
  templateFileInputRef: topFilterTemplateInputRef,
  isGrouped: topFilterIsGrouped,
  handleSaveTemplate: handleTopFilterSaveTemplate,
  handleLoadTemplate: handleTopFilterLoadTemplate,
  onTemplateFileChange: onTopFilterTemplateChange,
  handleGenerateApi: handleTopFilterGenerateApi,
  handleUngroupList: handleTopFilterUngroup,
  handleGroupList: handleTopFilterGroup,
  handleDownloadPDF: handleTopFilterDownloadPDF,
  handleDownloadCSV: handleTopFilterDownloadCSV,
} = useDatatableFeatures({
  pageName: "Kitchen Sink Top Filter",
  apiDataPath: "/kitchen-sink",
  defaultExportColumns: ["Level", "Budget Code", "Description", "Status"],
  getFilteredList: () => filteredTopFilterData.value as unknown as Record<string, unknown>[],
  datatableRef: topFilterDatatableRef,
  searchKeyword: topFilterKeyword,
  smartFilter: topFilter,
  applyFilters: () => {},
});

function handleTopFilterReset() {
  topFilter.value = { level: "", code: "", description: "", status: "" };
}

async function handleTopFilterExportExcel() {
  try {
    const ExcelJS = await import("exceljs");
    const data = filteredTopFilterData.value;
    const cols = ["Level", "Budget Code", "Description", "Status"] as const;
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Export");
    sheet.addRow(["No.", ...cols]);
    data.forEach((item, i) => {
      const row: (string | number)[] = [i + 1];
      cols.forEach((c) => row.push(item[c] ?? ""));
      sheet.addRow(row);
    });
    const buf = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `Kitchen_Sink_top_${new Date().toISOString().split("T")[0]}.xlsx`;
    a.click();
    URL.revokeObjectURL(a.href);
    toast.success("Excel downloaded");
  } catch (e) {
    const err = e as Error;
    toast.error("Export failed", err?.message || "Excel export failed.");
  }
}

function handleSmartFilterOk() {
  showSmartFilter.value = false;
}

function handleSmartFilterReset() {
  smartFilter.value = { level: "", code: "", description: "", status: "" };
}

async function handleExportExcel() {
  try {
    const ExcelJS = await import("exceljs");
    const data = filteredSmartFilterData.value;
    const cols = ["Level", "Budget Code", "Description", "Status"] as const;
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet("Export");
    sheet.addRow(["No.", ...cols]);
    data.forEach((item, i) => {
      const row: (string | number)[] = [i + 1];
      cols.forEach((c) => row.push(item[c] ?? ""));
      sheet.addRow(row);
    });
    const buf = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `Kitchen_Sink_smart_${new Date().toISOString().split("T")[0]}.xlsx`;
    a.click();
    URL.revokeObjectURL(a.href);
    toast.success("Excel downloaded");
  } catch (e) {
    const err = e as Error;
    toast.error("Export failed", err?.message || "Excel export failed.");
  }
}

// ─── Miller columns ─────────────────────────────────────────────────────
type MillerItem = { id: string; label: string; desc: string; status: "ACTIVE" | "INACTIVE" };

const millerColumns = [
  { key: "category" as const, title: "CATEGORY", level: 0 },
  { key: "subcategory" as const, title: "SUBCATEGORY", level: 1 },
  { key: "item" as const, title: "ITEM", level: 2 },
];

const millerData = ref<{
  category: MillerItem[];
  subcategory: Record<string, MillerItem[]>;
  item: Record<string, MillerItem[]>;
}>({
  category: [
    { id: "c1", label: "Electronics", desc: "Electronic devices", status: "ACTIVE" },
    { id: "c2", label: "Office", desc: "Office supplies", status: "ACTIVE" },
  ],
  subcategory: {
    c1: [
      { id: "s1", label: "Phones", desc: "Mobile phones", status: "ACTIVE" },
      { id: "s2", label: "Laptops", desc: "Laptop computers", status: "ACTIVE" },
    ],
    c2: [
      { id: "s3", label: "Stationery", desc: "Pens, paper", status: "ACTIVE" },
      { id: "s4", label: "Furniture", desc: "Desks, chairs", status: "ACTIVE" },
    ],
  },
  item: {
    s1: [
      { id: "i1", label: "iPhone 15", desc: "Apple smartphone", status: "ACTIVE" },
      { id: "i2", label: "Galaxy S24", desc: "Samsung smartphone", status: "ACTIVE" },
    ],
    s2: [
      { id: "i3", label: "MacBook Pro", desc: "Apple laptop", status: "ACTIVE" },
      { id: "i4", label: "ThinkPad X1", desc: "Lenovo laptop", status: "INACTIVE" },
    ],
    s3: [{ id: "i5", label: "Ballpoint Pen", desc: "Blue ink", status: "ACTIVE" }],
    s4: [{ id: "i6", label: "Office Chair", desc: "Ergonomic", status: "ACTIVE" }],
  },
});

const millerSelected = ref<{
  category: MillerItem | null;
  subcategory: MillerItem | null;
  item: MillerItem | null;
}>({ category: null, subcategory: null, item: null });

const millerSearchKeywords = ref({ category: "", subcategory: "", item: "" });
const millerActionMenu = ref({ show: false, x: 0, y: 0, colKey: "" as "category" | "subcategory" | "item" | "", item: null as MillerItem | null });
const millerDownloadMenu = ref({ show: false, x: 0, y: 0, colKey: "" as "category" | "subcategory" | "item" | "" });
const millerModal = ref(false);
const millerModalMode = ref<"add" | "edit" | "view">("add");
const millerModalColKey = ref<"category" | "subcategory" | "item">("category");
const millerForm = ref<{ label: string; desc: string; status: "ACTIVE" | "INACTIVE"; _item?: MillerItem | null }>({
  label: "",
  desc: "",
  status: "ACTIVE",
});

const millerVisibleColumns = computed(() => {
  const v: ("category" | "subcategory" | "item")[] = ["category"];
  if (millerSelected.value.category) v.push("subcategory");
  if (millerSelected.value.subcategory) v.push("item");
  return v;
});

const millerColumnWidths: Record<string, number> = { category: 180, subcategory: 180, item: 180 };

function handleMillerItemClick(colKey: "category" | "subcategory" | "item", item: MillerItem) {
  millerSelected.value[colKey] = item;
  if (colKey === "category") {
    millerSelected.value.subcategory = null;
    millerSelected.value.item = null;
  } else if (colKey === "subcategory") {
    millerSelected.value.item = null;
  }
}

function getMillerList(colKey: "category" | "subcategory" | "item"): MillerItem[] {
  if (colKey === "category") return millerData.value.category;
  if (colKey === "subcategory") {
    const cat = millerSelected.value.category;
    return cat ? millerData.value.subcategory[cat.id] ?? [] : [];
  }
  if (colKey === "item") {
    const sub = millerSelected.value.subcategory;
    return sub ? millerData.value.item[sub.id] ?? [] : [];
  }
  return [];
}

function getMillerFilteredList(colKey: "category" | "subcategory" | "item"): MillerItem[] {
  const list = getMillerList(colKey);
  const kw = (millerSearchKeywords.value[colKey] || "").toLowerCase().trim();
  if (!kw) return list;
  return list.filter((i) => (i.label || "").toLowerCase().includes(kw) || (i.desc || "").toLowerCase().includes(kw));
}

const millerSelectionPath = computed(() => {
  const p: { key: string; label: string }[] = [];
  if (millerSelected.value.category) p.push({ key: "category", label: millerSelected.value.category.label });
  if (millerSelected.value.subcategory) p.push({ key: "subcategory", label: millerSelected.value.subcategory.label });
  if (millerSelected.value.item) p.push({ key: "item", label: millerSelected.value.item.label });
  return p;
});

function toggleMillerActionMenu(e: MouseEvent, colKey: "category" | "subcategory" | "item", item: MillerItem) {
  e.stopPropagation();
  if (millerActionMenu.value.show && millerActionMenu.value.item === item) {
    millerActionMenu.value.show = false;
    return;
  }
  const el = e.currentTarget as HTMLElement;
  const rect = el.getBoundingClientRect();
  millerActionMenu.value = { show: true, x: rect.right + 4, y: rect.top, colKey, item };
}

function toggleMillerDownloadMenu(e: MouseEvent, colKey: "category" | "subcategory" | "item") {
  e.stopPropagation();
  if (millerDownloadMenu.value.show && millerDownloadMenu.value.colKey === colKey) {
    millerDownloadMenu.value.show = false;
    return;
  }
  const el = e.currentTarget as HTMLElement;
  const rect = el.getBoundingClientRect();
  millerDownloadMenu.value = { show: true, x: rect.left, y: rect.bottom + 4, colKey };
}

function closeMillerActionMenu() {
  millerActionMenu.value.show = false;
}
function closeMillerDownloadMenu() {
  millerDownloadMenu.value.show = false;
}

function handleMillerAdd(colKey: "category" | "subcategory" | "item") {
  millerModalMode.value = "add";
  millerModalColKey.value = colKey;
  millerForm.value = { label: "", desc: "", status: "ACTIVE" };
  millerModal.value = true;
}

function handleMillerView(_colKey: "category" | "subcategory" | "item", item: MillerItem) {
  millerModalMode.value = "view";
  millerModalColKey.value = _colKey;
  millerForm.value = { label: item.label, desc: item.desc || "", status: item.status || "ACTIVE" };
  millerModal.value = true;
}

function handleMillerEdit(_colKey: "category" | "subcategory" | "item", item: MillerItem) {
  millerModalMode.value = "edit";
  millerModalColKey.value = _colKey;
  millerForm.value = { label: item.label, desc: item.desc || "", status: item.status || "ACTIVE", _item: item };
  millerModal.value = true;
}

function handleMillerDelete(colKey: "category" | "subcategory" | "item", item: MillerItem) {
  if (!window.confirm(`Delete "${item.label}"?`)) return;
  if (colKey === "category") {
    millerData.value.category = millerData.value.category.filter((i) => i.id !== item.id);
    if (millerSelected.value.category?.id === item.id) {
      millerSelected.value.category = null;
      millerSelected.value.subcategory = null;
      millerSelected.value.item = null;
    }
  } else if (colKey === "subcategory") {
    const catId = millerSelected.value.category?.id;
    if (catId && millerData.value.subcategory[catId]) {
      millerData.value.subcategory[catId] = millerData.value.subcategory[catId].filter((i) => i.id !== item.id);
      if (millerSelected.value.subcategory?.id === item.id) {
        millerSelected.value.subcategory = null;
        millerSelected.value.item = null;
      }
    }
  } else if (colKey === "item") {
    const subId = millerSelected.value.subcategory?.id;
    if (subId && millerData.value.item[subId]) {
      millerData.value.item[subId] = millerData.value.item[subId].filter((i) => i.id !== item.id);
      if (millerSelected.value.item?.id === item.id) millerSelected.value.item = null;
    }
  }
  toast.success("Deleted", "Item removed.");
}

function handleMillerSave() {
  if (!millerForm.value.label?.trim()) {
    toast.info("Validation", "Label is required.");
    return;
  }
  const colKey = millerModalColKey.value;
  if (millerModalMode.value === "add") {
    const newId = `new_${Date.now()}`;
    const newItem: MillerItem = {
      id: newId,
      label: millerForm.value.label.trim(),
      desc: millerForm.value.desc || "",
      status: millerForm.value.status || "ACTIVE",
    };
    if (colKey === "category") {
      millerData.value.category.push(newItem);
    } else if (colKey === "subcategory") {
      const catId = millerSelected.value.category?.id;
      if (catId) {
        if (!millerData.value.subcategory[catId]) millerData.value.subcategory[catId] = [];
        millerData.value.subcategory[catId].push(newItem);
      }
    } else if (colKey === "item") {
      const subId = millerSelected.value.subcategory?.id;
      if (subId) {
        if (!millerData.value.item[subId]) millerData.value.item[subId] = [];
        millerData.value.item[subId].push(newItem);
      }
    }
    toast.success("Added", "Item created.");
  } else if (millerModalMode.value === "edit") {
    const orig = millerForm.value._item;
    if (orig) {
      orig.label = millerForm.value.label.trim();
      orig.desc = millerForm.value.desc || "";
      orig.status = millerForm.value.status || "ACTIVE";
    }
    toast.success("Updated", "Item saved.");
  }
  millerModal.value = false;
}

function getMillerExportData(colKey: "category" | "subcategory" | "item") {
  const list = getMillerFilteredList(colKey);
  return list.map((item, idx) => ({
    no: idx + 1,
    Code: item.label,
    "Description (Malay)": item.desc || "",
    Status: item.status || "ACTIVE",
  }));
}

function getMillerColumnTitle(colKey: "category" | "subcategory" | "item") {
  return millerColumns.find((c) => c.key === colKey)?.title || colKey;
}

async function handleMillerDownloadPDF(colKey: "category" | "subcategory" | "item") {
  try {
    const { default: jsPDF } = await import("jspdf");
    const autoTable = (await import("jspdf-autotable")).default;
    const data = getMillerExportData(colKey);
    if (data.length === 0) {
      toast.info("No data", "Nothing to export.");
      return;
    }
    const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
    const title = getMillerColumnTitle(colKey);
    doc.setFontSize(16);
    doc.setFont("helvetica", "bold");
    doc.text(title, 105, 15, { align: "center" });
    autoTable(doc, {
      head: [["No.", "Code", "Description (Malay)", "Status"]],
      body: data.map((r) => [r.no, r.Code, r["Description (Malay)"], r.Status]),
      startY: 22,
      styles: { fontSize: 9 },
      headStyles: { fillColor: [59, 130, 246], textColor: [255, 255, 255], fontStyle: "bold" },
    });
    doc.save(`${title.replace(/\s+/g, "_")}_${new Date().toISOString().split("T")[0]}.pdf`);
    toast.success("PDF downloaded");
  } catch (e) {
    const err = e as Error;
    toast.error("Export failed", err?.message || "PDF failed.");
  }
}

function handleMillerDownloadCSV(colKey: "category" | "subcategory" | "item") {
  try {
    const data = getMillerExportData(colKey);
    if (data.length === 0) {
      toast.info("No data", "Nothing to export.");
      return;
    }
    const escape = (f: unknown) => {
      if (f == null) return "";
      const s = String(f);
      return s.includes(",") || s.includes('"') ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const headers = ["No.", "Code", "Description (Malay)", "Status"];
    let csv = headers.map(escape).join(",") + "\n";
    data.forEach((r) => {
      csv += [r.no, r.Code, r["Description (Malay)"], r.Status].map(escape).join(",") + "\n";
    });
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = `${getMillerColumnTitle(colKey).replace(/\s+/g, "_")}_${new Date().toISOString().split("T")[0]}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
    toast.success("CSV downloaded");
  } catch (e) {
    const err = e as Error;
    toast.error("Export failed", err?.message || "CSV failed.");
  }
}

async function handleMillerDownloadExcel(colKey: "category" | "subcategory" | "item") {
  try {
    const ExcelJS = await import("exceljs");
    const data = getMillerExportData(colKey);
    if (data.length === 0) {
      toast.info("No data", "Nothing to export.");
      return;
    }
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet(getMillerColumnTitle(colKey));
    ws.addRow(["No.", "Code", "Description (Malay)", "Status"]);
    data.forEach((r) => ws.addRow([r.no, r.Code, r["Description (Malay)"], r.Status]));
    const buf = await wb.xlsx.writeBuffer();
    const a = document.createElement("a");
    a.href = URL.createObjectURL(new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" }));
    a.download = `${getMillerColumnTitle(colKey).replace(/\s+/g, "_")}_${new Date().toISOString().split("T")[0]}.xlsx`;
    a.click();
    URL.revokeObjectURL(a.href);
    toast.success("Excel downloaded");
  } catch (e) {
    const err = e as Error;
    toast.error("Export failed", err?.message || "Excel failed.");
  }
}

function onGlobalWindowClick(e: MouseEvent) {
  const target = e.target as Node;
  if (!smartFilterMenuRoot.value?.contains(target)) smartFilterOverflowOpen.value = false;
  if (!topFilterMenuRoot.value?.contains(target)) topFilterOverflowOpen.value = false;
  closeMillerActionMenu();
  closeMillerDownloadMenu();
}

// ─── Side panel ─────────────────────────────────────────────────────────
const sidePanelItems = ref([
  { id: 1, title: "Dashboard", menu: "Home", status: "ACTIVE" as const },
  { id: 2, title: "Budget Code", menu: "Budget / Setup", status: "ACTIVE" as const },
  { id: 3, title: "Page Creator", menu: "Workbench Editor", status: "ACTIVE" as const },
]);

type SideItem = (typeof sidePanelItems.value)[number];
const sidePanelSelected = ref<SideItem | null>(null);
const sidePanelSearch = ref("");

const sidePanelFilteredItems = computed(() => {
  if (!sidePanelSearch.value.trim()) return sidePanelItems.value;
  const kw = sidePanelSearch.value.toLowerCase();
  return sidePanelItems.value.filter(
    (p) => (p.title || "").toLowerCase().includes(kw) || (p.menu || "").toLowerCase().includes(kw),
  );
});

onMounted(() => {
  window.addEventListener("click", onGlobalWindowClick);
});
onUnmounted(() => {
  window.removeEventListener("click", onGlobalWindowClick);
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h1 class="page-title">Kitchen Sink — UI patterns</h1>
          <p class="mt-1 text-sm text-slate-500">
            Ported from Kerisi: smart-filter datatable, Miller columns, side-panel master-detail (
            <code class="rounded bg-slate-100 px-1 text-xs">docs/kitchen-sink-patterns-migration.md</code>
            ).
          </p>
        </div>
        <RouterLink
          to="/admin/kitchen-sink"
          class="text-sm font-medium text-slate-600 underline-offset-4 hover:text-slate-900 hover:underline"
        >
          ← Component gallery
        </RouterLink>
      </div>

      <!-- Smart filter datatable -->
      <input
        ref="smartFilterTemplateInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onSmartFilterTemplateChange"
      />

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <div class="flex items-center gap-2">
            <Table2 class="h-4 w-4 text-slate-600" />
            <div>
              <h2 class="text-sm font-semibold text-slate-900">Datatable — smart filter pattern</h2>
              <p class="text-xs text-slate-500">Quick search, structured filter modal, column layout, template JSON, exports</p>
            </div>
          </div>
          <div ref="smartFilterMenuRoot" class="relative">
            <button
              type="button"
              class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
              aria-label="Table actions"
              @click.stop="smartFilterOverflowOpen = !smartFilterOverflowOpen"
            >
              <MoreVertical class="h-4 w-4" />
            </button>
            <div
              v-if="smartFilterOverflowOpen"
              class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
              @click.stop
            >
              <button
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="
                  smartFilterOverflowOpen = false;
                  handleSmartFilterSaveTemplate();
                "
              >
                Save template
              </button>
              <button
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="
                  smartFilterOverflowOpen = false;
                  handleSmartFilterLoadTemplate();
                "
              >
                Load template
              </button>
              <button
                v-if="smartFilterIsGrouped"
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="
                  smartFilterOverflowOpen = false;
                  handleSmartFilterUngroup();
                "
              >
                Ungroup list
              </button>
              <button
                v-else
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="
                  smartFilterOverflowOpen = false;
                  handleSmartFilterGroup();
                "
              >
                Group list
              </button>
            </div>
          </div>
        </div>
        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select
                v-model.number="smartFilterPageSize"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
              >
                <option v-for="n in [5, 10, 25, 50]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="smartFilterKeyword"
                  type="search"
                  placeholder="Filter rows…"
                  class="w-52 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                />
                <button
                  v-if="smartFilterKeyword"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  @click="smartFilterKeyword = ''"
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

          <KitchenSmartFilterTable
            ref="smartFilterDatatableRef"
            :data="filteredSmartFilterData"
            :page-size="smartFilterPageSize"
            :grouped="smartFilterIsGrouped"
          />

          <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
            <span class="text-sm text-slate-500">{{ filteredSmartFilterData.length }} records (after filters)</span>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                @click="handleSmartFilterDownloadPDF"
              >
                <Download class="h-3.5 w-3.5" />
                PDF
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                @click="handleSmartFilterDownloadCSV"
              >
                <FileDown class="h-3.5 w-3.5" />
                CSV
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                @click="handleExportExcel"
              >
                <FileSpreadsheet class="h-3.5 w-3.5" />
                Excel
              </button>
            </div>
          </div>
        </div>
      </article>

      <!-- Top filter datatable -->
      <input
        ref="topFilterTemplateInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onTopFilterTemplateChange"
      />

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <div class="flex items-center gap-2">
            <Table2 class="h-4 w-4 text-slate-600" />
            <div>
              <h2 class="text-sm font-semibold text-slate-900">Datatable — top filter pattern</h2>
              <p class="text-xs text-slate-500">Quick search, top filter row, column layout, template JSON, exports</p>
            </div>
          </div>
          <div ref="topFilterMenuRoot" class="relative">
            <button
              type="button"
              class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
              aria-label="Table actions"
              @click.stop="topFilterOverflowOpen = !topFilterOverflowOpen"
            >
              <MoreVertical class="h-4 w-4" />
            </button>
            <div
              v-if="topFilterOverflowOpen"
              class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
              @click.stop
            >
              <button
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="
                  topFilterOverflowOpen = false;
                  handleTopFilterSaveTemplate();
                "
              >
                Save template
              </button>
              <button
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="
                  topFilterOverflowOpen = false;
                  handleTopFilterLoadTemplate();
                "
              >
                Load template
              </button>
              <button
                v-if="topFilterIsGrouped"
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="
                  topFilterOverflowOpen = false;
                  handleTopFilterUngroup();
                "
              >
                Ungroup list
              </button>
              <button
                v-else
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="
                  topFilterOverflowOpen = false;
                  handleTopFilterGroup();
                "
              >
                Group list
              </button>
            </div>
          </div>
        </div>
        <div class="space-y-4 p-4">
          <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-5">
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Level</label>
                <input
                  v-model="topFilter.level"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Budget code</label>
                <input
                  v-model="topFilter.code"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Description</label>
                <input
                  v-model="topFilter.description"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
                <select
                  v-model="topFilter.status"
                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm shadow-sm"
                >
                  <option value="">Any</option>
                  <option value="ACTIVE">ACTIVE</option>
                  <option value="INACTIVE">INACTIVE</option>
                </select>
              </div>
              <div class="flex items-end">
                <button
                  type="button"
                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-slate-50"
                  @click="handleTopFilterReset"
                >
                  Reset top filter
                </button>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select
                v-model.number="topFilterPageSize"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
              >
                <option v-for="n in [5, 10, 25, 50]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="topFilterKeyword"
                  type="search"
                  placeholder="Filter rows…"
                  class="w-52 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                />
                <button
                  v-if="topFilterKeyword"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  @click="topFilterKeyword = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>

          <KitchenSmartFilterTable
            ref="topFilterDatatableRef"
            :data="filteredTopFilterData"
            :page-size="topFilterPageSize"
            :grouped="topFilterIsGrouped"
          />

          <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
            <span class="text-sm text-slate-500">{{ filteredTopFilterData.length }} records (after filters)</span>
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                @click="handleTopFilterDownloadPDF"
              >
                <Download class="h-3.5 w-3.5" />
                PDF
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                @click="handleTopFilterDownloadCSV"
              >
                <FileDown class="h-3.5 w-3.5" />
                CSV
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                @click="handleTopFilterExportExcel"
              >
                <FileSpreadsheet class="h-3.5 w-3.5" />
                Excel
              </button>
            </div>
          </div>
        </div>
      </article>

      <!-- Miller columns -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <div>
            <h2 class="text-sm font-semibold text-slate-900">Miller columns — hierarchical drilldown</h2>
            <p class="text-xs text-slate-500">Per-column search, path, CRUD demo, exports per column</p>
          </div>
          <span class="text-xs text-slate-400">{{ millerVisibleColumns.length }} of {{ millerColumns.length }} levels</span>
        </div>
        <div class="space-y-4 p-4">
          <div class="flex min-h-[28px] flex-wrap items-center gap-1 text-sm">
            <span class="font-medium text-slate-500">Path:</span>
            <template v-if="millerSelectionPath.length === 0">
              <span class="italic text-slate-400">Select an item to begin browsing…</span>
            </template>
            <template v-for="(part, idx) in millerSelectionPath" :key="part.key">
              <ChevronRight v-if="idx > 0" class="mx-0.5 h-3.5 w-3.5 text-slate-300" />
              <span class="inline-flex items-center rounded-full bg-slate-900/10 px-2 py-0.5 text-xs font-medium text-slate-900">
                {{ part.label }}
              </span>
            </template>
          </div>

          <div class="flex overflow-x-auto rounded-lg border border-slate-200">
            <div
              v-for="colKey in millerVisibleColumns"
              :key="colKey"
              class="flex shrink-0 flex-col border-r border-slate-200 last:border-r-0"
              :style="{ width: millerColumnWidths[colKey] + 'px' }"
            >
              <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
                <span class="truncate text-xs font-bold uppercase tracking-wide text-slate-600">
                  {{ millerColumns.find((c) => c.key === colKey)?.title }}
                </span>
                <div class="flex items-center gap-1">
                  <span class="rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] text-slate-600">
                    {{ getMillerFilteredList(colKey).length }}
                  </span>
                  <button
                    type="button"
                    class="rounded p-0.5 text-slate-500 hover:bg-slate-200"
                    title="Add"
                    @click.stop="handleMillerAdd(colKey)"
                  >
                    <Plus class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    class="rounded p-0.5 text-slate-500 hover:bg-slate-200"
                    title="Download"
                    @click.stop="toggleMillerDownloadMenu($event, colKey)"
                  >
                    <MoreVertical class="h-4 w-4" />
                  </button>
                </div>
              </div>
              <div class="border-b border-slate-100 px-2 py-1.5">
                <div class="relative">
                  <Search class="pointer-events-none absolute left-2 top-1/2 h-3 w-3 -translate-y-1/2 text-slate-400" />
                  <input
                    v-model="millerSearchKeywords[colKey]"
                    type="text"
                    placeholder="Search…"
                    class="w-full rounded border border-slate-200 bg-white py-1 pl-7 pr-7 text-xs text-slate-800 focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-300"
                    @click.stop
                  />
                  <button
                    v-if="millerSearchKeywords[colKey]"
                    type="button"
                    class="absolute right-1 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                    @click.stop="millerSearchKeywords[colKey] = ''"
                  >
                    <X class="h-3 w-3" />
                  </button>
                </div>
              </div>
              <div class="max-h-[200px] flex-1 overflow-y-auto py-0.5">
                <div
                  v-for="(item, idx) in getMillerFilteredList(colKey)"
                  :key="item.id || idx"
                  class="group mx-0.5 flex cursor-pointer items-center gap-2 rounded px-3 py-1.5 transition-colors"
                  :class="
                    millerSelected[colKey]?.id === item.id
                      ? 'bg-slate-900 text-white'
                      : 'hover:bg-slate-100'
                  "
                  @click="handleMillerItemClick(colKey, item)"
                  @contextmenu.prevent="toggleMillerActionMenu($event, colKey, item)"
                >
                  <span
                    class="h-1.5 w-1.5 shrink-0 rounded-full"
                    :class="{
                      'bg-emerald-400': (item.status || 'ACTIVE') === 'ACTIVE',
                      'bg-red-400': item.status === 'INACTIVE',
                      'bg-slate-300': !item.status,
                    }"
                  />
                  <div class="min-w-0 flex-1">
                    <div class="truncate text-xs font-semibold">{{ item.label }}</div>
                    <div
                      class="truncate text-[10px] leading-tight"
                      :class="millerSelected[colKey]?.id === item.id ? 'text-white/70' : 'text-slate-500'"
                    >
                      {{ item.desc || "" }}
                    </div>
                  </div>
                  <button
                    type="button"
                    class="shrink-0 rounded p-0.5 transition-opacity hover:bg-black/10"
                    :class="millerSelected[colKey]?.id === item.id ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
                    title="Actions"
                    @click.stop="toggleMillerActionMenu($event, colKey, item)"
                  >
                    <MoreVertical
                      class="h-4 w-4"
                      :class="millerSelected[colKey]?.id === item.id ? 'text-white/80' : 'text-slate-400'"
                    />
                  </button>
                </div>
                <div v-if="getMillerFilteredList(colKey).length === 0" class="px-3 py-6 text-center text-xs italic text-slate-400">
                  No items found
                </div>
              </div>
            </div>
          </div>
        </div>
      </article>

      <!-- Side panel -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
          <h2 class="text-sm font-semibold text-slate-900">Side panel — multilevel master-detail</h2>
          <p class="text-xs text-slate-500">Narrow master list + detail pane (demo is one-level master)</p>
        </div>
        <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-3">
          <div class="flex flex-col gap-3 lg:col-span-1">
            <div class="flex items-center gap-2">
              <label class="shrink-0 text-sm font-medium text-slate-700">Search</label>
              <div class="relative flex-1">
                <input
                  v-model="sidePanelSearch"
                  type="search"
                  placeholder="Search…"
                  class="w-full rounded-lg border border-slate-300 py-2 pl-3 pr-8 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                />
                <button
                  v-if="sidePanelSearch"
                  type="button"
                  class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                  @click="sidePanelSearch = ''"
                >
                  <X class="h-4 w-4" />
                </button>
              </div>
            </div>
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
              <div class="border-b border-slate-100 px-4 py-2 text-xs font-semibold uppercase text-slate-500">Items</div>
              <div class="max-h-[240px] overflow-y-auto">
                <button
                  v-for="item in sidePanelFilteredItems"
                  :key="item.id"
                  type="button"
                  class="w-full border-b border-slate-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-slate-900/5"
                  :class="{
                    'border-l-2 border-l-slate-900 bg-slate-900/10 text-slate-900': sidePanelSelected?.id === item.id,
                  }"
                  @click="sidePanelSelected = item"
                >
                  <div class="text-sm font-semibold">{{ item.title }}</div>
                  <div class="text-xs text-slate-500">{{ item.menu || "No menu" }}</div>
                  <span
                    class="mt-1 inline-block rounded px-2 py-0.5 text-[11px]"
                    :class="
                      item.status === 'ACTIVE'
                        ? 'bg-emerald-100 text-emerald-800'
                        : 'bg-red-100 text-red-800'
                    "
                  >
                    {{ item.status || "UNKNOWN" }}
                  </span>
                </button>
                <div v-if="sidePanelFilteredItems.length === 0" class="p-4 text-center text-sm text-slate-500">No items found</div>
              </div>
            </div>
          </div>
          <div class="flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-slate-100 px-4 py-3">
              <div class="text-sm font-semibold text-slate-900">Detail</div>
            </div>
            <div class="flex flex-1 flex-col gap-4 p-4">
              <div v-if="!sidePanelSelected" class="flex h-32 items-center justify-center text-sm text-slate-500">
                Select an item to view details
              </div>
              <div v-else class="space-y-3">
                <div>
                  <p class="text-xs font-medium text-slate-500">Title</p>
                  <p class="text-sm font-semibold text-slate-900">{{ sidePanelSelected.title }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-500">Menu</p>
                  <p class="text-sm text-slate-800">{{ sidePanelSelected.menu }}</p>
                </div>
                <div>
                  <p class="text-xs font-medium text-slate-500">Status</p>
                  <span
                    class="inline-block rounded px-2 py-0.5 text-xs"
                    :class="
                      sidePanelSelected.status === 'ACTIVE'
                        ? 'bg-emerald-100 text-emerald-800'
                        : 'bg-red-100 text-red-800'
                    "
                  >
                    {{ sidePanelSelected.status }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </article>
    </div>

    <!-- Miller action menu -->
    <Teleport to="body">
      <div
        v-if="millerActionMenu.show"
        class="fixed z-50 min-w-[150px] rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
        :style="{ left: millerActionMenu.x + 'px', top: millerActionMenu.y + 'px' }"
        @click.stop
      >
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
          @click="
            millerActionMenu.colKey &&
              millerActionMenu.item &&
              handleMillerView(millerActionMenu.colKey, millerActionMenu.item);
            closeMillerActionMenu();
          "
        >
          View
        </button>
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
          @click="
            millerActionMenu.colKey &&
              millerActionMenu.item &&
              handleMillerEdit(millerActionMenu.colKey, millerActionMenu.item);
            closeMillerActionMenu();
          "
        >
          Edit
        </button>
        <div class="my-1 border-t border-slate-100" />
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-red-600 hover:bg-red-50"
          @click="
            millerActionMenu.colKey &&
              millerActionMenu.item &&
              handleMillerDelete(millerActionMenu.colKey, millerActionMenu.item);
            closeMillerActionMenu();
          "
        >
          Delete
        </button>
      </div>
    </Teleport>

    <!-- Miller download menu -->
    <Teleport to="body">
      <div
        v-if="millerDownloadMenu.show"
        class="fixed z-50 min-w-[170px] rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
        :style="{ left: millerDownloadMenu.x + 'px', top: millerDownloadMenu.y + 'px' }"
        @click.stop
      >
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
          @click="
            millerDownloadMenu.colKey && handleMillerDownloadPDF(millerDownloadMenu.colKey);
            closeMillerDownloadMenu();
          "
        >
          Download PDF
        </button>
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
          @click="
            millerDownloadMenu.colKey && handleMillerDownloadCSV(millerDownloadMenu.colKey);
            closeMillerDownloadMenu();
          "
        >
          Download CSV
        </button>
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
          @click="
            millerDownloadMenu.colKey && handleMillerDownloadExcel(millerDownloadMenu.colKey);
            closeMillerDownloadMenu();
          "
        >
          Download Excel
        </button>
      </div>
    </Teleport>

    <!-- Miller modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="millerModal"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
          @click.self="millerModal = false"
        >
          <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-2xl" @click.stop>
            <div class="border-b border-slate-100 px-4 py-3">
              <h3 class="text-base font-semibold text-slate-900">
                {{
                  millerModalMode === "view" ? "View item" : millerModalMode === "edit" ? "Edit item" : "Add item"
                }}
              </h3>
            </div>
            <div class="space-y-4 p-4">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Label</label>
                <input
                  v-model="millerForm.label"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm disabled:bg-slate-50"
                  :disabled="millerModalMode === 'view'"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                <textarea
                  v-model="millerForm.desc"
                  rows="3"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm disabled:bg-slate-50"
                  :disabled="millerModalMode === 'view'"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select
                  v-model="millerForm.status"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm disabled:bg-slate-50"
                  :disabled="millerModalMode === 'view'"
                >
                  <option value="ACTIVE">ACTIVE</option>
                  <option value="INACTIVE">INACTIVE</option>
                </select>
              </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm hover:bg-slate-50"
                @click="millerModal = false"
              >
                {{ millerModalMode === "view" ? "Close" : "Cancel" }}
              </button>
              <button
                v-if="millerModalMode !== 'view'"
                type="button"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800"
                @click="handleMillerSave"
              >
                Save
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Smart filter modal -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="showSmartFilter"
          class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
          @click.self="showSmartFilter = false"
        >
          <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-2xl" @click.stop>
            <div class="border-b border-slate-100 px-4 py-3">
              <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
            </div>
            <div class="space-y-4 p-4">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Level</label>
                <input v-model="smartFilter.level" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Budget code</label>
                <input v-model="smartFilter.code" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                <input
                  v-model="smartFilter.description"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select v-model="smartFilter.status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
                  <option value="">Any</option>
                  <option value="ACTIVE">ACTIVE</option>
                  <option value="INACTIVE">INACTIVE</option>
                </select>
              </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm hover:bg-slate-50"
                @click="handleSmartFilterReset"
              >
                Reset
              </button>
              <button
                type="button"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800"
                @click="handleSmartFilterOk"
              >
                OK
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AdminLayout>
</template>
