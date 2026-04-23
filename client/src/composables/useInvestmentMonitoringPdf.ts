/**
 * PDF generators for Investment > Monitoring (PAGEID 1183 /
 * MENUID 1458), replacing legacy TCPDF binaries under
 * `custom/report/senarai/Investment/`:
 *
 *   - downloadInvestmentMonitoringSummaryPdf <- investmentSummary_pdf.php
 *     Batch-level "Investment Summary" report triggered by the
 *     legacy `ATR_INVESTMENT_MONITORING` action=summary branch.
 *     Landscape A4, one row per investment, with a batch header
 *     (Batch No. + Total By Batch) and a Grand Total footer row.
 *
 * Same jsPDF + jspdf-autotable stack as the other migrated
 * generators (`useManualJournalPdf.ts`, `usePettyCashFormPdf.ts`).
 *
 * Deferred legacy binaries (separate migration passes):
 *   - billRegistrationInvestBatch_pdf.php (action=billBatch) —
 *     per-bill workflow document iterating over every bill in the
 *     batch; requires bills_master / bills_details / wf_task /
 *     wf_task_history / staff / bank_master / lookup_details.
 *   - billRegistrationInvest_pdf.php (action=reportUrl) — per-bill
 *     variant, no entry point from the migrated monitoring grid.
 */
import type { InvestmentMonitoringSummaryPdfPayload } from "@/types";

const AMOUNT_FORMATTER = new Intl.NumberFormat("en-MY", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

function formatAmount(value: number | null | undefined): string {
  if (value == null || Number.isNaN(value)) return "0.00";
  return AMOUNT_FORMATTER.format(value);
}

function formatRate(value: number | null | undefined): string {
  if (value == null || Number.isNaN(value)) return "";
  return Number(value).toFixed(2);
}

function institutionLine(row: {
  institutionCode: string | null;
  institutionDesc: string | null;
  institutionBranch: string | null;
}): string {
  const code = row.institutionCode ? `[${row.institutionCode}] ` : "";
  const desc = row.institutionDesc ?? "";
  const branch = row.institutionBranch ? ` - ${row.institutionBranch}` : "";
  return `${code}${desc}${branch}`.trim();
}

function investmentCertLine(row: {
  investmentNo: string | null;
  certificateNo: string | null;
}): string {
  return [row.investmentNo, row.certificateNo]
    .map((part) => (part ?? "").trim())
    .filter((part) => part !== "")
    .join("\n");
}

function journalLine(row: {
  journalNo: string | null;
  journalStatus: string | null;
}): string {
  if (!row.journalNo && !row.journalStatus) return "";
  if (row.journalStatus) {
    return `${row.journalNo ?? ""} (${row.journalStatus})`.trim();
  }
  return row.journalNo ?? "";
}

function tenureLine(row: {
  period: number | null;
  tenureDesc: string | null;
  startDate: string | null;
  endDate: string | null;
}): string {
  const head = [row.period !== null ? String(row.period) : "", row.tenureDesc ?? ""]
    .filter(Boolean)
    .join(" ");
  const range = [row.startDate ?? "", row.endDate ?? ""]
    .filter(Boolean)
    .join(" - ");
  return [head, range].filter(Boolean).join("\n");
}

/**
 * Replicates `investmentSummary_pdf.php` — "LIST OF INVESTMENT BY
 * BATCH" landscape A4 report with columns:
 *   No / Institution / Investment No. & Cert. No. / Journal No. &
 *   Status / Tenure & Period Duration / Principal / Rate / Status
 * Header: Batch No. + Total By Batch (RM) (matches the legacy
 * `$sqlTotal` value from the backend payload). Footer: Grand Total
 * across all rendered rows.
 */
export async function downloadInvestmentMonitoringSummaryPdf(
  payload: InvestmentMonitoringSummaryPdfPayload,
): Promise<void> {
  const { default: jsPDF } = await import("jspdf");
  const autoTable = (await import("jspdf-autotable")).default;

  const doc = new jsPDF({ orientation: "landscape", unit: "mm", format: "a4" });
  const pw = doc.internal.pageSize.getWidth();
  const ph = doc.internal.pageSize.getHeight();
  const margin = 12;

  const now = payload.generatedAt || new Date().toLocaleString("en-GB");
  const [datePart, timePart] = now.split(" ");

  const drawHeader = () => {
    doc.setFont("helvetica", "bold");
    doc.setFontSize(14);
    doc.text("LIST OF INVESTMENT BY BATCH", margin, 13);

    doc.setFont("helvetica", "normal");
    doc.setFontSize(9);
    doc.text(`Date: ${datePart ?? ""}`, pw - margin, 10, { align: "right" });
    doc.text(`Time: ${timePart ?? ""}`, pw - margin, 14, { align: "right" });

    doc.setFont("helvetica", "bold");
    doc.setFontSize(10);
    doc.text(`Batch No.: ${payload.batch}`, margin, 21);
    doc.text(
      `Total By Batch (RM): ${formatAmount(payload.totalByBatch)}`,
      pw - margin,
      21,
      { align: "right" },
    );
  };

  const drawFooter = () => {
    const pageCount = doc.getNumberOfPages();
    doc.setFont("helvetica", "italic");
    doc.setFontSize(8);
    for (let i = 1; i <= pageCount; i += 1) {
      doc.setPage(i);
      doc.text(`${i} / ${pageCount}`, pw / 2, ph - 7, { align: "center" });
    }
  };

  const body: (string | number)[][] = payload.rows.map((r) => [
    String(r.index),
    institutionLine(r),
    investmentCertLine(r),
    journalLine(r),
    tenureLine(r),
    formatAmount(r.principalAmount),
    formatRate(r.rate),
    r.status ?? "",
  ]);

  body.push([
    {
      content: "GRAND TOTAL",
      colSpan: 5,
      styles: { fontStyle: "bold", halign: "right" },
    } as unknown as string,
    {
      content: formatAmount(payload.grandTotal),
      styles: { fontStyle: "bold", halign: "right" },
    } as unknown as string,
    "",
    "",
  ]);

  autoTable(doc, {
    startY: 26,
    margin: { left: margin, right: margin, top: 26, bottom: 14 },
    head: [
      [
        "No.",
        "Institution",
        "Investment No. /\nCertification No.",
        "Journal No. / Status",
        "Tenure /\nPeriod Duration",
        "Principal (RM)",
        "Rate (%)",
        "Status",
      ],
    ],
    body,
    styles: {
      fontSize: 8,
      cellPadding: 2,
      lineColor: [0, 0, 0],
      lineWidth: 0.2,
      overflow: "linebreak",
      valign: "top",
    },
    headStyles: {
      fillColor: [235, 235, 235],
      textColor: [0, 0, 0],
      fontStyle: "bold",
      halign: "center",
      lineColor: [0, 0, 0],
      lineWidth: 0.2,
    },
    columnStyles: {
      0: { cellWidth: 10, halign: "center" },
      1: { cellWidth: 58 },
      2: { cellWidth: 42, halign: "center" },
      3: { cellWidth: 40 },
      4: { cellWidth: 50, halign: "center" },
      5: { cellWidth: 28, halign: "right" },
      6: { cellWidth: 18, halign: "right" },
      7: { cellWidth: 28, halign: "center" },
    },
    theme: "grid",
    didDrawPage: () => {
      drawHeader();
    },
  });

  if (payload.truncated) {
    const tail = (doc as unknown as { lastAutoTable?: { finalY?: number } })
      .lastAutoTable?.finalY ?? 32;
    doc.setFont("helvetica", "italic");
    doc.setFontSize(8);
    doc.text(
      `Note: output truncated at ${payload.limit} rows — narrow filters to see additional entries.`,
      margin,
      tail + 6,
    );
  }

  drawFooter();

  doc.save(`Investment Summary - ${payload.batch}.pdf`);
}
