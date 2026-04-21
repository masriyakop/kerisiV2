<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Cek yang mungkin error" diagnostics screen (legacy PAGEID 2253 / MENUID 2740).
 *
 * Ports MM_API_MAINTANANCE_CEKERROR into seven read-only list endpoints. All
 * queries hit the `mysql_secondary` connection (DB_SECOND_DATABASE) and use
 * Eloquent's query builder exclusively — no raw SQL strings.
 */
class CheckErrorController extends Controller
{
    use ApiResponse;

    private const CONN = 'mysql_secondary';

    public function billMaster(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'bim_bills_id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc'));
        [$page, $limit, $sortBy, $sortDir] = $this->resolvePaging(
            $request,
            $sortBy,
            $sortDir,
            [
                'bim_bills_id',
                'bim_bills_no',
                'bim_bills_type',
                'bim_bill_amt',
                'bim_status',
                'bim_payto_id',
                'bim_payto_type',
                'bim_payto_name',
                'bim_payto_address',
                'createdby',
                'updatedby',
                'bim_system_id',
                'bim_payee_count',
            ],
        );

        $base = DB::connection(self::CONN)
            ->table('bills_master')
            ->where(function (Builder $builder) {
                $builder->whereNull('bim_cust_invoice_no')
                    ->orWhereNull('bim_cust_invoice_date')
                    ->orWhereNull('bim_payto_type')
                    ->orWhereNull('bim_payto_id');
            })
            ->whereNotIn('bim_status', ['DRAFT', 'REJECT']);

        if ($q !== '') {
            $this->applyConcatWsLike($base, [
                'bim_bills_id',
                'bim_bills_no',
                'bim_bills_type',
                'bim_bill_amt',
                'bim_status',
                'bim_payto_id',
                'bim_payto_type',
                'bim_payto_name',
                'bim_payto_address',
                'createdby',
                'updatedby',
                'bim_system_id',
                'bim_payee_count',
            ], $q);
        }

        $total = (clone $base)->count();
        $rows = (clone $base)
            ->select([
                'bim_bills_id',
                'bim_bills_no',
                'bim_bills_type',
                'bim_bill_amt',
                'bim_status',
                'bim_payto_id',
                'bim_payto_type',
                'bim_payto_name',
                'bim_payto_address',
                'createdby',
                'updatedby',
                'bim_system_id',
                'bim_payee_count',
            ])
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'bim_bills_id' => $row->bim_bills_id,
                'bim_bills_no' => $row->bim_bills_no,
                'bim_bills_type' => $row->bim_bills_type,
                'bim_bill_amt' => $row->bim_bill_amt,
                'bim_status' => $row->bim_status,
                'bim_payto_id' => $row->bim_payto_id,
                'bim_payto_type' => $row->bim_payto_type,
                'bim_payto_name' => $row->bim_payto_name,
                'bim_payto_address' => $row->bim_payto_address,
                'createdby' => $row->createdby,
                'updatedby' => $row->updatedby,
                'bim_system_id' => $row->bim_system_id,
                'bim_payee_count' => $row->bim_payee_count,
            ];
        });

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    public function voucherDetail(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        [$page, $limit, $sortBy, $sortDir] = $this->resolvePaging(
            $request,
            (string) $request->input('sort_by', 'vma_voucher_id'),
            strtolower((string) $request->input('sort_dir', 'asc')),
            ['vma_voucher_id', 'dt', 'cr', 'beza'],
        );

        $inner = DB::connection(self::CONN)
            ->table('voucher_details')
            ->select([
                'vma_voucher_id',
                DB::raw("SUM(CASE WHEN vde_trans_type='DT' THEN IFNULL(vde_amount,0) ELSE 0 END) as dt"),
                DB::raw("SUM(CASE WHEN vde_trans_type='CR' THEN IFNULL(-vde_amount,0) ELSE 0 END) as cr"),
                DB::raw("SUM(CASE WHEN vde_trans_type='DT' THEN IFNULL(vde_amount,0) WHEN vde_trans_type='CR' THEN IFNULL(-vde_amount,0) ELSE 0 END) as beza"),
            ])
            ->groupBy('vma_voucher_id');

        $base = DB::connection(self::CONN)
            ->query()
            ->fromSub($inner, 'z')
            ->where('beza', '<>', 0);

        if ($q !== '') {
            $this->applyConcatWsLike($base, ['vma_voucher_id', 'dt', 'cr', 'beza'], $q);
        }

        $total = (clone $base)->count();
        $rows = (clone $base)
            ->select(['vma_voucher_id', 'dt', 'cr', 'beza'])
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'vma_voucher_id' => $row->vma_voucher_id,
                // Lowercase keys so `CamelCaseMiddleware` produces clean names
                // (legacy `DT` / `CR` / `BEZA` would become `dT` / `cR` / `bEZA`).
                'dt' => $this->asFloat($row->dt),
                'cr' => $this->asFloat($row->cr),
                'beza' => $this->asFloat($row->beza),
            ];
        });

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    public function voucherMaster(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        [$page, $limit, $sortBy, $sortDir] = $this->resolvePaging(
            $request,
            (string) $request->input('sort_by', 'vma_voucher_id'),
            strtolower((string) $request->input('sort_dir', 'asc')),
            ['vma_voucher_id', 'vma_voucher_no', 'vma_vch_status', 'vma_payto_type', 'vma_payto_id', 'vma_payto_name'],
        );

        $base = DB::connection(self::CONN)
            ->table('voucher_master')
            ->where(function (Builder $builder) {
                $builder->whereNull('vma_payto_type')
                    ->orWhereNull('vma_payto_id')
                    ->orWhereNull('vma_payto_name');
            })
            ->whereNotIn('vma_vch_status', ['DRAFT', 'REJECT']);

        if ($q !== '') {
            $this->applyConcatWsLike($base, [
                'vma_voucher_id',
                'vma_voucher_no',
                'vma_vch_status',
                'vma_payto_type',
                'vma_payto_id',
                'vma_payto_name',
            ], $q);
        }

        $total = (clone $base)->distinct()->count('vma_voucher_id');
        $rows = (clone $base)
            ->select([
                'vma_voucher_id',
                'vma_voucher_no',
                'vma_vch_status',
                'vma_payto_type',
                'vma_payto_id',
                'vma_payto_name',
            ])
            ->distinct()
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'vma_voucher_id' => $row->vma_voucher_id,
                'vma_voucher_no' => $row->vma_voucher_no,
                'vma_vch_status' => $row->vma_vch_status,
                'vma_payto_type' => $row->vma_payto_type,
                'vma_payto_id' => $row->vma_payto_id,
                'vma_payto_name' => $row->vma_payto_name,
            ];
        });

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    public function paymentRecord2Pelik(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        [$page, $limit, $sortBy, $sortDir] = $this->resolvePaging(
            $request,
            (string) $request->input('sort_by', 'pre_payment_record_id'),
            strtolower((string) $request->input('sort_dir', 'asc')),
            ['pre_payment_record_id', 'pre_payment_no', 'pre_mod_type'],
        );

        $sub = DB::connection(self::CONN)
            ->table('voucher_details')
            ->select('vde_payment_no')
            ->whereNotNull('vde_payment_no');

        $base = DB::connection(self::CONN)
            ->table('payment_record')
            ->whereNotIn('pre_payment_no', $sub);

        if ($q !== '') {
            $this->applyConcatWsLike($base, ['pre_payment_record_id', 'pre_payment_no', 'pre_mod_type'], $q);
        }

        $total = (clone $base)->count();
        $rows = (clone $base)
            ->select(['pre_payment_record_id', 'pre_payment_no', 'pre_mod_type'])
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pre_payment_record_id' => $row->pre_payment_record_id,
                'pre_payment_no' => $row->pre_payment_no,
                'pre_mod_type' => $row->pre_mod_type,
            ];
        });

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    public function paymentRecordPelik(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        [$page, $limit, $sortBy, $sortDir] = $this->resolvePaging(
            $request,
            (string) $request->input('sort_by', 'vma_voucher_id'),
            strtolower((string) $request->input('sort_dir', 'asc')),
            ['vma_voucher_id', 'vma_voucher_no', 'vde_payment_no'],
        );

        $sub = DB::connection(self::CONN)
            ->table('payment_record')
            ->select('pre_payment_no')
            ->whereNotNull('pre_payment_no');

        $base = DB::connection(self::CONN)
            ->table('voucher_details as vd')
            ->join('voucher_master as vm', 'vd.vma_voucher_id', '=', 'vm.vma_voucher_id')
            ->whereNotIn('vd.vde_payment_no', $sub)
            ->where('vd.vde_trans_type', 'CR');

        if ($q !== '') {
            $this->applyConcatWsLike($base, ['vd.vma_voucher_id', 'vd.vde_payment_no', 'vm.vma_voucher_no'], $q);
        }

        $total = (clone $base)->count();
        $rows = (clone $base)
            ->select([
                'vd.vma_voucher_id',
                'vd.vde_payment_no',
                'vm.vma_voucher_no',
            ])
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'vma_voucher_id' => $row->vma_voucher_id,
                'vma_voucher_no' => $row->vma_voucher_no,
                'vde_payment_no' => $row->vde_payment_no,
            ];
        });

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    public function urlBrfHilang(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        [$page, $limit, $sortBy, $sortDir] = $this->resolvePaging(
            $request,
            (string) $request->input('sort_by', 'wtk_task_id'),
            strtolower((string) $request->input('sort_dir', 'asc')),
            [
                'wtk_application_id',
                'wtk_task_id',
                'wtk_process_id',
                'wtk_workflow_code',
                'wtk_task_name',
                'wtk_task_url',
                'wtk_status',
                'createdby',
            ],
        );

        $base = DB::connection(self::CONN)
            ->table('wf_task')
            ->where('wtk_workflow_code', 'BILL_INTEG_REF_STUD')
            ->where('wtk_status', 'NEW')
            ->where(function (Builder $builder) {
                $builder->where('wtk_task_url', 'like', '%http%')
                    ->orWhereNull('wtk_task_url')
                    ->orWhere('wtk_task_url', '=', '');
            });

        if ($q !== '') {
            $this->applyConcatWsLike($base, [
                'wtk_task_id',
                'wtk_process_id',
                'wtk_application_id',
                'wtk_workflow_code',
                'wtk_task_name',
                'wtk_task_url',
                'wtk_status',
                'createdby',
            ], $q);
        }

        $total = (clone $base)->distinct()->count('wtk_task_id');
        $rows = (clone $base)
            ->select([
                'wtk_application_id',
                'wtk_task_id',
                'wtk_process_id',
                'wtk_workflow_code',
                'wtk_task_name',
                'wtk_task_url',
                'wtk_status',
                'createdby',
            ])
            ->distinct()
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'wtk_application_id' => $row->wtk_application_id,
                'wtk_task_id' => $row->wtk_task_id,
                'wtk_process_id' => $row->wtk_process_id,
                'wtk_workflow_code' => $row->wtk_workflow_code,
                'wtk_task_name' => $row->wtk_task_name,
                'wtk_task_url' => $row->wtk_task_url,
                'wtk_status' => $row->wtk_status,
                'createdby' => $row->createdby,
            ];
        });

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    public function resitNoAllocate(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        [$page, $limit, $sortBy, $sortDir] = $this->resolvePaging(
            $request,
            (string) $request->input('sort_by', 'pde_document_no'),
            strtolower((string) $request->input('sort_dir', 'asc')),
            ['pde_document_no', 'pde_reference', 'pde_ent_amt'],
        );

        $sub = DB::connection(self::CONN)
            ->table('budget_transaction')
            ->select('bgt_ref');

        $base = DB::connection(self::CONN)
            ->table('posting_details')
            ->whereNotIn('pde_document_no', $sub)
            ->where('acm_acct_code', 'like', 'A0111%')
            ->where('pde_trans_type', 'DT')
            ->where('fty_fund_type', '!=', 'E01');

        if ($q !== '') {
            $this->applyConcatWsLike($base, ['pde_document_no', 'pde_reference', 'pde_ent_amt'], $q);
        }

        $total = (clone $base)->count();
        $rows = (clone $base)
            ->select(['pde_document_no', 'pde_reference', 'pde_ent_amt'])
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'pde_document_no' => $row->pde_document_no,
                'pde_reference' => $row->pde_reference,
                'pde_ent_amt' => $this->asFloat($row->pde_ent_amt),
            ];
        });

        return $this->sendOk($data, $this->meta($page, $limit, $total));
    }

    /**
     * @param  array<int, string>  $allowedSortBy
     * @return array{0:int,1:int,2:string,3:string}
     */
    private function resolvePaging(Request $request, string $sortBy, string $sortDir, array $allowedSortBy): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 5));
        $sortByNormalized = $sortBy;
        if (! in_array($sortByNormalized, $allowedSortBy, true)) {
            $sortByNormalized = $allowedSortBy[0];
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        return [$page, $limit, $sortByNormalized, $sortDir];
    }

    /**
     * Apply the legacy `CONCAT_WS('__', col1, col2, …) LIKE '%needle%'` filter
     * to the supplied query builder while escaping LIKE metacharacters.
     *
     * @param  array<int, string>  $columns
     */
    private function applyConcatWsLike(Builder $builder, array $columns, string $needle): void
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($needle, 'UTF-8')).'%';
        $joined = implode(', ', array_map(static fn (string $col) => "IFNULL($col, '')", $columns));
        $builder->whereRaw("LOWER(CONCAT_WS('__', $joined)) LIKE ?", [$like]);
    }

    private function asFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    /**
     * @return array<string, int>
     */
    private function meta(int $page, int $limit, int $total): array
    {
        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ];
    }
}
