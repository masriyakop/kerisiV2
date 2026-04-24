<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from "vue";
import { Download, Eye, FileDown, FileSpreadsheet, Pencil, Plus, Search, Trash2, X } from "lucide-vue-next";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";

import AdminLayout from "@/layouts/AdminLayout.vue";
import {
  createAccountActivity,
  createAccountCode,
  deleteAccountActivity,
  deleteAccountCode,
  listAccountCodeLevel,
  updateAccountActivity,
  updateAccountCode,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { AccountActivityInput, AccountActivityRow, AccountCodeInput, AccountCodeRow } from "@/types";

type LevelKey = "activity" | "class" | "subClass" | "siri" | "subSiri" | "accountCode";
type ModalMode = "create" | "edit" | "view";

const toast = useToast();
const columns: { key: LevelKey; title: string; level: number }[] = [
  { key: "activity", title: "ACCOUNT ACTIVITY", level: 0 },
  { key: "class", title: "ACCOUNT CLASS", level: 1 },
  { key: "subClass", title: "ACCOUNT SUB-CLASS", level: 2 },
  { key: "siri", title: "ACCOUNT SIRI", level: 3 },
  { key: "subSiri", title: "ACCOUNT SUB-SIRI", level: 4 },
  { key: "accountCode", title: "ACCOUNT CODE", level: 5 },
];

const listData = reactive<Record<LevelKey, (AccountActivityRow | AccountCodeRow)[]>>({
  activity: [],
  class: [],
  subClass: [],
  siri: [],
  subSiri: [],
  accountCode: [],
});
const selected = reactive<Record<LevelKey, AccountActivityRow | AccountCodeRow | null>>({
  activity: null,
  class: null,
  subClass: null,
  siri: null,
  subSiri: null,
  accountCode: null,
});
const searchKeywords = reactive<Record<LevelKey, string>>({
  activity: "",
  class: "",
  subClass: "",
  siri: "",
  subSiri: "",
  accountCode: "",
});
const loading = reactive<Record<LevelKey, boolean>>({
  activity: false,
  class: false,
  subClass: false,
  siri: false,
  subSiri: false,
  accountCode: false,
});

const modalVisible = ref(false);
const modalLevel = ref<LevelKey>("activity");
const modalMode = ref<ModalMode>("create");
const currentId = ref<number | null>(null);
const currentCode = ref<string>("");

const activityForm = reactive<AccountActivityInput>({
  ldeValue: "",
  ldeDescription: "",
  ldeDescription2: "",
  ldeStatus: "ACTIVE",
});
const accountForm = reactive<AccountCodeInput>({
  acmAcctCode: "",
  acmAcctDesc: "",
  acmAcctDescEng: "",
  acmAcctStatus: "ACTIVE",
  acmAcctGroup: "",
  acmAcctLevel: 1,
  acmAcctActivity: "",
  acmAcctParent: "",
});

const visibleColumns = computed(() => {
  const out: LevelKey[] = ["activity"];
  if (selected.activity) out.push("class");
  if (selected.class) out.push("subClass");
  if (selected.subClass) out.push("siri");
  if (selected.siri) out.push("subSiri");
  if (selected.subSiri) out.push("accountCode");
  return out;
});

const selectionPath = computed(() =>
  [
    (selected.activity as AccountActivityRow | null)?.ldeValue,
    (selected.class as AccountCodeRow | null)?.acmAcctCode,
    (selected.subClass as AccountCodeRow | null)?.acmAcctCode,
    (selected.siri as AccountCodeRow | null)?.acmAcctCode,
    (selected.subSiri as AccountCodeRow | null)?.acmAcctCode,
    (selected.accountCode as AccountCodeRow | null)?.acmAcctCode,
  ].filter(Boolean),
);

function levelForKey(level: LevelKey) {
  return columns.find((x) => x.key === level)?.level ?? 0;
}

function parentForLevel(level: LevelKey): string | null {
  if (level === "subClass") return (selected.class as AccountCodeRow | null)?.acmAcctCode ?? null;
  if (level === "siri") return (selected.subClass as AccountCodeRow | null)?.acmAcctCode ?? null;
  if (level === "subSiri") return (selected.siri as AccountCodeRow | null)?.acmAcctCode ?? null;
  if (level === "accountCode") return (selected.subSiri as AccountCodeRow | null)?.acmAcctCode ?? null;
  return null;
}

function clearFrom(level: LevelKey) {
  if (level === "activity") {
    for (const key of ["class", "subClass", "siri", "subSiri", "accountCode"] as LevelKey[]) {
      selected[key] = null;
      listData[key] = [];
    }
    return;
  }
  if (level === "class") {
    for (const key of ["subClass", "siri", "subSiri", "accountCode"] as LevelKey[]) {
      selected[key] = null;
      listData[key] = [];
    }
    return;
  }
  if (level === "subClass") {
    for (const key of ["siri", "subSiri", "accountCode"] as LevelKey[]) {
      selected[key] = null;
      listData[key] = [];
    }
    return;
  }
  if (level === "siri") {
    for (const key of ["subSiri", "accountCode"] as LevelKey[]) {
      selected[key] = null;
      listData[key] = [];
    }
    return;
  }
  if (level === "subSiri") {
    selected.accountCode = null;
    listData.accountCode = [];
  }
}

async function fetchLevel(level: LevelKey) {
  loading[level] = true;
  try {
    const params = new URLSearchParams();
    if (level === "activity") {
      params.set("level", "0");
      params.set("dt_accountactvty", "1");
    } else {
      params.set("level", String(levelForKey(level)));
      if (level === "class") {
        params.set("level_1", "1");
        const activity = (selected.activity as AccountActivityRow | null)?.ldeValue;
        if (!activity) return;
        params.set("activity", activity);
      } else {
        const parent = parentForLevel(level);
        if (!parent) return;
        params.set("parent", parent);
      }
    }
    if (searchKeywords[level].trim()) params.set("search", searchKeywords[level].trim());
    const res = await listAccountCodeLevel(`?${params.toString()}`);
    listData[level] = (res.data as (AccountActivityRow | AccountCodeRow)[]) ?? [];
  } catch (error) {
    toast.error("Load failed", error instanceof Error ? error.message : "Unable to load account code level.");
  } finally {
    loading[level] = false;
  }
}

async function selectRow(level: LevelKey, row: AccountActivityRow | AccountCodeRow) {
  if (level === "activity") {
    const same = (selected.activity as AccountActivityRow | null)?.ldeId === (row as AccountActivityRow).ldeId;
    selected.activity = same ? null : row;
    clearFrom("activity");
    if (!same && selected.activity) await fetchLevel("class");
    return;
  }

  if (level === "accountCode") {
    selected.accountCode = row;
    return;
  }

  const same = (selected[level] as AccountCodeRow | null)?.acmAcctCode === (row as AccountCodeRow).acmAcctCode;
  selected[level] = same ? null : row;
  clearFrom(level);
  if (same) return;

  if (level === "class") await fetchLevel("subClass");
  if (level === "subClass") await fetchLevel("siri");
  if (level === "siri") await fetchLevel("subSiri");
  if (level === "subSiri") await fetchLevel("accountCode");
}

function openCreate(level: LevelKey) {
  modalLevel.value = level;
  modalMode.value = "create";
  currentId.value = null;
  currentCode.value = "";

  if (level === "activity") {
    activityForm.ldeValue = "";
    activityForm.ldeDescription = "";
    activityForm.ldeDescription2 = "";
    activityForm.ldeStatus = "ACTIVE";
  } else {
    accountForm.acmAcctCode = "";
    accountForm.acmAcctDesc = "";
    accountForm.acmAcctDescEng = "";
    accountForm.acmAcctGroup = "";
    accountForm.acmAcctStatus = "ACTIVE";
    accountForm.acmAcctLevel = levelForKey(level);
    accountForm.acmAcctActivity = level === "class" ? ((selected.activity as AccountActivityRow | null)?.ldeValue ?? "") : "";
    accountForm.acmAcctParent = parentForLevel(level) ?? "";
  }

  modalVisible.value = true;
}

function openViewOrEdit(level: LevelKey, mode: ModalMode, row: AccountActivityRow | AccountCodeRow) {
  modalLevel.value = level;
  modalMode.value = mode;

  if (level === "activity") {
    const r = row as AccountActivityRow;
    currentId.value = r.ldeId;
    currentCode.value = r.ldeValue;
    activityForm.ldeValue = r.ldeValue;
    activityForm.ldeDescription = r.ldeDescription;
    activityForm.ldeDescription2 = r.ldeDescription2 ?? "";
    activityForm.ldeStatus = r.ldeStatus;
  } else {
    const r = row as AccountCodeRow;
    currentCode.value = r.acmAcctCode;
    accountForm.acmAcctCode = r.acmAcctCode;
    accountForm.acmAcctDesc = r.acmAcctDesc;
    accountForm.acmAcctDescEng = r.acmAcctDescEng ?? "";
    accountForm.acmAcctStatus = r.acmAcctStatus;
    accountForm.acmAcctGroup = r.acmAcctGroup ?? "";
    accountForm.acmAcctLevel = r.acmAcctLevel;
    accountForm.acmAcctActivity = r.acmAcctActivity ?? "";
    accountForm.acmAcctParent = r.acmAcctParent ?? "";
  }

  modalVisible.value = true;
}

async function removeRow(level: LevelKey, row: AccountActivityRow | AccountCodeRow) {
  if (!window.confirm("Delete selected record?")) return;
  try {
    if (level === "activity") {
      await deleteAccountActivity((row as AccountActivityRow).ldeId);
    } else {
      await deleteAccountCode((row as AccountCodeRow).acmAcctCode);
    }
    await fetchLevel(level);
    toast.success("Delete successful");
  } catch (error) {
    toast.error("Delete failed", error instanceof Error ? error.message : "Unable to delete record.");
  }
}

async function saveModal() {
  try {
    if (modalLevel.value === "activity") {
      if (!activityForm.ldeValue.trim() || !activityForm.ldeDescription.trim()) {
        toast.error("Validation failed", "Code and description are required.");
        return;
      }
      if (modalMode.value === "create") {
        await createAccountActivity(activityForm);
      } else {
        await updateAccountActivity(currentId.value ?? 0, activityForm);
      }
      await fetchLevel("activity");
    } else {
      if (!accountForm.acmAcctCode.trim() || !accountForm.acmAcctDesc.trim()) {
        toast.error("Validation failed", "Account code and description are required.");
        return;
      }
      if (modalMode.value === "create") {
        await createAccountCode(accountForm);
      } else {
        await updateAccountCode(currentCode.value, accountForm);
      }
      await fetchLevel(modalLevel.value);
    }
    modalVisible.value = false;
    toast.success("Save successful");
  } catch (error) {
    toast.error("Save failed", error instanceof Error ? error.message : "Unable to save record.");
  }
}

function rowKey(level: LevelKey, row: AccountActivityRow | AccountCodeRow) {
  return level === "activity" ? String((row as AccountActivityRow).ldeId) : (row as AccountCodeRow).acmAcctCode;
}

function rowLabel(level: LevelKey, row: AccountActivityRow | AccountCodeRow) {
  return level === "activity"
    ? `${(row as AccountActivityRow).ldeValue} - ${(row as AccountActivityRow).ldeDescription}`
    : `${(row as AccountCodeRow).acmAcctCode} - ${(row as AccountCodeRow).acmAcctDesc}`;
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
  const rows = listData[level];
  if (!rows.length) {
    toast.info("No data", "There is no data to export");
    return null;
  }

  const title = columns.find((col) => col.key === level)?.title ?? "ACCOUNT CODE";
  if (level === "activity") {
    return {
      title,
      headers: ["No.", "Code", "Description (Malay)", "Description (English)", "Status"],
      body: (rows as AccountActivityRow[]).map((r, i) => [i + 1, r.ldeValue, r.ldeDescription, r.ldeDescription2 ?? "", r.ldeStatus]),
    };
  }
  return {
    title,
    headers: ["No.", "Account Code", "Description (Malay)", "Description (English)", "Status"],
    body: (rows as AccountCodeRow[]).map((r, i) => [i + 1, r.acmAcctCode, r.acmAcctDesc, r.acmAcctDescEng ?? "", r.acmAcctStatus]),
  };
}

function exportCSV(level: LevelKey) {
  const payload = exportPayload(level);
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
  const payload = exportPayload(level);
  if (!payload) return;
  try {
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet(payload.title);
    ws.addRow([`Date : ${formatDateHeader()}`]);
    ws.addRow([payload.title]);
    ws.addRow([]);
    ws.addRow(payload.headers);
    payload.body.forEach((row) => ws.addRow(row));
    ws.getRow(4).font = { bold: true };
    ws.getRow(4).fill = { type: "pattern", pattern: "solid", fgColor: { argb: "FFE5E7EB" } };
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
  } catch {
    toast.error("Export failed", "Please install exceljs dependency.");
  }
}

function exportPDF(level: LevelKey) {
  const payload = exportPayload(level);
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
    headStyles: { fillColor: [41, 98, 255], textColor: 255, fontStyle: "bold" },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    columnStyles: { 0: { cellWidth: 36, halign: "center" } },
  });
  doc.save(`${payload.title.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.pdf`);
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

onMounted(() => {
  void fetchLevel("activity");
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
      <h1 class="page-title">Setup and Maintenance / General Ledger Structure / Account Code</h1>
      <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h1 class="text-lg font-semibold text-slate-900">Account Code</h1>
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
                  v-for="row in listData[col.key]"
                  :key="rowKey(col.key, row)"
                  class="rounded-md border border-slate-200 bg-white p-2 text-sm hover:bg-slate-50"
                  :class="{
                    'ring-2 ring-blue-400':
                      (col.key === 'activity' && (selected.activity as AccountActivityRow | null)?.ldeId === (row as AccountActivityRow).ldeId) ||
                      (col.key !== 'activity' && (selected[col.key] as AccountCodeRow | null)?.acmAcctCode === (row as AccountCodeRow).acmAcctCode),
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
            <template v-if="modalLevel === 'activity'">
              <input v-model="activityForm.ldeValue" class="w-full rounded border px-3 py-2" placeholder="Activity code" :disabled="modalMode !== 'create'" />
              <input v-model="activityForm.ldeDescription" class="w-full rounded border px-3 py-2" placeholder="Description (Malay)" :disabled="modalMode === 'view'" />
              <input v-model="activityForm.ldeDescription2" class="w-full rounded border px-3 py-2" placeholder="Description (English)" :disabled="modalMode === 'view'" />
              <select v-model="activityForm.ldeStatus" class="w-full rounded border px-3 py-2" :disabled="modalMode === 'view'">
                <option value="ACTIVE">ACTIVE</option>
                <option value="INACTIVE">INACTIVE</option>
              </select>
            </template>
            <template v-else>
              <input v-model="accountForm.acmAcctCode" class="w-full rounded border px-3 py-2" placeholder="Account code" :disabled="modalMode !== 'create'" />
              <input v-model="accountForm.acmAcctDesc" class="w-full rounded border px-3 py-2" placeholder="Description (Malay)" :disabled="modalMode === 'view'" />
              <input v-model="accountForm.acmAcctDescEng" class="w-full rounded border px-3 py-2" placeholder="Description (English)" :disabled="modalMode === 'view'" />
              <input v-model="accountForm.acmAcctGroup" class="w-full rounded border px-3 py-2" placeholder="Group" :disabled="modalMode === 'view'" />
              <input v-model="accountForm.acmAcctActivity" class="w-full rounded border px-3 py-2" placeholder="Activity code" :disabled="modalMode === 'view'" />
              <input v-model="accountForm.acmAcctParent" class="w-full rounded border px-3 py-2" placeholder="Parent code" :disabled="modalMode === 'view'" />
              <select v-model="accountForm.acmAcctStatus" class="w-full rounded border px-3 py-2" :disabled="modalMode === 'view'">
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
