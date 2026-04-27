<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\AuditSystemTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Audit Trail / System Transaction (PAGEID 3 / MENUID 5).
 *
 * Source: FIMS BL `V2_AUDIT_SYSTEM_TRANSACTION_API`. The legacy BL exposes
 *   ?dt_listing=1   — paginated datatable of audit rows
 *   ?showSQL=1      — fetch the full AUDIT_SQL for one row
 *   ?autoSuggest*   — typeahead lookups (menu, user)
 *
 * The new endpoints follow the project envelope (`data` + `meta`) and use
 * Eloquent / query builder against `mysql_secondary` exclusively — no raw
 * legacy SQL is preserved. Cross-database joins (PRUSER, staff, student,
 * vend_customer_supplier) are reproduced through the query builder.
 *
 * NOTE on connection topology: the legacy BL references three schemas —
 *   • fims_audit.system_transaction (audit ledger)
 *   • DB2.staff / DB2.student / DB2.vend_customer_supplier (DB_SECOND_DATABASE)
 *   • PRUSER (legacy "main" DB also reachable from the secondary user)
 * MySQL fully-qualified names work across schemas on the same server provided
 * the secondary connection user has SELECT on each. If PRUSER is on a
 * different host, the user-name enrichment will return null and the rows are
 * still rendered (matching the legacy "leave blank" behaviour).
 */
class AuditSystemTransactionController extends Controller
{
    use ApiResponse;

    private const ALLOWED_SORT = [
        'AUDIT_TIMESTAMP', 'AUDIT_ACTION', 'AUDIT_REQUEST_MENU_PATH',
        'AUDIT_BROWSER', 'AUDIT_CLIENT_IP', 'AUDIT_ID',
    ];

    private const EXCLUDED_REQUEST_URI_PATTERNS = [
        '%/bl_editor.php%',
        '%/express_editor.php%',
        '%/service.php%',
        '%/system_impexp.php%',
        '%/trigger_editor.php%',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $sortBy = (string) $request->input('sort_by', 'AUDIT_TIMESTAMP');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sortBy, self::ALLOWED_SORT, true)) {
            $sortBy = 'AUDIT_TIMESTAMP';
        }

        $dateFrom = trim((string) $request->input('search_date_from', ''));
        $dateTo = trim((string) $request->input('search_date_to', ''));
        $transType = trim((string) $request->input('search_trans_type', ''));
        $menuId = trim((string) $request->input('search_menu', ''));
        $browser = trim((string) $request->input('search_browser', ''));
        $userType = trim((string) $request->input('search_user_type', ''));
        $userId = trim((string) $request->input('search_user', ''));
        $q = trim((string) $request->input('q', ''));

        $base = AuditSystemTransaction::query()
            ->from('fims_audit.system_transaction as aud')
            ->whereNotNull('aud.USER_ID')
            ->where('aud.USER_ID', '!=', 1);

        foreach (self::EXCLUDED_REQUEST_URI_PATTERNS as $pattern) {
            $base->where('aud.AUDIT_REQUEST_URI', 'not like', $pattern);
        }

        if ($dateFrom !== '') {
            $base->whereRaw("aud.AUDIT_TIMESTAMP >= STR_TO_DATE(?, '%d/%m/%Y')", [$dateFrom]);
        }
        if ($dateTo !== '') {
            $base->whereRaw(
                "aud.AUDIT_TIMESTAMP <= STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s')",
                [$dateTo.' 23:59:59']
            );
        }
        if ($transType !== '') {
            $base->whereRaw('UPPER(aud.AUDIT_ACTION) = ?', [strtoupper($transType)]);
        }
        if ($menuId !== '' && ctype_digit($menuId)) {
            $base->where('aud.AUDIT_REQUEST_MENU_ID', (int) $menuId);
        }
        if ($browser !== '') {
            if (strtoupper($browser) === 'OTHERS') {
                $base->whereNotNull('aud.AUDIT_BROWSER')
                    ->whereNotIn('aud.AUDIT_BROWSER', $this->knownBrowsers());
            } else {
                $base->where('aud.AUDIT_BROWSER', strtoupper($browser));
            }
        }
        if ($userId !== '' && ctype_digit($userId)) {
            $base->where('aud.USER_ID', (int) $userId);
        }
        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $base->where(function ($b) use ($like) {
                $b->orWhereRaw('LOWER(IFNULL(aud.AUDIT_ACTION, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(aud.AUDIT_REQUEST_MENU_PATH, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(aud.AUDIT_BROWSER, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(aud.AUDIT_CLIENT_IP, "")) LIKE ?', [$like]);
            });
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->selectRaw("DATE_FORMAT(aud.AUDIT_TIMESTAMP, '%d/%m/%Y %H:%i:%S') as audit_timestamp_text")
            ->selectRaw('UPPER(aud.AUDIT_ACTION) as audit_action_text')
            ->selectRaw("UPPER(IFNULL(aud.AUDIT_BROWSER, 'OTHERS')) as audit_browser_text")
            ->addSelect([
                'aud.AUDIT_ID',
                'aud.USER_ID',
                'aud.AUDIT_CLIENT_IP',
                'aud.AUDIT_REQUEST_MENU_PATH',
                'aud.AUDIT_REQUEST_MENU_ID',
                'aud.AUDIT_USER_TYPE',
                'aud.AUDIT_SQL',
            ])
            ->orderBy($sortBy, $sortDir)
            ->orderBy('aud.AUDIT_ID', 'desc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        if ($userType !== '') {
            $upper = strtoupper($userType);
            if (in_array($upper, ['STAFF', 'STUDENT', 'VENDOR'], true)) {
                $rows = $this->scopeByUserType($rows, $upper);
            }
        }

        $data = $rows->values()->map(function ($r, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'auditId' => (int) $r->AUDIT_ID,
                'auditTimestamp' => $r->audit_timestamp_text,
                'auditAction' => $r->audit_action_text,
                'auditMenuPath' => $this->cleanMenuPath($r->AUDIT_REQUEST_MENU_PATH),
                'auditMenuId' => $r->AUDIT_REQUEST_MENU_ID !== null ? (int) $r->AUDIT_REQUEST_MENU_ID : null,
                'auditBrowser' => $r->audit_browser_text,
                'auditClientIp' => $r->AUDIT_CLIENT_IP,
                'auditUserId' => $r->USER_ID !== null ? (int) $r->USER_ID : null,
                'auditUserType' => $r->AUDIT_USER_TYPE,
                'auditUser' => $this->resolveUserLabel((int) $r->USER_ID),
                'hasSql' => filled($r->AUDIT_SQL),
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function showSql(int $auditId): JsonResponse
    {
        $row = AuditSystemTransaction::query()
            ->from('fims_audit.system_transaction')
            ->where('AUDIT_ID', $auditId)
            ->first(['AUDIT_ID', 'AUDIT_SQL']);

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Audit row not found.');
        }

        return $this->sendOk([
            'auditId' => (int) $row->AUDIT_ID,
            'sql' => (string) $row->AUDIT_SQL,
        ]);
    }

    public function options(): JsonResponse
    {
        return $this->sendOk([
            'browsers' => array_map(
                static fn (string $b) => ['id' => $b, 'label' => $b],
                $this->knownBrowsers()
            ),
            'userTypes' => [
                ['id' => 'STAFF', 'label' => 'STAFF'],
                ['id' => 'STUDENT', 'label' => 'STUDENT'],
                ['id' => 'VENDOR', 'label' => 'VENDOR'],
            ],
            'transTypes' => $this->distinctTransTypes(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function knownBrowsers(): array
    {
        return ['CHROME', 'EDGE', 'FIREFOX', 'INTERNET EXPLORER', 'MOZILA', 'NAVIGATOR', 'SAFARI', 'OTHERS'];
    }

    /**
     * @return array<int, array{id:string,label:string}>
     */
    private function distinctTransTypes(): array
    {
        try {
            $rows = AuditSystemTransaction::query()
                ->from('fims_audit.system_transaction')
                ->selectRaw('DISTINCT UPPER(AUDIT_ACTION) as action')
                ->whereNotNull('AUDIT_ACTION')
                ->orderBy('action')
                ->limit(50)
                ->pluck('action');

            return $rows->filter()->map(static fn ($a) => ['id' => $a, 'label' => $a])->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function cleanMenuPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        // Legacy stripped everything before the last </back> segment.
        $parts = explode('</back>', $path);
        $tail = trim((string) end($parts));

        return strip_tags($tail) === '' ? $tail : strip_tags($tail);
    }

    /**
     * @param  Collection<int, Model>  $rows
     * @return Collection<int, Model>
     */
    private function scopeByUserType($rows, string $userType)
    {
        $userIds = $rows->pluck('USER_ID')->filter()->unique()->values()->all();
        if (empty($userIds)) {
            return $rows;
        }

        $known = $this->lookupUserType($userIds, $userType);

        return $rows->filter(fn ($r) => isset($known[(int) $r->USER_ID]))->values();
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, string>
     */
    private function lookupUserType(array $userIds, string $userType): array
    {
        try {
            $conn = AuditSystemTransaction::query()->getConnection();
            if ($userType === 'STAFF') {
                $rows = $conn->table('PRUSER as pru')
                    ->join('staff as stf', 'stf.stf_ad_username', '=', 'pru.USERNAME')
                    ->whereIn('pru.USERID', $userIds)
                    ->pluck('pru.USERID');
            } elseif ($userType === 'STUDENT') {
                $rows = $conn->table('PRUSER as pru')
                    ->join('student as std', 'std.std_ad_username', '=', 'pru.USERNAME')
                    ->whereIn('pru.USERID', $userIds)
                    ->pluck('pru.USERID');
            } else {
                $rows = $conn->table('PRUSER as pru')
                    ->join('vend_customer_supplier as vcs', 'vcs.vcs_vendor_code', '=', 'pru.USERNAME')
                    ->whereIn('pru.USERID', $userIds)
                    ->pluck('pru.USERID');
            }

            $out = [];
            foreach ($rows as $id) {
                $out[(int) $id] = $userType;
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveUserLabel(int $userId): ?string
    {
        if ($userId <= 1) {
            return null;
        }

        try {
            $conn = AuditSystemTransaction::query()->getConnection();

            $staff = $conn->table('PRUSER as pru')
                ->join('staff as stf', 'stf.stf_ad_username', '=', 'pru.USERNAME')
                ->where('pru.USERID', $userId)
                ->select('stf.stf_staff_name as name', 'stf.stf_staff_id as code', 'pru.USERNAME as username')
                ->first();
            if ($staff) {
                return $this->formatUserLabel('Staff ID', $staff->code, $staff->username, $staff->name);
            }

            $student = $conn->table('PRUSER as pru')
                ->join('student as std', 'std.std_ad_username', '=', 'pru.USERNAME')
                ->where('pru.USERID', $userId)
                ->select('std.std_student_name as name', 'std.std_student_id as code', 'pru.USERNAME as username')
                ->first();
            if ($student) {
                return $this->formatUserLabel('Student ID', $student->code, $student->username, $student->name);
            }

            $vendor = $conn->table('PRUSER as pru')
                ->join('vend_customer_supplier as vcs', 'vcs.vcs_vendor_code', '=', 'pru.USERNAME')
                ->where('pru.USERID', $userId)
                ->select('vcs.vcs_vendor_name as name', 'vcs.vcs_vendor_code as code', 'pru.USERNAME as username')
                ->first();
            if ($vendor) {
                return $this->formatUserLabel('Vendor Code', $vendor->code, $vendor->username, $vendor->name);
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatUserLabel(string $idLabel, ?string $code, ?string $username, ?string $name): string
    {
        return sprintf(
            '%s (%s: %s, Username: %s)',
            (string) $name,
            $idLabel,
            (string) $code,
            (string) $username,
        );
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
