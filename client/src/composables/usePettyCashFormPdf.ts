/**
 * Generates "BORANG PERMOHONAN PANJAR WANG RUNCIT" (Request Petty Cash form)
 * as a downloadable PDF using jsPDF + jspdf-autotable.
 *
 * Mirrors the printed form layout visible in FIMS legacy output.
 */
import type { PettyCashApplicationDetail } from "@/types";

const ORG_NAME = "KERISI SAGA FINANCIAL SYSTEM";
const ORG_ADDRESS1 = "No 9-2, Jalan Tasik Selatan 3, Metro Centre, Bandar Tasik Selatan, 57000 Kuala Lumpur, Wilayah Persekutuan";
const ORG_TEL = "Tel: +603-9059 3800  Fax: +60 4978 2400";
const FORM_TITLE = "BORANG PERMOHONAN PANJAR WANG RUNCIT";
const FOOTER_NOTE = "NOTA : Bil/ Invois hendaklah disertakan bersama Baucer jika berkenaan.";
const COMPUTER_PRINT_NOTICE = "INI ADALAH CETAKAN KOMPUTER DAN TIDAK MEMERLUKAN TANDATANGAN";

function splitAmt(amount: number | null): { rm: string; sen: string } {
  if (amount == null) return { rm: "0", sen: "00" };
  const fixed = Number(amount).toFixed(2);
  const [rm, sen] = fixed.split(".");
  return { rm: Number(rm).toLocaleString("en-MY"), sen: sen ?? "00" };
}

export async function downloadRequestPettyCashPdf(detail: PettyCashApplicationDetail): Promise<void> {
  const { default: jsPDF } = await import("jspdf");
  const autoTable = (await import("jspdf-autotable")).default;

  const doc = new jsPDF({ orientation: "portrait", unit: "mm", format: "a4" });
  const pw = doc.internal.pageSize.getWidth(); // 210
  const margin = 14;
  const innerW = pw - margin * 2;
  let y = margin;

  // ── Header ───────────────────────────────────────────────────────────────
  doc.setFont("helvetica", "bold");
  doc.setFontSize(11);
  doc.text(ORG_NAME, pw / 2, y, { align: "center" });
  y += 5;

  doc.setFont("helvetica", "normal");
  doc.setFontSize(8);
  const addrLines = doc.splitTextToSize(ORG_ADDRESS1, innerW);
  doc.text(addrLines, pw / 2, y, { align: "center" });
  y += addrLines.length * 4;

  doc.text(ORG_TEL, pw / 2, y, { align: "center" });
  y += 6;

  doc.setFont("helvetica", "bold");
  doc.setFontSize(10);
  doc.text(FORM_TITLE, pw / 2, y, { align: "center" });
  y += 7;

  // Draw outer border around entire form content (drawn at the end)
  const formTopY = y;

  // ── Top info block: BAYAR KEPADA / TARIKH / NO RUJUKAN ───────────────────
  const colW1 = 38; // label col
  const colW2 = 58; // value col (BAYAR KEPADA spans wider)
  const colW3 = 42; // TARIKH label+value
  const colW4 = 24; // NO RUJUKAN label
  const colW5 = innerW - colW1 - colW2 - colW3 - colW4; // remaining

  const infoRowH = 14;

  // Draw top info table borders
  doc.setDrawColor(0);
  doc.setLineWidth(0.3);
  doc.rect(margin, y, innerW, infoRowH);

  // Internal vertical lines
  let vx = margin + colW1;
  doc.line(vx, y, vx, y + infoRowH);
  vx += colW2;
  doc.line(vx, y, vx, y + infoRowH);
  vx += colW3;
  doc.line(vx, y, vx, y + infoRowH);

  // Labels
  doc.setFont("helvetica", "bold");
  doc.setFontSize(8);
  doc.text("BAYAR KEPADA :", margin + 2, y + 5);
  doc.text("TARIKH PERMOHONAN :", margin + colW1 + colW2 + 2, y + 5);
  doc.text("NO. RUJUKAN", margin + colW1 + colW2 + colW3 + 2, y + 5);
  doc.text("PERMOHONAN :", margin + colW1 + colW2 + colW3 + 2, y + 9);

  // Values
  doc.setFont("helvetica", "normal");
  const payToLabel = detail.pmsPayToIdDesc
    ? `${detail.pmsPayToIdDesc} (${detail.pmsPayToId ?? ""})`
    : detail.pmsPayToId ?? "";
  const payToLines = doc.splitTextToSize(payToLabel, colW2 - 4);
  doc.text(payToLines, margin + colW1 + 2, y + 5);
  doc.text(detail.pmsRequestDate, margin + colW1 + colW2 + 26, y + 5);
  doc.text(detail.pmsApplicationNo ?? "", margin + colW1 + colW2 + colW3 + 2, y + 13);

  y += infoRowH;

  // ── Line items table ─────────────────────────────────────────────────────
  const tableBody = detail.lines.map((l) => {
    const { rm, sen } = splitAmt(l.pcdTransAmt);
    return [l.pcdTransDesc ?? "", l.pcdReceiptNo ?? "", l.acmAcctCode ?? "", rm, sen, l.pcdStatus ?? ""];
  });

  // Append total row
  const { rm: totalRm, sen: totalSen } = splitAmt(detail.pmsTotalAmt);

  autoTable(doc, {
    startY: y,
    margin: { left: margin, right: margin },
    tableWidth: innerW,
    head: [
      [
        { content: "BUTIRAN TUNTUTAN", rowSpan: 1, styles: { halign: "center", fontStyle: "bold", fontSize: 8 } },
        { content: "NO. RESIT/ INVOICE", rowSpan: 1, styles: { halign: "center", fontStyle: "bold", fontSize: 8 } },
        { content: "KOD AKUAN", rowSpan: 1, styles: { halign: "center", fontStyle: "bold", fontSize: 8 } },
        { content: "AMAUN", colSpan: 2, styles: { halign: "center", fontStyle: "bold", fontSize: 8 } },
        { content: "STATUS", rowSpan: 1, styles: { halign: "center", fontStyle: "bold", fontSize: 8 } },
      ],
      [
        { content: "", styles: { cellPadding: 0, minCellHeight: 0 } },
        { content: "", styles: { cellPadding: 0, minCellHeight: 0 } },
        { content: "", styles: { cellPadding: 0, minCellHeight: 0 } },
        { content: "RM", styles: { halign: "center", fontStyle: "bold", fontSize: 8 } },
        { content: "SEN", styles: { halign: "center", fontStyle: "bold", fontSize: 8 } },
        { content: "", styles: { cellPadding: 0, minCellHeight: 0 } },
      ],
    ],
    body: [
      ...tableBody,
      [
        { content: "JUMLAH KESELURUHAN", colSpan: 3, styles: { halign: "right", fontStyle: "bold", fontSize: 8 } },
        { content: totalRm, styles: { halign: "right", fontStyle: "bold", fontSize: 8 } },
        { content: totalSen, styles: { halign: "center", fontStyle: "bold", fontSize: 8 } },
        { content: "", styles: {} },
      ],
    ],
    columnStyles: {
      0: { cellWidth: 52, fontSize: 8 },
      1: { cellWidth: 36, fontSize: 8 },
      2: { cellWidth: 30, halign: "center", fontSize: 8 },
      3: { cellWidth: 20, halign: "right", fontSize: 8 },
      4: { cellWidth: 16, halign: "center", fontSize: 8 },
      5: { cellWidth: innerW - 52 - 36 - 30 - 20 - 16, halign: "center", fontSize: 8 },
    },
    styles: { lineColor: [0, 0, 0], lineWidth: 0.3, fontSize: 8, cellPadding: 2 },
    headStyles: { fillColor: [255, 255, 255], textColor: [0, 0, 0], lineColor: [0, 0, 0], lineWidth: 0.3 },
    bodyStyles: { fillColor: [255, 255, 255], textColor: [0, 0, 0] },
    theme: "grid",
  });

  y = (doc as unknown as { lastAutoTable: { finalY: number } }).lastAutoTable.finalY + 4;

  // ── Signature block row 1: DIPOHON OLEH | DISEDIA OLEH | DISEMAK OLEH ────
  const sigBlockW = innerW / 3;
  const sigRowH = 34;

  const sig1Labels = ["DIPOHON OLEH:", "DISEDIA OLEH:", "DISEMAK OLEH:"];
  const sig1Values: Array<{ name: string; job: string; tarikh: string; masa: string }> = [
    { name: `Nama/No.Staf :`, job: detail.requestorName, tarikh: detail.pmsRequestDate, masa: detail.pmsRequestTime },
    { name: "Nama/No.Staf :", job: "", tarikh: "", masa: "" },
    { name: "Nama/No.Staf :", job: "", tarikh: "", masa: "" },
  ];

  doc.setDrawColor(0);
  doc.setLineWidth(0.3);

  sig1Labels.forEach((label, i) => {
    const bx = margin + i * sigBlockW;
    doc.rect(bx, y, sigBlockW, sigRowH);

    doc.setFont("helvetica", "normal");
    doc.setFontSize(7.5);
    let by = y + 4;
    doc.text(label, bx + 2, by);
    by += 4;
    doc.text(sig1Values[i]!.name, bx + 2, by);
    by += 4;
    doc.setFont("helvetica", "bold");
    const nameLines = doc.splitTextToSize(sig1Values[i]!.job, sigBlockW - 4);
    doc.text(nameLines, bx + 2, by);
    by += nameLines.length * 3.5 + 1;
    doc.setFont("helvetica", "normal");
    if (sig1Values[i]!.job) {
      // job title
      const jobLines = doc.splitTextToSize(detail.requestorJob, sigBlockW - 4);
      doc.text(jobLines, bx + 2, by);
      by += jobLines.length * 3.5 + 1;
    }
    doc.setFontSize(7);
    doc.text(`Jawatan :`, bx + 2, by);
    by += 4;
    doc.text(`Tarikh: ${sig1Values[i]!.tarikh}`, bx + 2, by);
    by += 4;
    doc.text(`Masa: ${sig1Values[i]!.masa}`, bx + 2, by);
  });

  y += sigRowH;

  // ── Signature block row 2: DILULUS OLEH | (spacer) | PERAKUAN PENERIMAAN OLEH ─
  const sigHalf = innerW / 2;
  const sigRow2H = 34;

  const sig2Data: Array<{ label: string; name: string; job: string; tarikh: string; masa: string }> = [
    { label: "DILULUS OLEH:", name: "Nama/No.Staf :", job: "", tarikh: "", masa: "" },
    {
      label: "PERAKUAN PENERIMAAN OLEH :",
      name: "Nama/No.Staf :",
      job: detail.payToName,
      tarikh: "",
      masa: "",
    },
  ];

  sig2Data.forEach((sig, i) => {
    const bx = margin + i * sigHalf;
    doc.setDrawColor(0);
    doc.setLineWidth(0.3);
    doc.rect(bx, y, sigHalf, sigRow2H);

    doc.setFont("helvetica", "normal");
    doc.setFontSize(7.5);
    let by = y + 4;
    doc.text(sig.label, bx + 2, by);
    by += 4;
    doc.text(sig.name, bx + 2, by);
    by += 4;
    doc.setFont("helvetica", "bold");
    const nameLines = doc.splitTextToSize(sig.job, sigHalf - 4);
    doc.text(nameLines, bx + 2, by);
    by += nameLines.length * 3.5 + 1;
    doc.setFont("helvetica", "normal");
    if (sig.job && detail.payToJob) {
      const jobLines = doc.splitTextToSize(detail.payToJob, sigHalf - 4);
      doc.text(jobLines, bx + 2, by);
      by += jobLines.length * 3.5 + 1;
    }
    doc.setFontSize(7);
    doc.text("Jawatan :", bx + 2, by);
    by += 4;
    doc.text(`Tarikh: ${sig.tarikh}`, bx + 2, by);
    by += 4;
    doc.text(`Masa: ${sig.masa}`, bx + 2, by);
  });

  y += sigRow2H + 3;

  // ── Footer note ───────────────────────────────────────────────────────────
  doc.setDrawColor(0);
  doc.setLineWidth(0.3);
  doc.setFont("helvetica", "normal");
  doc.setFontSize(7.5);
  doc.rect(margin, y, innerW, 8);
  doc.text(FOOTER_NOTE, margin + 2, y + 5);
  y += 12;

  // Outer form border
  doc.setLineWidth(0.5);
  doc.rect(margin, formTopY, innerW, y - formTopY - 4);

  // ── Computer print notice ─────────────────────────────────────────────────
  doc.setFont("helvetica", "bold");
  doc.setFontSize(8);
  doc.text(COMPUTER_PRINT_NOTICE, pw / 2, y + 2, { align: "center" });

  // ── Save ─────────────────────────────────────────────────────────────────
  const filename = `RequestPettyCash_${detail.pmsApplicationNo ?? detail.pmsId}.pdf`;
  doc.save(filename);
}
