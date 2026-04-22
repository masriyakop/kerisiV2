<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\VendCustomerSupplier;
use App\Models\VendSupplierAccount;
use App\Models\VendorAddress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Account Receivable > Debtor" endpoints (PAGEID 1415 / MENUID 1727).
 *
 * Source: FIMS BL `DT_AR_DEBTOR` (datatable: ?listing=1, delete: ?delete=1,
 * export: ?downloadCSV=1). Lists rows in `vend_customer_supplier` with
 * `vcs_isdebtor='Y'`, enriched with the outstanding balance from
 * `cust_invoice_master` (SUM(cim_bal_amt) WHERE cim_status='APPROVE'
 * AND cim_cust_id = vendor_code AND cim_bal_amt > 0 AND cim_system_id='AR_INV').
 *
 * Smart filter: Status (ACTIVE/INACTIVE/BLACKLIST), matched against
 * `vcs_extended_field->>'$.statusDesc'`.
 *
 * Delete cascades to `vend_supplier_account`, `vendor_address`, then
 * `vend_customer_supplier`, wrapped in a transaction.
 *
 * View / Edit / Reset Password pages (legacy menuID 1728 / 2598) are out of
 * scope for this migration; the frontend renders those buttons disabled.
 */
class DebtorController extends Controller
{
    use ApiResponse;

    private const STATUSES = ['ACTIVE', 'INACTIVE', 'BLACKLIST'];

    public function options(): JsonResponse
    {
        return $this->sendOk([
            'status' => array_map(fn ($s) => ['id' => $s, 'label' => $s], self::STATUSES),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));
        $sortBy = (string) $request->input('sort_by', 'vcs_vendor_code');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = ['vcs_vendor_code', 'vcs_vendor_name', 'status'];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'vcs_vendor_code';
        }

        $query = VendCustomerSupplier::query()->where('vcs_isdebtor', 'Y');

        if ($status !== '' && in_array(strtoupper($status), self::STATUSES, true)) {
            $query->whereRaw("vcs_extended_field->>'$.statusDesc' = ?", [strtoupper($status)]);
        }

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->where(function (Builder $b) use ($like) {
                $b->orWhereRaw("LOWER(IFNULL(vcs_vendor_code, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(vcs_vendor_name, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(vcs_extended_field->>'$.statusDesc', '')) LIKE ?", [$like]);
            });
        }

        $total = (clone $query)->count();

        $orderColumn = match ($sortBy) {
            'status' => DB::raw("vcs_extended_field->>'$.statusDesc'"),
            default => $sortBy,
        };

        $rows = $query
            ->select([
                'vcs_id',
                'vcs_vendor_code',
                'vcs_vendor_name',
                DB::raw("vcs_extended_field->>'\$.statusDesc' as status_desc"),
            ])
            ->orderBy($orderColumn, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        // Resolve outstanding balance per debtor in a single grouped query so we
        // avoid N+1 sub-queries on the datatable.
        $codes = $rows->pluck('vcs_vendor_code')->filter()->unique()->values()->all();
        $balances = [];
        if (! empty($codes)) {
            $balances = DB::connection('mysql_secondary')
                ->table('cust_invoice_master')
                ->where('cim_status', 'APPROVE')
                ->where('cim_bal_amt', '>', 0)
                ->where('cim_system_id', 'AR_INV')
                ->whereIn('cim_cust_id', $codes)
                ->groupBy('cim_cust_id')
                ->selectRaw('cim_cust_id, SUM(cim_bal_amt) as total')
                ->pluck('total', 'cim_cust_id')
                ->toArray();
        }

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'id' => (int) $r->vcs_id,
            'vendorCode' => (string) $r->vcs_vendor_code,
            'vendorName' => $r->vcs_vendor_name,
            'status' => $r->status_desc,
            'outstandingAmount' => isset($balances[$r->vcs_vendor_code])
                ? (float) $balances[$r->vcs_vendor_code]
                : 0.0,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $debtor = VendCustomerSupplier::query()
            ->where('vcs_id', $id)
            ->where('vcs_isdebtor', 'Y')
            ->first(['vcs_id', 'vcs_vendor_code']);

        if (! $debtor) {
            return $this->sendError(404, 'NOT_FOUND', 'Debtor not found');
        }

        DB::connection('mysql_secondary')->transaction(function () use ($debtor) {
            VendSupplierAccount::query()
                ->where('vcs_vendor_code', $debtor->vcs_vendor_code)
                ->delete();

            VendorAddress::query()
                ->where('vcs_vendor_code', $debtor->vcs_vendor_code)
                ->delete();

            VendCustomerSupplier::query()
                ->where('vcs_id', $debtor->vcs_id)
                ->delete();
        });

        return $this->sendOk(['success' => true]);
    }

    private function likeEscape(string $needle): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
    }
}
