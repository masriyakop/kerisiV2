<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\AuthorizedReceipting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Account Receivable > Authorized Receipting" list (PAGEID 1613 / MENUID 1952).
 *
 * Source: FIMS BL `V2_AUTHORIZED_RECEIPTING_API`. The legacy BL scopes rows
 * to the logged-in staff OR to members of the `UUM_UNIT_TERIMAAN` FIMS user
 * group. That group-mapping layer does not exist in the new system (RBAC is
 * permission-based), so we expose this as a global admin list, mirroring the
 * approach used for Cashbook PTJ (MENUID 1049). An optional `staff_id` query
 * parameter still lets the caller emulate the per-staff view.
 *
 * Smart filter (kitchen-sink modal): staff_id, ptj_code, event_code,
 * position_code, status. Delete removes the row from `authorized_receipting`
 * (the legacy `dt_listingDelete` action).
 */
class AuthorizedReceiptingController extends Controller
{
    use ApiResponse;

    /** Statuses the workflow can reach (legacy values). */
    private const STATUSES = ['DRAFT', 'ENTRY', 'ENDORSED', 'APPROVE', 'RETURN', 'REJECT'];

    public function options(): JsonResponse
    {
        $statuses = array_map(fn ($s) => ['id' => $s, 'label' => $s], self::STATUSES);

        $ptj = DB::connection('mysql_secondary')
            ->table('authorized_receipting')
            ->whereNotNull('oun_code_ptj')
            ->where('oun_code_ptj', '!=', '')
            ->distinct()
            ->orderBy('oun_code_ptj')
            ->pluck('oun_code_ptj')
            ->values()
            ->map(fn ($c) => ['id' => (string) $c, 'label' => (string) $c])
            ->all();

        $events = DB::connection('mysql_secondary')
            ->table('authorized_receipting')
            ->whereNotNull('are_event_code')
            ->where('are_event_code', '!=', '')
            ->distinct()
            ->orderBy('are_event_code')
            ->pluck('are_event_code')
            ->values()
            ->map(fn ($c) => ['id' => (string) $c, 'label' => (string) $c])
            ->all();

        $positions = DB::connection('mysql_secondary')
            ->table('authorized_receipting')
            ->whereNotNull('are_position_code')
            ->where('are_position_code', '!=', '')
            ->distinct()
            ->orderBy('are_position_code')
            ->pluck('are_position_code')
            ->values()
            ->map(fn ($c) => ['id' => (string) $c, 'label' => (string) $c])
            ->all();

        return $this->sendOk([
            'status' => $statuses,
            'ptj' => $ptj,
            'event' => $events,
            'position' => $positions,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'createddate');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortable = [
            'are_application_no', 'stf_staff_id', 'oun_code_ptj',
            'are_event_code', 'are_position_code', 'are_status', 'createddate',
        ];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'createddate';
        }

        $query = AuthorizedReceipting::query();

        // Smart filter fields (all optional).
        if (($staffId = trim((string) $request->input('staff_id', ''))) !== '') {
            $query->where('stf_staff_id', $staffId);
        }
        if (($ptj = trim((string) $request->input('ptj', ''))) !== '') {
            $query->where('oun_code_ptj', $ptj);
        }
        if (($event = trim((string) $request->input('event', ''))) !== '') {
            $query->where('are_event_code', $event);
        }
        if (($position = trim((string) $request->input('position', ''))) !== '') {
            $query->where('are_position_code', $position);
        }
        if (($status = trim((string) $request->input('status', ''))) !== '') {
            $query->where('are_status', strtoupper($status));
        }

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->where(function (Builder $b) use ($like) {
                $b->orWhereRaw("LOWER(IFNULL(are_application_no, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(stf_staff_id, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(oun_code_ptj, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(are_event_code, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(are_position_code, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(are_status, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(are_extended_field->>'$.stf_staff_id_desc', '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(IFNULL(are_extended_field->>'$.oun_code_ptj_desc', '')) LIKE ?", [$like]);
            });
        }

        $total = (clone $query)->count();

        $rows = (clone $query)
            ->select([
                'are_authorized_receipting_id',
                'are_application_no',
                'stf_staff_id',
                'oun_code_ptj',
                'are_event_code',
                'are_position_code',
                'are_reason',
                'are_status',
                'createddate',
                DB::raw("are_extended_field->>'\$.stf_staff_id_desc' as staff_name"),
                DB::raw("are_extended_field->>'\$.oun_code_ptj_desc' as ptj_desc"),
                DB::raw("are_extended_field->>'\$.are_event_code_desc' as event_desc"),
                DB::raw("are_extended_field->>'\$.are_position_code_desc' as position_desc"),
            ])
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(fn ($r, int $i) => [
            'index' => (($page - 1) * $limit) + $i + 1,
            'id' => (string) $r->are_authorized_receipting_id,
            'applicationNo' => $r->are_application_no,
            'staffId' => $r->stf_staff_id,
            'staffName' => $r->staff_name,
            'ptjCode' => $r->oun_code_ptj,
            'ptjDescription' => $r->ptj_desc,
            'eventCode' => $r->are_event_code,
            'eventDescription' => $r->event_desc,
            'positionCode' => $r->are_position_code,
            'positionDescription' => $r->position_desc,
            'reason' => $r->are_reason,
            'status' => $r->are_status,
            'requestedAt' => $r->createddate,
        ]);

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $row = AuthorizedReceipting::query()
            ->where('are_authorized_receipting_id', $id)
            ->first(['are_authorized_receipting_id']);

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Authorized receipting entry not found');
        }

        AuthorizedReceipting::query()
            ->where('are_authorized_receipting_id', $row->are_authorized_receipting_id)
            ->delete();

        return $this->sendOk(['success' => true]);
    }

    private function likeEscape(string $needle): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
    }
}
