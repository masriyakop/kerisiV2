import { ref, unref, type Ref } from "vue";
import { useToast } from "@/composables/useToast";

/** Serialized table UI state (save/load template). */
export type DatatableTemplateState = {
  columnOrder: string[];
  hiddenColumns: string[];
  sortColumn: string | null;
  sortDirection: "asc" | "desc";
};

export type DatatableExportConfig = {
  columns: string[];
  data: Record<string, unknown>[];
  groupedInfo?: Record<number, Record<number, { value: unknown; rowspan: number }>>;
  columnTitleIndices?: Record<number, number>;
};

export type DatatableRefApi = {
  getTemplateState?: () => DatatableTemplateState | null;
  applyTemplateState?: (template: Partial<DatatableTemplateState> & { columnOrder?: string[]; hiddenColumns?: string[] }) => void;
  getExportConfig?: () => DatatableExportConfig | null;
};

export type UseDatatableFeaturesOptions = {
  pageName: string;
  apiDataPath: string;
  defaultExportColumns: string[];
  getFilteredList: () => Record<string, unknown>[];
  datatableRef: Ref<DatatableRefApi | null>;
  searchKeyword: Ref<string>;
  smartFilter?: Ref<Record<string, unknown>>;
  applyFilters?: () => void;
  smartFilterLabels?: Record<string, string>;
  smartFilterOptionsLookup?: Record<string, Ref<unknown> | unknown>;
  getLookupLabel?: (opts: unknown, val: unknown) => string;
  formatDate?: (v: unknown) => string;
  formatDateTime?: (v: unknown) => string;
  columnOptionsLookup?: Record<string, Ref<unknown> | unknown>;
  columnDateTypeMap?: Record<string, "date" | "datetime">;
};

/**
 * Datatable helpers: template JSON save/load, group toggle, client PDF/CSV export.
 * Kerisi "Generate API" is not wired here; use Laravel routes if you need it.
 */
export function useDatatableFeatures(options: UseDatatableFeaturesOptions) {
  const toast = useToast();
  const {
    pageName,
    defaultExportColumns,
    getFilteredList,
    datatableRef,
    searchKeyword,
    smartFilter = ref({}),
    applyFilters = () => {},
    getLookupLabel = (_opts: unknown, val: unknown) => (val == null ? "" : String(val)),
    formatDate = (v: unknown) =>
      v
        ? new Date(v as string | number)
            .toLocaleDateString("en-GB", { day: "2-digit", month: "2-digit", year: "numeric" })
            .replace(/\//g, "/")
        : "-",
    formatDateTime = (v: unknown) => (v ? new Date(v as string | number).toLocaleString() : "-"),
    columnOptionsLookup = {},
    columnDateTypeMap = {},
    smartFilterLabels = {},
    smartFilterOptionsLookup = {},
  } = options;

  const templateFileInputRef = ref<HTMLInputElement | null>(null);
  const exportConfigRef = ref<(() => DatatableExportConfig | null) | null>(null);
  const isGrouped = ref(false);

  const formatCell = (item: Record<string, unknown>, col: string, val: unknown) => {
    const opts = unref(columnOptionsLookup[col]);
    const dateType = columnDateTypeMap[col];
    const value = val !== undefined ? val : item[col];
    if (opts) return (getLookupLabel(opts, value) || "").toString();
    if (dateType === "datetime") return formatDateTime(value);
    if (dateType === "date") return formatDate(value);
    return (value ?? "").toString();
  };

  const handleSaveTemplate = async () => {
    const tableState = datatableRef.value?.getTemplateState?.();
    if (!tableState) {
      toast.error("Save failed", "Table state is not ready.");
      return;
    }
    const template = {
      version: 1 as const,
      pageName,
      columnOrder: tableState.columnOrder,
      hiddenColumns: tableState.hiddenColumns,
      sortColumn: tableState.sortColumn,
      sortDirection: tableState.sortDirection,
      isGrouped: isGrouped.value,
      searchKeyword: searchKeyword.value || "",
      smartFilter: { ...smartFilter.value },
    };
    const blob = new Blob([JSON.stringify(template, null, 2)], { type: "application/json" });
    const suggestedName = `${pageName} Template.json`;
    const w = window as Window & {
      showSaveFilePicker?: (opts: {
        suggestedName: string;
        types: { description: string; accept: Record<string, string[]> }[];
      }) => Promise<{ createWritable: () => Promise<{ write: (b: Blob) => Promise<void>; close: () => Promise<void> }> }>;
    };
    if (typeof w.showSaveFilePicker === "function") {
      try {
        const handle = await w.showSaveFilePicker({
          suggestedName,
          types: [{ description: "JSON Template", accept: { "application/json": [".json"] } }],
        });
        const writable = await handle.createWritable();
        await writable.write(blob);
        await writable.close();
        toast.success("Template saved");
      } catch (err) {
        const e = err as { name?: string; message?: string };
        if (e.name !== "AbortError") toast.error("Save failed", e.message || "Could not save template.");
      }
    } else {
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = suggestedName;
      a.click();
      URL.revokeObjectURL(url);
      toast.success("Template downloaded");
    }
  };

  const handleLoadTemplate = () => {
    templateFileInputRef.value?.click();
  };

  const onTemplateFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        const template = JSON.parse(String(e.target?.result || "{}")) as {
          columnOrder?: string[];
          hiddenColumns?: string[];
          searchKeyword?: string;
          smartFilter?: Record<string, unknown>;
          isGrouped?: boolean;
        };
        if (!template.columnOrder || !Array.isArray(template.columnOrder)) {
          toast.error("Invalid template", "Missing or invalid columnOrder.");
          return;
        }
        datatableRef.value?.applyTemplateState?.(template);
        if (template.searchKeyword !== undefined) searchKeyword.value = template.searchKeyword;
        if (template.smartFilter && typeof template.smartFilter === "object") {
          smartFilter.value = { ...template.smartFilter };
        }
        if (template.isGrouped !== undefined) isGrouped.value = !!template.isGrouped;
        applyFilters();
        toast.success("Template loaded");
      } catch {
        toast.error("Invalid template", "Could not parse JSON.");
      }
      input.value = "";
    };
    reader.readAsText(file);
  };

  const handleGenerateApi = () => {
    toast.info(
      "Generate API not configured",
      "In Laravel, replace Kerisi’s /api/api-gen-template with your own signed export URL or omit this action.",
    );
  };

  const handleUngroupList = () => {
    isGrouped.value = false;
  };
  const handleGroupList = () => {
    isGrouped.value = true;
  };

  const handleDownloadPDF = async () => {
    try {
      const { default: jsPDF } = await import("jspdf");
      const autoTable = (await import("jspdf-autotable")).default;
      const exportConfig =
        datatableRef.value?.getExportConfig?.() ??
        (typeof exportConfigRef.value === "function" ? exportConfigRef.value() : null);
      const exportColumns = exportConfig ? exportConfig.columns : defaultExportColumns;
      const dataToExport = exportConfig ? [...exportConfig.data] : [...(getFilteredList() || [])];
      if (dataToExport.length === 0) {
        toast.info("No data", "There is nothing to export.");
        return;
      }
      const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
      const pageWidth = doc.internal.pageSize.getWidth();
      const margin = 10;
      const now = new Date();
      const formattedDateTime = `Date : ${String(now.getDate()).padStart(2, "0")}/${String(now.getMonth() + 1).padStart(2, "0")}/${now.getFullYear()} ${String(now.getHours() % 12 || 12).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}:${String(now.getSeconds()).padStart(2, "0")} ${now.getHours() >= 12 ? "PM" : "AM"}`;
      doc.setFontSize(10);
      doc.text(formattedDateTime, pageWidth - margin - doc.getTextWidth(formattedDateTime), margin + 8);
      doc.setFontSize(16);
      doc.setFont("helvetica", "bold");
      doc.text(pageName, (pageWidth - doc.getTextWidth(pageName)) / 2, margin + 10);
      const { groupedInfo, columnTitleIndices } = exportConfig || {};
      const tableData = dataToExport.map((item, index) => {
        const row: unknown[] = [(index + 1).toString()];
        exportColumns.forEach((col, colIdx) => {
          const titleIdx = columnTitleIndices?.[colIdx];
          const cellInfo = groupedInfo?.[index]?.[titleIdx as number];
          if (cellInfo != null && cellInfo.rowspan > 0) {
            row.push({
              content: formatCell(item, col, cellInfo.value),
              rowSpan: cellInfo.rowspan,
              styles: { valign: "middle" as const },
            });
          } else if (cellInfo != null && cellInfo.rowspan === 0) {
            // merged cell continuation — omit
          } else {
            row.push(formatCell(item, col, undefined));
          }
        });
        return row;
      });
      autoTable(doc, {
        head: [["No.", ...exportColumns]],
        body: tableData as import("jspdf-autotable").RowInput[],
        startY: margin + 18,
        margin: { left: margin, right: margin },
        styles: { fontSize: 9, cellPadding: 2 },
        headStyles: { fillColor: [59, 130, 246], textColor: [255, 255, 255], fontStyle: "bold", halign: "center" },
        columnStyles: { 0: { halign: "center", cellWidth: 15 } },
      });
      doc.save(`${pageName.replace(/\s+/g, "_")}_${new Date().toISOString().split("T")[0]}.pdf`);
      toast.success("PDF downloaded");
    } catch (error) {
      console.error("PDF export error:", error);
      toast.error("Export failed", "Could not generate PDF.");
    }
  };

  const handleDownloadCSV = () => {
    try {
      const exportConfig =
        datatableRef.value?.getExportConfig?.() ??
        (typeof exportConfigRef.value === "function" ? exportConfigRef.value() : null);
      const exportColumns = exportConfig ? exportConfig.columns : defaultExportColumns;
      const dataToExport = exportConfig ? [...exportConfig.data] : [...(getFilteredList() || [])];
      if (dataToExport.length === 0) {
        toast.info("No data", "There is nothing to export.");
        return;
      }
      const escapeCSVField = (field: unknown) => {
        if (field === null || field === undefined) return "";
        const str = String(field);
        return str.includes(",") || str.includes('"') || str.includes("\n") ? `"${str.replace(/"/g, '""')}"` : str;
      };
      const now = new Date();
      const formattedDateTime = `Date : ${String(now.getDate()).padStart(2, "0")}/${String(now.getMonth() + 1).padStart(2, "0")}/${now.getFullYear()} ${String(now.getHours() % 12 || 12).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}:${String(now.getSeconds()).padStart(2, "0")} ${now.getHours() >= 12 ? "PM" : "AM"}`;
      let csvContent = escapeCSVField(formattedDateTime) + "\n" + escapeCSVField(pageName) + "\n";
      if (searchKeyword.value?.trim()) csvContent += escapeCSVField(`Search: ${searchKeyword.value.trim()}`) + "\n";
      const sf = (smartFilter.value || {}) as Record<string, unknown>;
      Object.keys(sf).forEach((key) => {
        const v = sf[key];
        if (v !== undefined && v !== null && String(v).trim()) {
          const label = smartFilterLabels[key] || key.replace(/_filter$/, "");
          const opts = unref(smartFilterOptionsLookup[key]);
          const val = opts ? getLookupLabel(opts, v) : v;
          csvContent += escapeCSVField(`${label}: ${val}`) + "\n";
        }
      });
      if (
        Object.keys(sf).some((k) => {
          const x = sf[k];
          return x !== undefined && x !== null && String(x).trim();
        }) ||
        searchKeyword.value?.trim()
      ) {
        csvContent += "\n";
      }
      csvContent += ["No.", ...exportColumns].map(escapeCSVField).join(",") + "\n";
      const { groupedInfo, columnTitleIndices } = exportConfig || {};
      dataToExport.forEach((item, index) => {
        const row: string[] = [(index + 1).toString()];
        exportColumns.forEach((col, colIdx) => {
          const titleIdx = columnTitleIndices?.[colIdx];
          const cellInfo = groupedInfo?.[index]?.[titleIdx as number];
          if (cellInfo != null && cellInfo.rowspan > 0) row.push(formatCell(item, col, cellInfo.value));
          else if (cellInfo != null && cellInfo.rowspan === 0) row.push("");
          else row.push(formatCell(item, col, undefined));
        });
        csvContent += row.map(escapeCSVField).join(",") + "\n";
      });
      const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.download = `${pageName.replace(/\s+/g, "_")}_${new Date().toISOString().split("T")[0]}.csv`;
      link.style.visibility = "hidden";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(link.href);
      toast.success("CSV downloaded");
    } catch (error) {
      console.error("CSV export error:", error);
      toast.error("Export failed", "Could not generate CSV.");
    }
  };

  return {
    templateFileInputRef,
    exportConfigRef,
    isGrouped,
    handleSaveTemplate,
    handleLoadTemplate,
    onTemplateFileChange,
    handleGenerateApi,
    handleUngroupList,
    handleGroupList,
    handleDownloadPDF,
    handleDownloadCSV,
  };
}
