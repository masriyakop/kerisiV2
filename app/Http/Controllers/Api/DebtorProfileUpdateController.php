<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\VendCustomerSupplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Debtor Portal > List of Profile Update Application" endpoint
 * (PAGEID 2155 / MENUID 2608).
 *
 * Source: FIMS BL `MZ_BL_DEBTOR_PORTAL_LIST` (?dtListing=1). The legacy
 * listing shows a single row per debtor (the logged-in vendor record)
 * with a `statusUpdateDebtor` flag pulled from the pending temp copy in
 * `temp_vend_customer_supplier.vcs_extended_field->>'$.statusUpdateDebtor'`.
 *
 * # Scoping / authentication
 * The legacy code filters `vcs_vendor_code = $_USER['USERNAME']` — i.e. the
 * authenticated user's login name IS the vendor code. The same mapping is
 * used here via `auth()->user()->name`. If the logged-in user has no
 * matching vendor record an empty page is returned (not an error), which
 * mirrors legacy behaviour for internal/admin users hitting this page.
 *
 * This endpoint is read-only; the "View" action on each row navigates the
 * SPA to the Debtor Portal / Debtor Profile page (MENUID 2189) which is
 * not part of the current Portal migration wave.
 */
class DebtorProfileUpdateController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'createddate');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $vendorCode = $this->vendorCode($request);

        if ($vendorCode === null) {
            return $this->sendOk([], [
                'page' => $page,
                'limit' => $limit,
                'total' => 0,
                'totalPages' => 0,
            ]);
        }

        $allowedSort = ['vcs_vendor_code', 'vcs_vendor_name', 'createddate', 'vcs_vendor_status'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'createddate';
        }

        $query = VendCustomerSupplier::query()
            ->from('vend_customer_supplier as vcs')
            ->where('vcs.vcs_vendor_code', $vendorCode)
            ->select([
                'vcs.vcs_vendor_code',
                'vcs.vcs_vendor_name',
                'vcs.vcs_vendor_status',
                'vcs.vcs_iscreditor',
                'vcs.vcs_vendor_bank',
                'vcs.vcs_bank_accno',
                'vcs.createddate',
                DB::raw(
                    '(SELECT tmp.vcs_extended_field->>\'$.statusUpdateDebtor\''
                    . ' FROM temp_vend_customer_supplier tmp'
                    . ' WHERE tmp.vcs_vendor_code = vcs.vcs_vendor_code'
                    . ' LIMIT 1) as status_update_debtor'
                ),
            ]);

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->where(function ($b) use ($like) {
                foreach ([
                    'vcs.vcs_vendor_code',
                    'vcs.vcs_vendor_name',
                    'vcs.vcs_vendor_bank',
                    'vcs.vcs_bank_accno',
                ] as $col) {
                    $b->orWhereRaw("LOWER(IFNULL($col, '')) LIKE ?", [$like]);
                }
            });
        }

        $total = (clone $query)->count();

        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'vendorCode' => $r->vcs_vendor_code,
            'vendorName' => $r->vcs_vendor_name,
            'vendorStatus' => $r->vcs_vendor_status === '1' ? 'ACTIVE' : 'NON-ACTIVE',
            'isCreditor' => $r->vcs_iscreditor === 'N' ? 'NO' : 'YES',
            'bankName' => $r->vcs_vendor_bank,
            'bankAccountNo' => $r->vcs_bank_accno,
            'statusUpdateDebtor' => $r->status_update_debtor,
            'createdDate' => $r->createddate,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    /**
     * Resolve the vendor code for the currently authenticated user.
     *
     * Legacy FIMS assumed `user login name === vcs_vendor_code`. That same
     * mapping is used here via the `name` column on the Laravel user. If
     * no authenticated user is attached (shouldn't happen behind
     * auth:sanctum) or the name is empty, null is returned so the caller
     * can short-circuit to an empty page.
     */
    private function vendorCode(Request $request): ?string
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }
        $code = trim((string) ($user->name ?? ''));
        return $code === '' ? null : $code;
    }

    private function likeEscape(string $needle): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
    }
}
