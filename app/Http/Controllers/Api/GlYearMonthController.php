<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGlYearMonthRequest;
use App\Http\Requests\UpdateGlYearMonthRequest;
use App\Http\Traits\ApiResponse;
use App\Models\GlYearMonth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * General Ledger > List of Year and Month (PAGEID 2721 / MENUID 3287).
 *
 * Source: FIMS BL `MZ_BL_GL_LIST_YEAR_MONTH` (endpoints `dtListing`,
 * `save`, `viewDetails`, `download`). Legacy schema
 * `DB_SECOND_DATABASE.gl_year_month`.
 *
 * Key behaviours preserved from legacy:
 *   - Concatenated search over year/month/status/remark.
 *   - Exact-match smart filters on year, month and status.
 *   - `gym_id` is not auto-increment; computed as MAX(gym_id)+1 (matches
 *     the legacy `getSeqNo('gl_year_month')` helper).
 *   - `(gym_year, gym_month)` is UNIQUE in MySQL — we return 400/CONFLICT
 *     with a friendly message before the DB raises a constraint error.
 *
 * Legacy BL does NOT expose a delete endpoint; this controller does not
 * add one either.
 */
class GlYearMonthController extends Controller
{
    use ApiResponse;

    private const SORTABLE = [
        'gym_id',
        'gym_year',
        'gym_month',
        'gym_status',
        'gym_remark',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'gym_year');
        if (! in_array($sortBy, self::SORTABLE, true)) {
            $sortBy = 'gym_year';
        }
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $year = trim((string) $request->input('gym_year_filter', ''));
        $month = trim((string) $request->input('gym_month_filter', ''));
        $status = trim((string) $request->input('gym_status_filter', ''));

        $query = GlYearMonth::query();

        if ($q !== '') {
            $like = $this->likeEscape(mb_strtolower($q, 'UTF-8'));
            $query->whereRaw(
                "LOWER(CONCAT_WS('__',
                    IFNULL(gym_year, ''),
                    IFNULL(gym_month, ''),
                    IFNULL(gym_status, ''),
                    IFNULL(gym_remark, '')
                )) LIKE ?",
                [$like]
            );
        }

        if ($year !== '') {
            $query->where('gym_year', $year);
        }
        if ($month !== '') {
            $query->where('gym_month', $month);
        }
        if ($status !== '') {
            $query->where('gym_status', $status);
        }

        $total = (clone $query)->count();

        $rows = (clone $query)
            ->orderBy($sortBy, $sortDir)
            ->orderBy('gym_month', 'asc')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (GlYearMonth $r, int $i) use ($page, $limit): array {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'gymId' => (int) $r->gym_id,
                'year' => $r->gym_year,
                'month' => $r->gym_month,
                'status' => $r->gym_status,
                'remark' => $r->gym_remark,
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
        $row = GlYearMonth::query()->find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Year/Month record not found');
        }

        return $this->sendOk([
            'gymId' => (int) $row->gym_id,
            'year' => $row->gym_year,
            'month' => $row->gym_month,
            'status' => $row->gym_status,
            'remark' => $row->gym_remark,
        ]);
    }

    public function store(StoreGlYearMonthRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($this->duplicateExists($data['gym_year'], $data['gym_month'], null)) {
            return $this->sendError(
                400,
                'BAD_REQUEST',
                'A record for this Year and Month already exists.',
            );
        }

        $username = $request->user()?->name ?? 'system';

        // Legacy BL: getSeqNo('gl_year_month') — equivalent to MAX(gym_id)+1.
        // gym_id is NOT AUTO_INCREMENT in the source schema.
        $nextId = (int) (GlYearMonth::query()->max('gym_id') ?? 0) + 1;

        $row = null;
        DB::connection('mysql_secondary')->transaction(function () use (
            &$row, $nextId, $data, $username
        ) {
            $row = GlYearMonth::create([
                'gym_id' => $nextId,
                'gym_year' => trim($data['gym_year']),
                'gym_month' => trim($data['gym_month']),
                'gym_status' => trim($data['gym_status']),
                'gym_remark' => isset($data['gym_remark']) ? trim((string) $data['gym_remark']) : null,
                'createdby' => $username,
                'createddate' => now(),
            ]);
        });

        return $this->sendCreated([
            'gymId' => (int) $row->gym_id,
            'year' => $row->gym_year,
            'month' => $row->gym_month,
            'status' => $row->gym_status,
            'remark' => $row->gym_remark,
        ]);
    }

    public function update(UpdateGlYearMonthRequest $request, int $id): JsonResponse
    {
        $row = GlYearMonth::query()->find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Year/Month record not found');
        }

        $data = $request->validated();

        if ($this->duplicateExists($data['gym_year'], $data['gym_month'], $id)) {
            return $this->sendError(
                400,
                'BAD_REQUEST',
                'Another record for this Year and Month already exists.',
            );
        }

        $row->update([
            'gym_year' => trim($data['gym_year']),
            'gym_month' => trim($data['gym_month']),
            'gym_status' => trim($data['gym_status']),
            'gym_remark' => isset($data['gym_remark']) ? trim((string) $data['gym_remark']) : null,
            'updatedby' => $request->user()?->name ?? 'system',
            'updateddate' => now(),
        ]);

        return $this->sendOk([
            'gymId' => (int) $row->gym_id,
            'year' => $row->gym_year,
            'month' => $row->gym_month,
            'status' => $row->gym_status,
            'remark' => $row->gym_remark,
        ]);
    }

    /**
     * Lookup options for the smart-filter and popup-modal dropdowns.
     * - `months` mirrors the legacy query
     *     SELECT lde_value, CONCAT_WS(' - ', lde_value, lde_description) ...
     *     FROM fims_usr.lookup_details WHERE lma_code_name = 'MONTH'
     *   The module sits on `mysql_secondary` (DB_SECOND_DATABASE = fims_usr
     *   in legacy parlance) so the table is reached un-prefixed.
     * - `statuses` is hard-coded (OPEN/CLOSE) in the legacy BL.
     */
    public function options(): JsonResponse
    {
        $months = DB::connection('mysql_secondary')
            ->table('lookup_details')
            ->where('lma_code_name', 'MONTH')
            ->orderBy('lde_value')
            ->get(['lde_value', 'lde_description'])
            ->map(fn ($r) => [
                'value' => (string) $r->lde_value,
                'label' => trim((string) $r->lde_value).' - '.trim((string) $r->lde_description),
            ])
            ->values()
            ->all();

        return $this->sendOk([
            'months' => $months,
            'statuses' => [
                ['value' => 'OPEN', 'label' => 'OPEN'],
                ['value' => 'CLOSE', 'label' => 'CLOSE'],
            ],
        ]);
    }

    private function duplicateExists(string $year, string $month, ?int $excludeId): bool
    {
        return GlYearMonth::query()
            ->where('gym_year', $year)
            ->where('gym_month', $month)
            ->when($excludeId !== null, fn (Builder $q) => $q->where('gym_id', '!=', $excludeId))
            ->exists();
    }

    private function likeEscape(string $needle): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
    }
}
