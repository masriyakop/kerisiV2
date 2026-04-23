<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PtptnDataMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Student Finance > PTPTN Data (PAGEID 857 / MENUID 1031).
 *
 * Legacy BL: `API_PTPTN_DATA` (referenced by the datatable `dt_ajax`). The
 * BL source was not shipped in PAGE_SECOND_LEVEL_MENU.json (the attached
 * COMPONENT_JS is a Fund Type boilerplate fragment that is reused across
 * many pages as a placeholder). Column list is derived from the
 * `Datatable column details` block and the live `kerisiv2` schema:
 * `ptptn_data_master` + `ptptn_data_detl` (no extra joins needed for the
 * listing; details are only loaded by the View modal).
 *
 * Delete is intentionally scoped to rows where
 * `pdm_is_process_complete = 'N'` — this mirrors the legacy dt_js guard
 * (`(row.isProcessed == 'N')?'':'disabled'`).
 */
class PtptnDataController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        'pdm_reference_no',
        'pdm_date',
        'pdm_file_name',
        'ptptn_source',
        'pdm_total_stud',
        'pdm_warrant_amt',
        'pdm_deduction_amt',
        'pdm_balance_amt',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'pdm_date');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'pdm_date';
        }

        $query = PtptnDataMaster::query();

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(pdm_reference_no, ''),
                    IFNULL(pdm_file_name, ''),
                    IFNULL(ptptn_source, ''),
                    IFNULL(DATE_FORMAT(pdm_date, '%d/%m/%Y'), '')
                )) LIKE ?",
                [$like]
            );
        }

        $total = (clone $query)->count();

        $rows = (clone $query)
            ->orderBy($sortBy, $sortDir)
            ->orderBy('pdm_ptptn_data_master_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (PtptnDataMaster $r, int $i) use ($page, $limit) {
            $date = $r->pdm_date ? Carbon::parse($r->pdm_date)->format('d/m/Y') : '';

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'mID' => (int) $r->pdm_ptptn_data_master_id,
                'referenceNo' => $r->pdm_reference_no,
                'date' => $date,
                'fileName' => $r->pdm_file_name,
                'source' => $r->ptptn_source,
                'totalStudent' => $r->pdm_total_stud !== null ? (int) $r->pdm_total_stud : null,
                'totalWarrant' => $r->pdm_warrant_amt !== null ? (float) $r->pdm_warrant_amt : null,
                'deductAmt' => $r->pdm_deduction_amt !== null ? (float) $r->pdm_deduction_amt : null,
                'balanceAmt' => $r->pdm_balance_amt !== null ? (float) $r->pdm_balance_amt : null,
                'isProcessed' => $r->pdm_is_process_complete ?? 'N',
                'isInvGenComplete' => $r->pdm_is_inv_gen_complete ?? 'N',
                'isExportComplete' => $r->pdm_is_export_complete ?? 'N',
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $master = PtptnDataMaster::query()->find($id);
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'PTPTN data record not found');
        }

        $details = $master->details()
            ->orderBy('pdd_ptptn_data_detl_id')
            ->get()
            ->map(fn ($d) => [
                'id' => (int) $d->pdd_ptptn_data_detl_id,
                'studentId' => $d->std_student_id,
                'studentName' => $d->pdd_student_name,
                'studentIc' => $d->pdd_student_ic,
                'uniCode' => $d->pdd_uni_code,
                'studentGrp' => $d->pdd_studentgrp,
                'warrantNo' => $d->pdd_warrant_no,
                'warrantAmt' => $d->pdd_warrant_amt !== null ? (float) $d->pdd_warrant_amt : null,
                'deductionAmt' => $d->pdd_deduction_amt !== null ? (float) $d->pdd_deduction_amt : null,
                'balanceAmt' => $d->pdd_balance_amt !== null ? (float) $d->pdd_balance_amt : null,
                'statusPtptn' => $d->pdd_statusptptn,
                'payDate' => $d->pdd_paydate ? Carbon::parse($d->pdd_paydate)->format('d/m/Y') : null,
                'invoiceNo' => $d->pdd_invoice_no,
                'invoiceAmt' => $d->pdd_invoice_amt !== null ? (float) $d->pdd_invoice_amt : null,
                'creditStatus' => $d->pdd_credit_status,
            ])
            ->values();

        return $this->sendOk([
            'header' => [
                'mID' => (int) $master->pdm_ptptn_data_master_id,
                'referenceNo' => $master->pdm_reference_no,
                'date' => $master->pdm_date ? Carbon::parse($master->pdm_date)->format('d/m/Y H:i') : null,
                'fileName' => $master->pdm_file_name,
                'source' => $master->ptptn_source,
                'totalStudent' => $master->pdm_total_stud !== null ? (int) $master->pdm_total_stud : null,
                'totalWarrant' => $master->pdm_warrant_amt !== null ? (float) $master->pdm_warrant_amt : null,
                'deductAmt' => $master->pdm_deduction_amt !== null ? (float) $master->pdm_deduction_amt : null,
                'balanceAmt' => $master->pdm_balance_amt !== null ? (float) $master->pdm_balance_amt : null,
                'isProcessed' => $master->pdm_is_process_complete ?? 'N',
                'isInvGenComplete' => $master->pdm_is_inv_gen_complete ?? 'N',
                'isExportComplete' => $master->pdm_is_export_complete ?? 'N',
            ],
            'details' => $details,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $master = PtptnDataMaster::query()->find($id);
        if (! $master) {
            return $this->sendError(404, 'NOT_FOUND', 'PTPTN data record not found');
        }

        // Legacy rule: Delete is only allowed while the batch has not been
        // processed yet (pdm_is_process_complete = 'N'). The JS gate disables
        // the button, but we also enforce it server-side to prevent tampering.
        if (($master->pdm_is_process_complete ?? 'N') !== 'N') {
            return $this->sendError(
                409,
                'PTPTN_ALREADY_PROCESSED',
                'PTPTN data has already been processed and cannot be deleted.',
            );
        }

        $master->details()->delete();
        $master->delete();

        return $this->sendOk(['success' => true]);
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
