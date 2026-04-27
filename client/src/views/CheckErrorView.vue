<script setup lang="ts">
import AdminLayout from "@/layouts/AdminLayout.vue";
import CheckErrorSection, { type CheckErrorColumn } from "@/components/fims/CheckErrorSection.vue";
import {
  listCheckErrorBillMaster,
  listCheckErrorVoucherDetail,
  listCheckErrorVoucherMaster,
  listCheckErrorPayment2Pelik,
  listCheckErrorPaymentPelik,
  listCheckErrorUrlBrfHilang,
  listCheckErrorResit,
} from "@/api/cms";

// Breadcrumb/title lifted from legacy PAGEBREADCRUMBS / PAGETITLE for PAGEID 2253.
const PAGE_BREADCRUMB = "Setup and Maintenance / Cek yang mungkin error";
const PAGE_TITLE = "Cek yang mungkin error";

function toCurrency(v: unknown): string {
  const n = typeof v === "number" ? v : v == null || v === "" ? 0 : Number(v);
  if (!isFinite(n)) return "0.00";
  return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Component 6934 — "Cek bill master yang hilang data". Legacy dt_bi labels preserved.
const billMasterColumns: CheckErrorColumn[] = [
  { key: "bimBillsId", label: "Bill id", sortable: true, hideable: true },
  { key: "bimBillsNo", label: "Bill no", sortable: true, hideable: true },
  { key: "bimBillsType", label: "Bill type", sortable: true, hideable: true },
  { key: "bimBillAmt", label: "Bill amount", sortable: true, hideable: true, align: "right", formatter: (r) => toCurrency(r.bimBillAmt) },
  { key: "bimStatus", label: "Status", sortable: true, hideable: true },
  { key: "bimPaytoId", label: "Pay To ID", sortable: true, hideable: true },
  { key: "bimPaytoType", label: "Pay To Type", sortable: true, hideable: true },
  { key: "bimPaytoName", label: "Pay To Name", sortable: true, hideable: true },
  { key: "bimPaytoAddress", label: "Pay To Address", sortable: true, hideable: true },
  { key: "createdby", label: "Create by", sortable: true, hideable: true },
  { key: "updatedby", label: "Update by", sortable: true, hideable: true },
  { key: "bimSystemId", label: "System Id", sortable: true, hideable: true },
  { key: "bimPayeeCount", label: "Payee Category", sortable: true, hideable: true },
];

// Component 6935 — "Cek data voucher detail kaki hilang".
const voucherDetailColumns: CheckErrorColumn[] = [
  { key: "vmaVoucherId", label: "Voucher Id", sortable: true, hideable: true },
  { key: "dt", label: "Debit", sortable: true, hideable: true, align: "right", formatter: (r) => toCurrency(r.dt) },
  { key: "cr", label: "Credit", sortable: true, hideable: true, align: "right", formatter: (r) => toCurrency(r.cr) },
  { key: "beza", label: "Perbezaan", sortable: true, hideable: true, align: "right", formatter: (r) => toCurrency(r.beza) },
];

// Component 6949 — "Voucher yg hilang payee dkat master".
const voucherMasterColumns: CheckErrorColumn[] = [
  { key: "vmaVoucherId", label: "Voucher Id", sortable: true, hideable: true },
  { key: "vmaVoucherNo", label: "Voucher No", sortable: true, hideable: true },
  { key: "vmaVchStatus", label: "Status", sortable: true, hideable: true },
  { key: "vmaPaytoType", label: "Pay to type", sortable: true, hideable: true },
  { key: "vmaPaytoId", label: "Pay to id", sortable: true, hideable: true },
  { key: "vmaPaytoName", label: "Pay to name", sortable: true, hideable: true },
];

// Component 6957 — "EFT yang ada dalam payment_record tapi tiada dalam voucher_details".
const paymentRecord2PelikColumns: CheckErrorColumn[] = [
  { key: "prePaymentRecordId", label: "Payment Id", sortable: true, hideable: true },
  { key: "preModType", label: "Payment mode", sortable: true, hideable: true },
  { key: "prePaymentNo", label: "Eft No", sortable: true, hideable: true },
];

// Component 6956 — "Voucher yang ada EFT No tapi EFT No tu tak wujud dalam payment_record".
const paymentRecordPelikColumns: CheckErrorColumn[] = [
  { key: "vmaVoucherId", label: "Voucher Id", sortable: true, hideable: true },
  { key: "vmaVoucherNo", label: "Voucher No", sortable: true, hideable: true },
  { key: "vdePaymentNo", label: "Eft dlm Voucher", sortable: true, hideable: true },
];

// Component 7135 — "URL BRF yg hilang".
const urlBrfHilangColumns: CheckErrorColumn[] = [
  { key: "wtkApplicationId", label: "BRF No", sortable: true, hideable: true },
  { key: "wtkTaskId", label: "Task Id", sortable: true, hideable: true },
  { key: "wtkProcessId", label: "Process ID", sortable: true, hideable: true },
  { key: "wtkWorkflowCode", label: "Wf Code", sortable: true, hideable: true },
  { key: "wtkTaskName", label: "Task Name", sortable: true, hideable: true },
  { key: "wtkTaskUrl", label: "Task URL", sortable: true, hideable: true },
  { key: "wtkStatus", label: "Task Status", sortable: true, hideable: true },
  { key: "createdby", label: "Create By", sortable: true, hideable: true },
];

// Component 7935 — "Data yg xda dlm allocatation receive".
const resitNoAllocateColumns: CheckErrorColumn[] = [
  { key: "pdeDocumentNo", label: "Document No", sortable: true, hideable: true },
  { key: "pdeReference", label: "Reference No", sortable: true, hideable: true },
  { key: "pdeEntAmt", label: "Amount", sortable: true, hideable: true, align: "right", formatter: (r) => toCurrency(r.pdeEntAmt) },
];
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">{{ PAGE_BREADCRUMB }}</h1>
      <h1 class="sr-only">{{ PAGE_TITLE }}</h1>

      <CheckErrorSection
        title="Cek bill master yang hilang data"
        export-name="Bill Master Errors"
        :columns="billMasterColumns"
        :fetcher="listCheckErrorBillMaster"
        default-sort-by="bim_bills_id"
      />

      <CheckErrorSection
        title="Cek data voucher detail kaki hilang"
        export-name="Voucher Detail Errors"
        :columns="voucherDetailColumns"
        :fetcher="listCheckErrorVoucherDetail"
        default-sort-by="vma_voucher_id"
      />

      <CheckErrorSection
        title="Voucher yg hilang payee dkat master"
        export-name="Voucher Master Errors"
        :columns="voucherMasterColumns"
        :fetcher="listCheckErrorVoucherMaster"
        default-sort-by="vma_voucher_id"
      />

      <CheckErrorSection
        title="EFT yang ada dalam payment_record tapi tiada dalam voucher_details"
        export-name="EFT In PaymentRecord Only"
        :columns="paymentRecord2PelikColumns"
        :fetcher="listCheckErrorPayment2Pelik"
        default-sort-by="pre_payment_record_id"
      />

      <CheckErrorSection
        title="Voucher yang ada EFT No tapi EFT No tu tak wujud dalam payment_record"
        export-name="EFT In Voucher Only"
        :columns="paymentRecordPelikColumns"
        :fetcher="listCheckErrorPaymentPelik"
        default-sort-by="vma_voucher_id"
      />

      <CheckErrorSection
        title="URL BRF yg hilang"
        export-name="URL BRF Missing"
        :columns="urlBrfHilangColumns"
        :fetcher="listCheckErrorUrlBrfHilang"
        default-sort-by="wtk_task_id"
      />

      <CheckErrorSection
        title="Data yg xda dlm allocatation receive"
        export-name="Resit Without Allocation"
        :columns="resitNoAllocateColumns"
        :fetcher="listCheckErrorResit"
        default-sort-by="pde_document_no"
      />
    </div>
  </AdminLayout>
</template>
