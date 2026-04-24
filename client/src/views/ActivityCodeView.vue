<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from "vue";
import { Download, Eye, FileDown, FileSpreadsheet, Pencil, Plus, Search, Trash2, X } from "lucide-vue-next";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

import AdminLayout from "@/layouts/AdminLayout.vue";
import {
  createActivityGroup,
  createActivitySubgroup,
  createActivitySubsiri,
  createActivityType,
  deleteActivityGroup,
  deleteActivitySubgroup,
  deleteActivitySubsiri,
  deleteActivityType,
  listActivityCodeLevel,
  updateActivityGroup,
  updateActivitySubgroup,
  updateActivitySubsiri,
  updateActivityType,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { ActivityGroupRow, ActivitySubgroupRow, ActivitySubsiriRow, ActivityTypeRow } from "@/types";

type LevelKey = "group" | "subgroup" | "subsiri" | "activityType";
type ModalMode = "create" | "edit" | "view";

const toast = useToast();

const listData = reactive({
  group: [] as ActivityGroupRow[],
  subgroup: [] as ActivitySubgroupRow[],
  subsiri: [] as ActivitySubsiriRow[],
  activityType: [] as ActivityTypeRow[],
});

const selected = reactive({
  group: null as ActivityGroupRow | null,
  subgroup: null as ActivitySubgroupRow | null,
  subsiri: null as ActivitySubsiriRow | null,
  activityType: null as ActivityTypeRow | null,
});

const searchKeywords = reactive<Record<LevelKey, string>>({
  group: "",
  subgroup: "",
  subsiri: "",
  activityType: "",
});

const loading = reactive<Record<LevelKey, boolean>>({
  group: false,
  subgroup: false,
  subsiri: false,
  activityType: false,
});

const modalVisible = ref(false);
const modalLevel = ref<LevelKey>("group");
const modalMode = ref<ModalMode>("create");
const currentCode = ref<string | number>("");

const groupForm = reactive({ activityGroupCode: "", activityGroupDesc: "" });
const subgroupForm = reactive({ activityGroupCode: "", activitySubgroupCode: "", activitySubgroupDesc: "" });
const subsiriForm = reactive({ activityGroup: "", activitySubgroupCode: "", activitySubsiriCode: "", activitySubsiriDesc: "", activitySubsiriDescEng: "" });
const activityTypeForm = reactive({
  activityGroupCode: "",
  activitySubgroupCode: "",
  activitySubsiriCode: "",
  atActivityCode: "",
  atActivityDescriptionBm: "",
  atActivityDescriptionEn: "",
  atStatus: "ACTIVE" as "ACTIVE" | "INACTIVE",
});

const columns = [
  { key: "group" as const, title: "ACTIVITY GROUP" },
  { key: "subgroup" as const, title: "ACTIVITY SUBGROUP" },
  { key: "subsiri" as const, title: "ACTIVITY SUBSIRI" },
  { key: "activityType" as const, title: "ACTIVITY TYPE" },
];

const visibleColumns = computed(() => {
  const keys: LevelKey[] = ["group"];
  if (selected.group) keys.push("subgroup");
  if (selected.subgroup) keys.push("subsiri");
  if (selected.subsiri) keys.push("activityType");
  return keys;
});

const selectionPath = computed(() => [
  selected.group?.activityGroupCode,
  selected.subgroup?.activitySubgroupCode,
  selected.subsiri?.activitySubsiriCode,
  selected.activityType?.atActivityCode,
].filter(Boolean));

async function fetchGroup() {
  loading.group = true;
  try {
    const params = new URLSearchParams({ level: "0" });
    if (searchKeywords.group.trim()) params.set("search", searchKeywords.group.trim());
    const res = await listActivityCodeLevel(`?${params.toString()}`);
    listData.group = (res.data as ActivityGroupRow[]) ?? [];
  } catch (error) {
    toast.error("Load failed", error instanceof Error ? error.message : "Unable to load activity groups.");
  } finally {
    loading.group = false;
  }
}

async function fetchSubgroup() {
  if (!selected.group) return;
  loading.subgroup = true;
  try {
    const params = new URLSearchParams({ level: "1", activityGroupCode: selected.group.activityGroupCode });
    if (searchKeywords.subgroup.trim()) params.set("search", searchKeywords.subgroup.trim());
    const res = await listActivityCodeLevel(`?${params.toString()}`);
    listData.subgroup = (res.data as ActivitySubgroupRow[]) ?? [];
  } catch (error) {
    toast.error("Load failed", error instanceof Error ? error.message : "Unable to load activity subgroups.");
  } finally {
    loading.subgroup = false;
  }
}

async function fetchSubsiri() {
  if (!selected.group || !selected.subgroup) return;
  loading.subsiri = true;
  try {
    const params = new URLSearchParams({
      level: "2",
      activityGroupCode: selected.group.activityGroupCode,
      activitySubgroupCode: selected.subgroup.activitySubgroupCode,
    });
    if (searchKeywords.subsiri.trim()) params.set("search", searchKeywords.subsiri.trim());
    const res = await listActivityCodeLevel(`?${params.toString()}`);
    listData.subsiri = (res.data as ActivitySubsiriRow[]) ?? [];
  } catch (error) {
    toast.error("Load failed", error instanceof Error ? error.message : "Unable to load activity subsiri.");
  } finally {
    loading.subsiri = false;
  }
}

async function fetchActivityType() {
  if (!selected.group || !selected.subgroup || !selected.subsiri) return;
  loading.activityType = true;
  try {
    const params = new URLSearchParams({
      level: "3",
      activityGroupCode: selected.group.activityGroupCode,
      activitySubgroupCode: selected.subgroup.activitySubgroupCode,
      activitySubsiriCode: selected.subsiri.activitySubsiriCode,
    });
    if (searchKeywords.activityType.trim()) params.set("search", searchKeywords.activityType.trim());
    const res = await listActivityCodeLevel(`?${params.toString()}`);
    listData.activityType = (res.data as ActivityTypeRow[]) ?? [];
  } catch (error) {
    toast.error("Load failed", error instanceof Error ? error.message : "Unable to load activity type.");
  } finally {
    loading.activityType = false;
  }
}

function clearFrom(level: LevelKey) {
  if (level === "group") {
    selected.subgroup = null;
    selected.subsiri = null;
    selected.activityType = null;
    listData.subgroup = [];
    listData.subsiri = [];
    listData.activityType = [];
  } else if (level === "subgroup") {
    selected.subsiri = null;
    selected.activityType = null;
    listData.subsiri = [];
    listData.activityType = [];
  } else if (level === "subsiri") {
    selected.activityType = null;
    listData.activityType = [];
  }
}

async function selectRow(level: LevelKey, row: ActivityGroupRow | ActivitySubgroupRow | ActivitySubsiriRow | ActivityTypeRow) {
  if (level === "group") {
    const same = selected.group?.activityGroupCode === (row as ActivityGroupRow).activityGroupCode;
    selected.group = same ? null : (row as ActivityGroupRow);
    clearFrom("group");
    if (!same && selected.group) await fetchSubgroup();
  } else if (level === "subgroup") {
    const same = selected.subgroup?.activitySubgroupCode === (row as ActivitySubgroupRow).activitySubgroupCode;
    selected.subgroup = same ? null : (row as ActivitySubgroupRow);
    clearFrom("subgroup");
    if (!same && selected.subgroup) await fetchSubsiri();
  } else if (level === "subsiri") {
    const same = selected.subsiri?.activitySubsiriCode === (row as ActivitySubsiriRow).activitySubsiriCode;
    selected.subsiri = same ? null : (row as ActivitySubsiriRow);
    clearFrom("subsiri");
    if (!same && selected.subsiri) await fetchActivityType();
  } else {
    selected.activityType = row as ActivityTypeRow;
  }
}

function openCreate(level: LevelKey) {
  modalLevel.value = level;
  modalMode.value = "create";
  currentCode.value = "";

  groupForm.activityGroupCode = "";
  groupForm.activityGroupDesc = "";
  subgroupForm.activityGroupCode = selected.group?.activityGroupCode ?? "";
  subgroupForm.activitySubgroupCode = "";
  subgroupForm.activitySubgroupDesc = "";
  subsiriForm.activityGroup = selected.group?.activityGroupCode ?? "";
  subsiriForm.activitySubgroupCode = selected.subgroup?.activitySubgroupCode ?? "";
  subsiriForm.activitySubsiriCode = "";
  subsiriForm.activitySubsiriDesc = "";
  subsiriForm.activitySubsiriDescEng = "";
  activityTypeForm.activityGroupCode = selected.group?.activityGroupCode ?? "";
  activityTypeForm.activitySubgroupCode = selected.subgroup?.activitySubgroupCode ?? "";
  activityTypeForm.activitySubsiriCode = selected.subsiri?.activitySubsiriCode ?? "";
  activityTypeForm.atActivityCode = "";
  activityTypeForm.atActivityDescriptionBm = "";
  activityTypeForm.atActivityDescriptionEn = "";
  activityTypeForm.atStatus = "ACTIVE";
  modalVisible.value = true;
}

function openViewOrEdit(level: LevelKey, mode: ModalMode, row: ActivityGroupRow | ActivitySubgroupRow | ActivitySubsiriRow | ActivityTypeRow) {
  modalLevel.value = level;
  modalMode.value = mode;

  if (level === "group") {
    const r = row as ActivityGroupRow;
    currentCode.value = r.activityGroupCode;
    groupForm.activityGroupCode = r.activityGroupCode;
    groupForm.activityGroupDesc = r.activityGroupDesc;
  } else if (level === "subgroup") {
    const r = row as ActivitySubgroupRow;
    currentCode.value = r.activitySubgroupCode;
    subgroupForm.activityGroupCode = r.activityGroupCode;
    subgroupForm.activitySubgroupCode = r.activitySubgroupCode;
    subgroupForm.activitySubgroupDesc = r.activitySubgroupDesc;
  } else if (level === "subsiri") {
    const r = row as ActivitySubsiriRow;
    currentCode.value = r.activitySubsiriCode;
    subsiriForm.activityGroup = r.activityGroup;
    subsiriForm.activitySubgroupCode = r.activitySubgroupCode;
    subsiriForm.activitySubsiriCode = r.activitySubsiriCode;
    subsiriForm.activitySubsiriDesc = r.activitySubsiriDesc;
    subsiriForm.activitySubsiriDescEng = r.activitySubsiriDescEng ?? "";
  } else {
    const r = row as ActivityTypeRow;
    currentCode.value = r.atActivityId;
    activityTypeForm.activityGroupCode = r.activityGroupCode;
    activityTypeForm.activitySubgroupCode = r.activitySubgroupCode;
    activityTypeForm.activitySubsiriCode = r.activitySubsiriCode;
    activityTypeForm.atActivityCode = r.atActivityCode;
    activityTypeForm.atActivityDescriptionBm = r.atActivityDescriptionBm;
    activityTypeForm.atActivityDescriptionEn = r.atActivityDescriptionEn ?? "";
    activityTypeForm.atStatus = r.atStatus;
  }

  modalVisible.value = true;
}

async function removeRow(level: LevelKey, row: ActivityGroupRow | ActivitySubgroupRow | ActivitySubsiriRow | ActivityTypeRow) {
  if (!window.confirm("Delete selected record?")) return;

  try {
    if (level === "group") {
      await deleteActivityGroup((row as ActivityGroupRow).activityGroupCode);
      await fetchGroup();
    } else if (level === "subgroup") {
      const r = row as ActivitySubgroupRow;
      await deleteActivitySubgroup(r.activitySubgroupCode, r.activityGroupCode);
      await fetchSubgroup();
    } else if (level === "subsiri") {
      const r = row as ActivitySubsiriRow;
      await deleteActivitySubsiri(r.activitySubsiriCode, r.activityGroup, r.activitySubgroupCode);
      await fetchSubsiri();
    } else {
      await deleteActivityType((row as ActivityTypeRow).atActivityId);
      await fetchActivityType();
    }
    toast.success("Delete successful");
  } catch (error) {
    toast.error("Delete failed", error instanceof Error ? error.message : "Unable to delete selected item.");
  }
}

async function saveModal() {
  try {
    if (modalLevel.value === "group") {
      if (!groupForm.activityGroupCode.trim() || !groupForm.activityGroupDesc.trim()) {
        toast.error("Validation failed", "Group code and description are required.");
        return;
      }
      if (modalMode.value === "create") {
        await createActivityGroup(groupForm);
      } else {
        await updateActivityGroup(String(currentCode.value), { activityGroupDesc: groupForm.activityGroupDesc });
      }
      await fetchGroup();
    } else if (modalLevel.value === "subgroup") {
      if (!subgroupForm.activityGroupCode.trim() || !subgroupForm.activitySubgroupCode.trim() || !subgroupForm.activitySubgroupDesc.trim()) {
        toast.error("Validation failed", "Group, subgroup code, and description are required.");
        return;
      }
      if (modalMode.value === "create") {
        await createActivitySubgroup(subgroupForm);
      } else {
        await updateActivitySubgroup(String(currentCode.value), {
          activityGroupCode: subgroupForm.activityGroupCode,
          activitySubgroupDesc: subgroupForm.activitySubgroupDesc,
        });
      }
      await fetchSubgroup();
    } else if (modalLevel.value === "subsiri") {
      if (!subsiriForm.activityGroup.trim() || !subsiriForm.activitySubgroupCode.trim() || !subsiriForm.activitySubsiriCode.trim() || !subsiriForm.activitySubsiriDesc.trim()) {
        toast.error("Validation failed", "Group, subgroup, subsiri code, and description are required.");
        return;
      }
      if (modalMode.value === "create") {
        await createActivitySubsiri(subsiriForm);
      } else {
        await updateActivitySubsiri(String(currentCode.value), {
          activityGroup: subsiriForm.activityGroup,
          activitySubgroupCode: subsiriForm.activitySubgroupCode,
          activitySubsiriDesc: subsiriForm.activitySubsiriDesc,
          activitySubsiriDescEng: subsiriForm.activitySubsiriDescEng,
        });
      }
      await fetchSubsiri();
    } else {
      if (!activityTypeForm.atActivityDescriptionBm.trim()) {
        toast.error("Validation failed", "Description (Malay) is required.");
        return;
      }
      if (modalMode.value === "create") {
        if (!activityTypeForm.atActivityCode.trim()) {
          toast.error("Validation failed", "Activity code is required.");
          return;
        }
        await createActivityType(activityTypeForm);
      } else {
        await updateActivityType(Number(currentCode.value), {
          atActivityDescriptionBm: activityTypeForm.atActivityDescriptionBm,
          atActivityDescriptionEn: activityTypeForm.atActivityDescriptionEn,
          atStatus: activityTypeForm.atStatus,
        });
      }
      await fetchActivityType();
    }

    toast.success("Save successful");
    modalVisible.value = false;
  } catch (error) {
    toast.error("Save failed", error instanceof Error ? error.message : "Unable to save.");
  }
}

function getRows(level: LevelKey) {
  if (level === "group") return listData.group;
  if (level === "subgroup") return listData.subgroup;
  if (level === "subsiri") return listData.subsiri;
  return listData.activityType;
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

function exportPayload(level: LevelKey) {
  const rows = getRows(level);
  if (!rows.length) {
    toast.info("No data", "There is no data to export.");
    return null;
  }

  if (level === "group") {
    return {
      title: "ACTIVITY GROUP",
      headers: ["No.", "Group Code", "Description"],
      body: rows.map((r, i) => [i + 1, (r as ActivityGroupRow).activityGroupCode, (r as ActivityGroupRow).activityGroupDesc]),
    };
  }

  if (level === "subgroup") {
    return {
      title: "ACTIVITY SUBGROUP",
      headers: ["No.", "Group Code", "Subgroup Code", "Description"],
      body: rows.map((r, i) => [i + 1, (r as ActivitySubgroupRow).activityGroupCode, (r as ActivitySubgroupRow).activitySubgroupCode, (r as ActivitySubgroupRow).activitySubgroupDesc]),
    };
  }

  if (level === "subsiri") {
    return {
      title: "ACTIVITY SUBSIRI",
      headers: ["No.", "Subsiri Code", "Description (Malay)", "Description (English)"],
      body: rows.map((r, i) => [i + 1, (r as ActivitySubsiriRow).activitySubsiriCode, (r as ActivitySubsiriRow).activitySubsiriDesc, (r as ActivitySubsiriRow).activitySubsiriDescEng ?? ""]),
    };
  }

  return {
    title: "ACTIVITY TYPE",
    headers: ["No.", "Activity Code", "Description (Malay)", "Description (English)", "Status"],
    body: rows.map((r, i) => [i + 1, (r as ActivityTypeRow).atActivityCode, (r as ActivityTypeRow).atActivityDescriptionBm, (r as ActivityTypeRow).atActivityDescriptionEn ?? "", (r as ActivityTypeRow).atStatus]),
  };
}

function exportCSV(level: LevelKey) {
  const payload = exportPayload(level);
  if (!payload) return;
  const now = formatDateHeader();
  const escape = (value: unknown) => `"${String(value ?? "").replace(/"/g, "\"\"")}"`;
  const lines = [
    `Date : ${now}`,
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
  const payload = exportPayload(level);
  if (!payload) return;
  const ExcelJS = await import("exceljs");
  const wb = new ExcelJS.Workbook();
  const ws = wb.addWorksheet(payload.title);
  ws.addRow([`Date : ${formatDateHeader()}`]);
  ws.addRow([payload.title]);
  ws.addRow([]);
  ws.addRow(payload.headers);
  payload.body.forEach((row) => ws.addRow(row));
  ws.getRow(4).font = { bold: true };
  ws.columns.forEach((col, idx) => {
    col.width = idx === 0 ? 8 : 25;
  });
  const buffer = await wb.xlsx.writeBuffer();
  const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `${payload.title.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.xlsx`;
  link.click();
  URL.revokeObjectURL(link.href);
}

function exportPDF(level: LevelKey) {
  const payload = exportPayload(level);
  if (!payload) return;
  const doc = new jsPDF({ orientation: "portrait", unit: "pt", format: "a4" });
  doc.setFontSize(10);
  doc.text(`Date : ${formatDateHeader()}`, 40, 40);
  doc.setFontSize(13);
  doc.text(payload.title, 297, 68, { align: "center" });
  autoTable(doc, {
    head: [payload.headers],
    body: payload.body,
    startY: 90,
    styles: { fontSize: 9 },
    headStyles: { fillColor: [41, 98, 255], textColor: 255 },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    columnStyles: { 0: { cellWidth: 36 } },
  });
  doc.save(`${payload.title.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.pdf`);
}

function rowKey(level: LevelKey, row: ActivityGroupRow | ActivitySubgroupRow | ActivitySubsiriRow | ActivityTypeRow) {
  if (level === "group") return (row as ActivityGroupRow).activityGroupCode;
  if (level === "subgroup") return `${(row as ActivitySubgroupRow).activityGroupCode}:${(row as ActivitySubgroupRow).activitySubgroupCode}`;
  if (level === "subsiri") return `${(row as ActivitySubsiriRow).activityGroup}:${(row as ActivitySubsiriRow).activitySubgroupCode}:${(row as ActivitySubsiriRow).activitySubsiriCode}`;
  return String((row as ActivityTypeRow).atActivityId);
}

function rowLabel(level: LevelKey, row: ActivityGroupRow | ActivitySubgroupRow | ActivitySubsiriRow | ActivityTypeRow) {
  if (level === "group") return `${(row as ActivityGroupRow).activityGroupCode} - ${(row as ActivityGroupRow).activityGroupDesc}`;
  if (level === "subgroup") return `${(row as ActivitySubgroupRow).activitySubgroupCode} - ${(row as ActivitySubgroupRow).activitySubgroupDesc}`;
  if (level === "subsiri") return `${(row as ActivitySubsiriRow).activitySubsiriCode} - ${(row as ActivitySubsiriRow).activitySubsiriDesc}`;
  return `${(row as ActivityTypeRow).atActivityCode} - ${(row as ActivityTypeRow).atActivityDescriptionBm}`;
}

function onSearch(level: LevelKey) {
  if (level === "group") void fetchGroup();
  if (level === "subgroup") void fetchSubgroup();
  if (level === "subsiri") void fetchSubsiri();
  if (level === "activityType") void fetchActivityType();
}

const searchTimers: Partial<Record<LevelKey, ReturnType<typeof setTimeout>>> = {};

function scheduleSearch(level: LevelKey) {
  const prev = searchTimers[level];
  if (prev) clearTimeout(prev);
  searchTimers[level] = setTimeout(() => {
    searchTimers[level] = undefined;
    onSearch(level);
  }, 350);
}

function runSearchNow(level: LevelKey) {
  const prev = searchTimers[level];
  if (prev) clearTimeout(prev);
  searchTimers[level] = undefined;
  onSearch(level);
}

function clearSearch(level: LevelKey) {
  searchKeywords[level] = "";
  runSearchNow(level);
}

onMounted(() => {
  void fetchGroup();
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
      <h1 class="page-title">Setup and Maintenance / General Ledger Structure / Activity Code</h1>
      <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h1 class="text-lg font-semibold text-slate-900">Activity Code</h1>
          <div class="flex flex-wrap items-center gap-2">
            <span v-for="segment in selectionPath" :key="segment" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
              {{ segment }}
            </span>
          </div>
        </div>

        <div class="mt-4 grid gap-3" :style="`grid-template-columns: repeat(${visibleColumns.length}, minmax(240px, 1fr));`">
          <section v-for="col in columns.filter((col) => visibleColumns.includes(col.key))" :key="col.key" class="rounded-lg border border-slate-200">
            <header class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
              <p class="text-xs font-bold tracking-wide text-slate-700">{{ col.title }}</p>
              <div class="flex items-center gap-1">
                <button class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="exportPDF(col.key)"><Download class="h-4 w-4" /></button>
                <button class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="exportCSV(col.key)"><FileDown class="h-4 w-4" /></button>
                <button class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="exportExcel(col.key)"><FileSpreadsheet class="h-4 w-4" /></button>
                <button class="rounded p-1 text-slate-600 hover:bg-slate-100" @click="openCreate(col.key)"><Plus class="h-4 w-4" /></button>
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

            <div class="max-h-[420px] overflow-auto p-2">
              <p v-if="loading[col.key]" class="p-2 text-sm text-slate-500">Loading...</p>
              <ul v-else class="space-y-1">
                <li
                  v-for="row in getRows(col.key)"
                  :key="rowKey(col.key, row)"
                  class="rounded-md border border-slate-200 bg-white p-2 text-sm hover:bg-slate-50"
                  :class="{
                    'ring-2 ring-blue-400':
                      (col.key === 'group' && selected.group && selected.group.activityGroupCode === (row as ActivityGroupRow).activityGroupCode) ||
                      (col.key === 'subgroup' && selected.subgroup && selected.subgroup.activitySubgroupCode === (row as ActivitySubgroupRow).activitySubgroupCode) ||
                      (col.key === 'subsiri' && selected.subsiri && selected.subsiri.activitySubsiriCode === (row as ActivitySubsiriRow).activitySubsiriCode) ||
                      (col.key === 'activityType' && selected.activityType && selected.activityType.atActivityId === (row as ActivityTypeRow).atActivityId),
                  }"
                >
                  <div class="flex items-start justify-between gap-2">
                    <button class="flex-1 text-left" @click="selectRow(col.key, row)">{{ rowLabel(col.key, row) }}</button>
                    <div class="flex items-center gap-1">
                      <button class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="openViewOrEdit(col.key, 'view', row)"><Eye class="h-3.5 w-3.5" /></button>
                      <button class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="openViewOrEdit(col.key, 'edit', row)"><Pencil class="h-3.5 w-3.5" /></button>
                      <button class="rounded p-1 text-rose-600 hover:bg-rose-50" @click="removeRow(col.key, row)"><Trash2 class="h-3.5 w-3.5" /></button>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
          </section>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="modalVisible" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="modalVisible = false">
        <div class="w-full max-w-xl rounded-lg border border-slate-200 bg-white shadow-xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">{{ modalMode === "create" ? "Add" : modalMode === "edit" ? "Edit" : "View" }} {{ modalLevel }}</h3>
          </div>
          <div class="space-y-3 p-4 text-sm">
            <template v-if="modalLevel === 'group'">
              <input v-model="groupForm.activityGroupCode" class="w-full rounded border px-3 py-2" placeholder="Group code" :disabled="modalMode !== 'create'" />
              <input v-model="groupForm.activityGroupDesc" class="w-full rounded border px-3 py-2" placeholder="Description" :disabled="modalMode === 'view'" />
            </template>
            <template v-else-if="modalLevel === 'subgroup'">
              <input v-model="subgroupForm.activityGroupCode" class="w-full rounded border px-3 py-2" placeholder="Group code" :disabled="true" />
              <input v-model="subgroupForm.activitySubgroupCode" class="w-full rounded border px-3 py-2" placeholder="Subgroup code" :disabled="modalMode !== 'create'" />
              <input v-model="subgroupForm.activitySubgroupDesc" class="w-full rounded border px-3 py-2" placeholder="Description" :disabled="modalMode === 'view'" />
            </template>
            <template v-else-if="modalLevel === 'subsiri'">
              <input v-model="subsiriForm.activityGroup" class="w-full rounded border px-3 py-2" placeholder="Group code" :disabled="true" />
              <input v-model="subsiriForm.activitySubgroupCode" class="w-full rounded border px-3 py-2" placeholder="Subgroup code" :disabled="true" />
              <input v-model="subsiriForm.activitySubsiriCode" class="w-full rounded border px-3 py-2" placeholder="Subsiri code" :disabled="modalMode !== 'create'" />
              <input v-model="subsiriForm.activitySubsiriDesc" class="w-full rounded border px-3 py-2" placeholder="Description (Malay)" :disabled="modalMode === 'view'" />
              <input v-model="subsiriForm.activitySubsiriDescEng" class="w-full rounded border px-3 py-2" placeholder="Description (English)" :disabled="modalMode === 'view'" />
            </template>
            <template v-else>
              <input v-model="activityTypeForm.activityGroupCode" class="w-full rounded border px-3 py-2" placeholder="Group code" :disabled="true" />
              <input v-model="activityTypeForm.activitySubgroupCode" class="w-full rounded border px-3 py-2" placeholder="Subgroup code" :disabled="true" />
              <input v-model="activityTypeForm.activitySubsiriCode" class="w-full rounded border px-3 py-2" placeholder="Subsiri code" :disabled="true" />
              <input v-model="activityTypeForm.atActivityCode" class="w-full rounded border px-3 py-2" placeholder="Activity code" :disabled="modalMode !== 'create'" />
              <input v-model="activityTypeForm.atActivityDescriptionBm" class="w-full rounded border px-3 py-2" placeholder="Description (Malay)" :disabled="modalMode === 'view'" />
              <input v-model="activityTypeForm.atActivityDescriptionEn" class="w-full rounded border px-3 py-2" placeholder="Description (English)" :disabled="modalMode === 'view'" />
              <select v-model="activityTypeForm.atStatus" class="w-full rounded border px-3 py-2" :disabled="modalMode === 'view'">
                <option value="ACTIVE">ACTIVE</option>
                <option value="INACTIVE">INACTIVE</option>
              </select>
            </template>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button class="rounded border border-slate-300 px-4 py-2 text-sm" @click="modalVisible = false">Close</button>
            <button v-if="modalMode !== 'view'" class="rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="saveModal">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
