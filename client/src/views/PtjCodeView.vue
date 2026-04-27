<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from "vue";
import { Download, Eye, FileDown, FileSpreadsheet, MoreVertical, Pencil, Plus, Search, Trash2, X } from "lucide-vue-next";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { createPtjCode, deletePtjCode, listPtjCodeLevel, updatePtjCode } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { PtjCodeInput, PtjCodeRow } from "@/types";

type LevelKey = "level1" | "level2" | "level3" | "level4";
type ModalMode = "create" | "edit" | "view";

const toast = useToast();

const columns: { key: LevelKey; level: number; title: string }[] = [
  { key: "level1", level: 1, title: "PTJ LEVEL 1" },
  { key: "level2", level: 2, title: "PTJ LEVEL 2" },
  { key: "level3", level: 3, title: "PTJ LEVEL 3" },
  { key: "level4", level: 4, title: "PTJ LEVEL 4" },
];

const listData = reactive<Record<LevelKey, PtjCodeRow[]>>({
  level1: [],
  level2: [],
  level3: [],
  level4: [],
});

const selected = reactive<Record<LevelKey, PtjCodeRow | null>>({
  level1: null,
  level2: null,
  level3: null,
  level4: null,
});

const searchKeywords = reactive<Record<LevelKey, string>>({
  level1: "",
  level2: "",
  level3: "",
  level4: "",
});

const loading = reactive<Record<LevelKey, boolean>>({
  level1: false,
  level2: false,
  level3: false,
  level4: false,
});

const showModal = ref(false);
const modalMode = ref<ModalMode>("create");
const modalLevel = ref<LevelKey>("level1");
const modalCode = ref("");

const actionMenuOpen = reactive<Record<LevelKey, boolean>>({
  level1: false,
  level2: false,
  level3: false,
  level4: false,
});

const form = reactive<PtjCodeInput>({
  ounCode: "",
  ounDesc: "",
  ounStatus: "ACTIVE",
  orgCode: "",
  ounLevel: 1,
  ounCodeParent: null,
  ounDescBi: null,
  orgDesc: null,
  ounAddress: null,
  ounState: null,
  stStaffIdHead: null,
  stStaffIdSuperior: null,
  ounTelNo: null,
  ounFaxNo: null,
  tanggungStartDate: null,
  tanggungEndDate: null,
  ounShortname: null,
  ounRegion: null,
  cnyCountryCode: null,
});

const columnWidths = reactive<Record<LevelKey, number>>({
  level1: 320,
  level2: 320,
  level3: 320,
  level4: 320,
});

const visibleColumns = computed(() => {
  const keys: LevelKey[] = ["level1"];
  if (selected.level1) keys.push("level2");
  if (selected.level2) keys.push("level3");
  if (selected.level3) keys.push("level4");
  return keys;
});

const selectionPath = computed(() => [selected.level1?.ounCode, selected.level2?.ounCode, selected.level3?.ounCode, selected.level4?.ounCode].filter(Boolean));

function parentCodeForLevel(level: LevelKey): string | null {
  if (level === "level2") return selected.level1?.ounCode ?? null;
  if (level === "level3") return selected.level2?.ounCode ?? null;
  if (level === "level4") return selected.level3?.ounCode ?? null;
  return null;
}

function clearDownstream(level: LevelKey) {
  if (level === "level1") {
    selected.level2 = null;
    selected.level3 = null;
    selected.level4 = null;
    listData.level2 = [];
    listData.level3 = [];
    listData.level4 = [];
  } else if (level === "level2") {
    selected.level3 = null;
    selected.level4 = null;
    listData.level3 = [];
    listData.level4 = [];
  } else if (level === "level3") {
    selected.level4 = null;
    listData.level4 = [];
  }
}

async function fetchLevel(level: LevelKey) {
  const col = columns.find((item) => item.key === level);
  if (!col) return;

  const parent = parentCodeForLevel(level);
  if (col.level > 1 && !parent) {
    listData[level] = [];
    return;
  }

  loading[level] = true;
  try {
    const params = new URLSearchParams({ level: String(col.level) });
    if (parent) params.set("ounCodeParent", parent);
    if (searchKeywords[level].trim()) params.set("search", searchKeywords[level].trim());
    const response = await listPtjCodeLevel(`?${params.toString()}`);
    listData[level] = response.data ?? [];
  } catch (error) {
    toast.error("Load failed", error instanceof Error ? error.message : "Unable to load PTJ codes.");
  } finally {
    loading[level] = false;
  }
}

async function selectRow(level: LevelKey, row: PtjCodeRow) {
  const isSame = selected[level]?.ounCode === row.ounCode;
  selected[level] = isSame ? null : row;
  clearDownstream(level);

  if (!isSame) {
    if (level === "level1") await fetchLevel("level2");
    if (level === "level2") await fetchLevel("level3");
    if (level === "level3") await fetchLevel("level4");
  }
}

function resetForm() {
  form.ounCode = "";
  form.ounDesc = "";
  form.ounStatus = "ACTIVE";
  form.orgCode = "";
  form.ounLevel = 1;
  form.ounCodeParent = null;
  form.ounDescBi = null;
  form.orgDesc = null;
  form.ounAddress = null;
  form.ounState = null;
  form.stStaffIdHead = null;
  form.stStaffIdSuperior = null;
  form.ounTelNo = null;
  form.ounFaxNo = null;
  form.tanggungStartDate = null;
  form.tanggungEndDate = null;
  form.ounShortname = null;
  form.ounRegion = null;
  form.cnyCountryCode = null;
}

function openCreate(level: LevelKey) {
  modalMode.value = "create";
  modalLevel.value = level;
  modalCode.value = "";
  resetForm();

  const levelNumber = columns.find((col) => col.key === level)?.level ?? 1;
  form.ounLevel = levelNumber;
  form.ounCodeParent = parentCodeForLevel(level);

  showModal.value = true;
}

function openEditView(level: LevelKey, mode: ModalMode, row: PtjCodeRow) {
  modalMode.value = mode;
  modalLevel.value = level;
  modalCode.value = row.ounCode;
  resetForm();

  form.ounCode = row.ounCode;
  form.ounDesc = row.ounDesc;
  form.ounStatus = row.ounStatus;
  form.orgCode = row.orgCode ?? "";
  form.ounLevel = row.ounLevel;
  form.ounCodeParent = row.ounCodeParent;
  form.ounDescBi = row.ounDescBi;
  form.orgDesc = row.orgDesc;
  form.ounAddress = row.ounAddress;
  form.ounState = row.ounState;
  form.stStaffIdHead = row.stStaffIdHead;
  form.stStaffIdSuperior = row.stStaffIdSuperior;
  form.ounTelNo = row.ounTelNo;
  form.ounFaxNo = row.ounFaxNo;
  form.tanggungStartDate = row.tanggungStartDate;
  form.tanggungEndDate = row.tanggungEndDate;
  form.ounShortname = row.ounShortname;
  form.ounRegion = row.ounRegion;
  form.cnyCountryCode = row.cnyCountryCode;

  showModal.value = true;
}

async function saveModal() {
  if (!form.ounCode.trim() && modalMode.value === "create") {
    toast.error("Validation failed", "PTJ code is required.");
    return;
  }
  if (!form.ounDesc.trim() || !form.orgCode.trim()) {
    toast.error("Validation failed", "Description and Org Code are required.");
    return;
  }

  try {
    if (modalMode.value === "create") {
      await createPtjCode({
        ...form,
        ounCode: form.ounCode.trim().toUpperCase(),
        ounDesc: form.ounDesc.trim(),
        orgCode: form.orgCode.trim().toUpperCase(),
        ounCodeParent: form.ounCodeParent?.trim()?.toUpperCase() ?? null,
      });
      toast.success("Insert successful");
    } else {
      await updatePtjCode(modalCode.value, {
        ...form,
        ounDesc: form.ounDesc.trim(),
        orgCode: form.orgCode.trim().toUpperCase(),
        ounCodeParent: form.ounCodeParent?.trim()?.toUpperCase() ?? null,
      });
      toast.success("Update successful");
    }

    showModal.value = false;
    await fetchLevel(modalLevel.value);
  } catch (error) {
    toast.error("Save failed", error instanceof Error ? error.message : "Unable to save PTJ.");
  }
}

async function removeRow(level: LevelKey, row: PtjCodeRow) {
  if (!window.confirm(`Delete PTJ ${row.ounCode}?`)) return;
  try {
    await deletePtjCode(row.ounCode);
    toast.success("Delete successful");
    if (selected[level]?.ounCode === row.ounCode) {
      selected[level] = null;
      clearDownstream(level);
    }
    await fetchLevel(level);
  } catch (error) {
    toast.error("Delete failed", error instanceof Error ? error.message : "Unable to delete PTJ.");
  }
}

function rowLabel(row: PtjCodeRow) {
  return `${row.ounCode} - ${row.ounDesc}`;
}

function formatDateHeader() {
  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: true,
  }).format(new Date());
}

function exportData(level: LevelKey) {
  const title = columns.find((col) => col.key === level)?.title ?? "PTJ";
  const rows = listData[level];
  if (!rows.length) {
    toast.info("No data", "There is no data to export.");
    return null;
  }

  const headers = ["No.", "PTJ Code", "Description (Malay)", "Description (English)", "Status", "Country"];
  if (level !== "level1") headers.splice(2, 0, "Parent Code");

  const body = rows.map((row, idx) => {
    const values = [idx + 1, row.ounCode];
    if (level !== "level1") values.push(row.ounCodeParent ?? "");
    values.push(row.ounDesc, row.ounDescBi ?? "", row.ounStatus, row.cnyCountryDesc ?? "");
    return values;
  });

  return { title, headers, body };
}

function exportCsv(level: LevelKey) {
  const payload = exportData(level);
  if (!payload) return;
  const escape = (value: unknown) => `"${String(value ?? "").replace(/"/g, "\"\"")}"`;
  const lines = [
    `Date : ${formatDateHeader()}`,
    payload.title,
    "",
    payload.headers.map(escape).join(","),
    ...payload.body.map((row) => row.map(escape).join(",")),
  ];
  const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `${payload.title.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.csv`;
  link.click();
  URL.revokeObjectURL(link.href);
}

async function exportExcel(level: LevelKey) {
  const payload = exportData(level);
  if (!payload) return;
  try {
    const ExcelJS = await import("exceljs");
    const workbook = new ExcelJS.Workbook();
    const ws = workbook.addWorksheet(payload.title);
    ws.addRow([`Date : ${formatDateHeader()}`]);
    ws.addRow([payload.title]);
    ws.addRow([]);
    ws.addRow(payload.headers);
    payload.body.forEach((row) => ws.addRow(row));
    ws.getRow(4).font = { bold: true };
    ws.getRow(4).fill = { type: "pattern", pattern: "solid", fgColor: { argb: "FFE5E7EB" } };
    ws.columns.forEach((col, index) => {
      col.width = index === 0 ? 8 : 25;
    });
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `${payload.title.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.xlsx`;
    link.click();
    URL.revokeObjectURL(link.href);
  } catch {
    toast.error("Export failed", "Excel export needs exceljs installed.");
  }
}

function exportPdf(level: LevelKey) {
  const payload = exportData(level);
  if (!payload) return;
  const doc = new jsPDF({ orientation: "portrait", unit: "pt", format: "a4" });
  doc.setFontSize(10);
  doc.text(`Date : ${formatDateHeader()}`, 555, 40, { align: "right" });
  doc.setFontSize(13);
  doc.text(payload.title, 297, 68, { align: "center" });
  autoTable(doc, {
    head: [payload.headers],
    body: payload.body,
    startY: 90,
    styles: { fontSize: 9 },
    headStyles: { fillColor: [59, 130, 246], textColor: 255 },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    columnStyles: { 0: { cellWidth: 36 } },
  });
  doc.save(`${payload.title.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.pdf`);
}

function startResize(event: MouseEvent, key: LevelKey) {
  const startX = event.clientX;
  const startW = columnWidths[key];
  const onMove = (moveEvent: MouseEvent) => {
    columnWidths[key] = Math.max(260, Math.min(520, startW + (moveEvent.clientX - startX)));
  };
  const onUp = () => {
    window.removeEventListener("mousemove", onMove);
    window.removeEventListener("mouseup", onUp);
  };
  window.addEventListener("mousemove", onMove);
  window.addEventListener("mouseup", onUp);
}

const searchTimers: Partial<Record<LevelKey, ReturnType<typeof setTimeout>>> = {};

function scheduleSearch(level: LevelKey) {
  const prev = searchTimers[level];
  if (prev) clearTimeout(prev);
  searchTimers[level] = setTimeout(() => {
    searchTimers[level] = undefined;
    void fetchLevel(level);
  }, 350);
}

function runSearchNow(level: LevelKey) {
  const prev = searchTimers[level];
  if (prev) clearTimeout(prev);
  searchTimers[level] = undefined;
  void fetchLevel(level);
}

function clearSearch(level: LevelKey) {
  searchKeywords[level] = "";
  runSearchNow(level);
}

onMounted(async () => {
  await fetchLevel("level1");
});

onUnmounted(() => {
  (Object.keys(searchTimers) as LevelKey[]).forEach((k) => {
    const t = searchTimers[k];
    if (t) clearTimeout(t);
  });
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">Setup and Maintenance / General Ledger Structure / PTJ Code</h1>
      <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h1 class="text-lg font-semibold text-slate-900">PTJ Code</h1>
          <div class="flex flex-wrap items-center gap-2">
            <span v-for="segment in selectionPath" :key="segment" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ segment }}</span>
          </div>
        </div>

        <div class="mt-4 flex gap-3 overflow-x-auto pb-2">
          <section
            v-for="col in columns.filter((item) => visibleColumns.includes(item.key))"
            :key="col.key"
            class="relative rounded-lg border border-slate-200"
            :style="{ width: `${columnWidths[col.key]}px`, minWidth: `${columnWidths[col.key]}px` }"
          >
            <header class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
              <p class="text-xs font-bold tracking-wide text-slate-700">{{ col.title }}</p>
              <div class="relative">
                <button class="rounded p-1 text-slate-600 hover:bg-slate-100" @click="actionMenuOpen[col.key] = !actionMenuOpen[col.key]"><MoreVertical class="h-4 w-4" /></button>
                <div v-if="actionMenuOpen[col.key]" class="absolute right-0 z-20 mt-1 w-36 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                  <button class="block w-full px-3 py-1.5 text-left text-xs hover:bg-slate-50" @click="actionMenuOpen[col.key] = false; exportPdf(col.key)">Export PDF</button>
                  <button class="block w-full px-3 py-1.5 text-left text-xs hover:bg-slate-50" @click="actionMenuOpen[col.key] = false; exportCsv(col.key)">Export CSV</button>
                  <button class="block w-full px-3 py-1.5 text-left text-xs hover:bg-slate-50" @click="actionMenuOpen[col.key] = false; exportExcel(col.key)">Export Excel</button>
                </div>
              </div>
            </header>

            <div class="border-b border-slate-100 p-2">
              <div class="relative">
                <Search class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="searchKeywords[col.key]"
                  type="search"
                  class="w-full rounded-lg border border-slate-300 py-1.5 pl-7 pr-8 text-sm"
                  placeholder="Filter rows..."
                  @input="scheduleSearch(col.key)"
                  @keyup.enter="runSearchNow(col.key)"
                />
                <button
                  v-if="searchKeywords[col.key]"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  @click="clearSearch(col.key)"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>

            <div class="max-h-[460px] overflow-auto p-2">
              <p v-if="loading[col.key]" class="p-2 text-sm text-slate-500">Loading...</p>
              <ul v-else class="space-y-1">
                <li
                  v-for="row in listData[col.key]"
                  :key="row.ounCode"
                  class="rounded-md border border-slate-200 bg-white p-2 text-sm hover:bg-slate-50"
                  :class="{ 'ring-2 ring-blue-400': selected[col.key]?.ounCode === row.ounCode }"
                >
                  <div class="flex items-start justify-between gap-2">
                    <button class="flex-1 text-left" @click="selectRow(col.key, row)">{{ rowLabel(row) }}</button>
                    <div class="flex items-center gap-1">
                      <button class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="openEditView(col.key, 'view', row)"><Eye class="h-3.5 w-3.5" /></button>
                      <button class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="openEditView(col.key, 'edit', row)"><Pencil class="h-3.5 w-3.5" /></button>
                      <button class="rounded p-1 text-rose-600 hover:bg-rose-50" @click="removeRow(col.key, row)"><Trash2 class="h-3.5 w-3.5" /></button>
                    </div>
                  </div>
                </li>
              </ul>
            </div>

            <div class="border-t border-slate-100 p-2">
              <button class="inline-flex items-center gap-1.5 rounded bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800" @click="openCreate(col.key)">
                <Plus class="h-3.5 w-3.5" />
                Add
              </button>
            </div>

            <button class="absolute right-0 top-0 h-full w-1 cursor-col-resize bg-transparent hover:bg-blue-200/60" @mousedown.prevent="startResize($event, col.key)" />
          </section>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="showModal = false">
        <div class="w-full max-w-3xl rounded-lg border border-slate-200 bg-white shadow-xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">{{ modalMode === "create" ? "Add" : modalMode === "edit" ? "Edit" : "View" }} PTJ</h3>
          </div>
          <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">PTJ Code *</label>
              <input v-model="form.ounCode" class="w-full rounded border px-3 py-2 text-sm" :disabled="modalMode !== 'create'" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Level</label>
              <input :value="form.ounLevel" class="w-full rounded border px-3 py-2 text-sm" disabled />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Description (Malay) *</label>
              <input v-model="form.ounDesc" class="w-full rounded border px-3 py-2 text-sm" :disabled="modalMode === 'view'" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Description (English)</label>
              <input v-model="form.ounDescBi" class="w-full rounded border px-3 py-2 text-sm" :disabled="modalMode === 'view'" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Org Code *</label>
              <input v-model="form.orgCode" class="w-full rounded border px-3 py-2 text-sm" :disabled="modalMode === 'view'" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status *</label>
              <select v-model="form.ounStatus" class="w-full rounded border px-3 py-2 text-sm" :disabled="modalMode === 'view'">
                <option value="ACTIVE">ACTIVE</option>
                <option value="INACTIVE">INACTIVE</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Parent Code</label>
              <input v-model="form.ounCodeParent" class="w-full rounded border px-3 py-2 text-sm" :disabled="modalMode === 'view' || modalMode === 'create'" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Country Code</label>
              <input v-model="form.cnyCountryCode" class="w-full rounded border px-3 py-2 text-sm" :disabled="modalMode === 'view'" />
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button class="rounded border border-slate-300 px-4 py-2 text-sm" @click="showModal = false">Close</button>
            <button v-if="modalMode !== 'view'" class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="saveModal">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
