<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * List of Petty Cash Application (PAGEID 1217 / MENUID 1490).
 *
 * Source: FIMS BL `API_PETTYCASH_LISTAPPLICATIONPETTYCASH` (?ListPettyCash_dt=1).
 * Simplified from legacy: optional `staff_id` restricts rows to applications
 * linked (via petty_cash_details.pcm_id) to petty_cash_main rows where the
 * staff is entry/approver/verifier/holder. Omit `staff_id` for an all-rows
 * (finance-style) listing. Optional `wf_staff_id` controls workflow "editable"
 * detection against wf_task.
 */
class PettyCashApplicationListController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        'pms_application_no' => 'pms.pms_application_no',
        'pms_request_by' => 'request_by_label',
        'pms_request_date' => 'pms.pms_request_date',
        'pms_total_amt' => 'pms.pms_total_amt',
        'reject_amt' => 'reject_amt',
        'cancel_amt' => 'cancel_amt',
        'paid_amount' => 'paid_amount',
        'pms_status' => 'pms.pms_status',
    ];

    public function options(): JsonResponse
    {
        $statuses = DB::connection('mysql_secondary')
            ->table('petty_cash_master')
            ->select('pms_status as id', 'pms_status as label')
            ->where('pms_status', '!=', '')
            ->whereNotNull('pms_status')
            ->distinct()
            ->orderBy('pms_status')
            ->get()
            ->map(fn ($r) => ['id' => (string) $r->id, 'label' => (string) $r->label])
            ->values();

        return $this->sendOk(['status' => $statuses]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 5)));
        $q = trim((string) $request->input('q', ''));
        $staffId = trim((string) $request->input('staff_id', ''));
        $wfStaffId = trim((string) $request->input('wf_staff_id', ''));
        $smartRequestBy = trim((string) $request->input('pms_request_by', ''));
        $smartStatus = trim((string) $request->input('pms_status', ''));

        $sortByKey = (string) $request->input('sort_by', 'pms_application_no');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderExpr = self::SORTABLE[$sortByKey] ?? 'pms.pms_application_no';

        $rejectCancelSub = DB::connection('mysql_secondary')
            ->table('petty_cash_details')
            ->select([
                'pms_application_no',
                DB::raw("SUM(CASE WHEN pcd_status = 'REJECT' THEN pcd_trans_amt ELSE 0 END) as reject_amt"),
                DB::raw("SUM(CASE WHEN pcd_status = 'CANCEL' THEN pcd_trans_amt ELSE 0 END) as cancel_amt"),
            ])
            ->whereIn('pcd_status', ['REJECT', 'CANCEL'])
            ->groupBy('pms_application_no');

        $pcmIds = $staffId !== '' ? $this->pcmIdsForStaff($staffId) : collect();

        $query = DB::connection('mysql_secondary')
            ->table('petty_cash_master as pms')
            ->leftJoinSub($rejectCancelSub, 'rc', 'rc.pms_application_no', '=', 'pms.pms_application_no')
            ->when($pcmIds->isNotEmpty(), function ($q) use ($pcmIds) {
                $ids = $pcmIds->all();
                $q->whereExists(function ($sub) use ($ids) {
                    $sub->select(DB::raw('1'))
                        ->from('petty_cash_details as pcd2')
                        ->whereColumn('pcd2.pms_application_no', 'pms.pms_application_no')
                        ->whereIn('pcd2.pcm_id', $ids);
                });
            })
            ->when($smartRequestBy !== '', function ($q) use ($smartRequestBy) {
                $like = $this->likeEscape(mb_strtolower($smartRequestBy, 'UTF-8'));
                $q->whereRaw(
                    "LOWER(CONCAT_WS(' - ', IFNULL(pms.pms_request_by,''), IFNULL(pms.pms_extended_field->>'$.pms_request_by_desc',''))) LIKE ?",
                    [$like]
                );
            })
            ->when($smartStatus !== '', function ($q) use ($smartStatus) {
                $like = $this->likeEscape(mb_strtolower($smartStatus, 'UTF-8'));
                $q->whereRaw('LOWER(IFNULL(pms.pms_status, \'\')) LIKE ?', [$like]);
            })
            ->when($q !== '', function ($qry) use ($q) {
                $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
                $qry->whereRaw(
                    "LOWER(CONCAT_WS('__',
                        IFNULL(pms.pms_id,''),
                        IFNULL(pms.pms_application_no,''),
                        IFNULL(pms.pms_request_by,''),
                        IFNULL(pms.pms_extended_field->>'$.pms_request_by_desc',''),
                        IFNULL(DATE_FORMAT(pms.pms_request_date, '%d/%m/%Y'),''),
                        IFNULL(pms.pms_total_amt,''),
                        IFNULL(pms.pms_status,'')
                    )) LIKE ?",
                    [$like]
                );
            });

        $countQuery = clone $query;
        $total = (int) $countQuery->selectRaw('COUNT(DISTINCT pms.pms_id) as aggregate')->value('aggregate');

        $rows = (clone $query)
            ->select([
                'pms.pms_id',
                'pms.pms_application_no',
                DB::raw("CONCAT_WS(' - ', IFNULL(pms.pms_request_by,''), IFNULL(pms.pms_extended_field->>'$.pms_request_by_desc','')) as request_by_label"),
                'pms.pms_request_date',
                'pms.pms_total_amt',
                DB::raw('IFNULL(rc.reject_amt, 0) as reject_amt'),
                DB::raw('IFNULL(rc.cancel_amt, 0) as cancel_amt'),
                DB::raw('IFNULL(pms.pms_total_amt, 0) - IFNULL(rc.reject_amt, 0) - IFNULL(rc.cancel_amt, 0) as paid_amount'),
                'pms.pms_status',
            ])
            ->distinct()
            ->orderBy($orderExpr, $sortDir)
            ->orderBy('pms.pms_id', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $pmsIds = $rows->pluck('pms_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $editableSet = [];
        if ($wfStaffId !== '' && $pmsIds !== []) {
            $editableSet = DB::connection('mysql_secondary')
                ->table('wf_task')
                ->where('wtk_workflow_code', 'PETTY_CASH')
                ->where('wtk_status', 'NEW')
                ->where('wtk_staff_id', $wfStaffId)
                ->whereIn('wtk_application_id', $pmsIds)
                ->pluck('wtk_application_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
                ->all();
        }

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit, $editableSet) {
            $pmsId = (int) $r->pms_id;
            $status = (string) ($r->pms_status ?? '');
            $editable = isset($editableSet[$pmsId]);

            $qsBase = http_build_query([
                'mode' => 'view',
                'status' => $status,
                'pms_id' => $pmsId,
            ]);
            $qsEdit = http_build_query([
                'mode' => 'edit',
                'status' => $status,
                'pms_id' => $pmsId,
            ]);

            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pms_id' => $pmsId,
                'pms_application_no' => $r->pms_application_no,
                'pms_request_by' => $r->request_by_label,
                'pms_request_date' => $r->pms_request_date
                    ? Carbon::parse($r->pms_request_date)->format('d/m/Y')
                    : '',
                'pms_total_amt' => $r->pms_total_amt !== null ? (float) $r->pms_total_amt : null,
                'reject_amt' => isset($r->reject_amt) ? (float) $r->reject_amt : 0.0,
                'cancel_amt' => isset($r->cancel_amt) ? (float) $r->cancel_amt : 0.0,
                'paid_amount' => isset($r->paid_amount) ? (float) $r->paid_amount : 0.0,
                'pms_status' => $status,
                'editable' => $editable ? 'Y' : 'N',
                'url_view' => '/admin/kerisi/m/1872?'.$qsBase,
                'url_edit' => '/admin/kerisi/m/1872?'.$qsEdit,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    /**
     * Return all data required to render BORANG PERMOHONAN PANJAR WANG RUNCIT.
     */
    public function show(int $id): JsonResponse
    {
        $pms = DB::connection('mysql_secondary')
            ->table('petty_cash_master')
            ->where('pms_id', $id)
            ->first();

        if (! $pms) {
            return $this->sendError(404, 'NOT_FOUND', 'Petty cash application not found');
        }

        $ext = json_decode($pms->pms_extended_field ?? '{}', true) ?? [];

        // Fetch requestor staff info
        $requestorInfo = $this->staffInfo((string) ($pms->pms_request_by ?? ''));
        $payToInfo     = $this->staffInfo((string) ($pms->pms_pay_to_id ?? ''));

        // Line items
        $lines = DB::connection('mysql_secondary')
            ->table('petty_cash_details')
            ->where('pms_application_no', $pms->pms_application_no)
            ->select(['pcd_id', 'pcd_trans_desc', 'pcd_receipt_no', 'acm_acct_code', 'pcd_trans_amt', 'pcd_status'])
            ->orderBy('pcd_id')
            ->get()
            ->map(fn ($l) => [
                'pcd_id'         => (int) $l->pcd_id,
                'pcd_trans_desc' => $l->pcd_trans_desc,
                'pcd_receipt_no' => $l->pcd_receipt_no,
                'acm_acct_code'  => $l->acm_acct_code,
                'pcd_trans_amt'  => $l->pcd_trans_amt !== null ? (float) $l->pcd_trans_amt : null,
                'pcd_status'     => $l->pcd_status,
            ])
            ->values();

        $requestDate = $pms->pms_request_date
            ? Carbon::parse($pms->pms_request_date)
            : null;

        return $this->sendOk([
            'pms_id'              => (int) $pms->pms_id,
            'pms_application_no'  => $pms->pms_application_no,
            'pms_request_by'      => $pms->pms_request_by,
            'pms_request_by_desc' => $ext['pms_request_by_desc'] ?? $requestorInfo['name'],
            'pms_pay_to_id'       => $pms->pms_pay_to_id,
            'pms_pay_to_id_desc'  => $ext['pms_pay_to_id_desc'] ?? $payToInfo['name'],
            'pms_request_date'    => $requestDate ? $requestDate->format('d/m/Y') : '',
            'pms_request_time'    => $requestDate ? $requestDate->format('h:i A') : '',
            'pms_total_amt'       => $pms->pms_total_amt !== null ? (float) $pms->pms_total_amt : 0.0,
            'pms_status'          => $pms->pms_status,
            'requestor_name'      => $requestorInfo['name'],
            'requestor_job'       => $requestorInfo['job'],
            'pay_to_name'         => $payToInfo['name'],
            'pay_to_job'          => $payToInfo['job'],
            'lines'               => $lines,
        ]);
    }

    /** Fetch staff name and job description. Returns empty strings if not found. */
    private function staffInfo(string $staffId): array
    {
        if ($staffId === '') {
            return ['name' => '', 'job' => ''];
        }

        $row = DB::connection('mysql_secondary')
            ->table('staff as s')
            ->leftJoin('staff_service as ss', 'ss.stf_staff_id', '=', 's.stf_staff_id')
            ->where('s.stf_staff_id', $staffId)
            ->select([
                's.stf_staff_name',
                DB::raw("ss.sts_extended_field->>'$.sts_job_desc' as job_desc"),
            ])
            ->first();

        if (! $row) {
            return ['name' => '', 'job' => ''];
        }

        return [
            'name' => trim((string) ($row->stf_staff_name ?? '')),
            'job'  => trim((string) ($row->job_desc ?? '')),
        ];
    }

    private function pcmIdsForStaff(string $staffId): Collection
    {
        return DB::connection('mysql_secondary')
            ->table('petty_cash_main')
            ->where(function ($q) use ($staffId) {
                $q->where('pcm_entry', $staffId)
                    ->orWhere('pcm_approver', $staffId)
                    ->orWhere('pcm_verifier', $staffId)
                    ->orWhere('pcm_approver2', $staffId)
                    ->orWhere('pcm_verifier2', $staffId)
                    ->orWhere('pcm_holder_id', $staffId);
            })
            ->distinct()
            ->pluck('pcm_id');
    }

    private function likeEscape(string $needleLower): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needleLower).'%';
    }
}
