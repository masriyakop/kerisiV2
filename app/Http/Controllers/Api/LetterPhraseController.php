<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLetterPhraseRequest;
use App\Http\Traits\ApiResponse;
use App\Models\LookupParameterMain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Letter Phrase setup (legacy PAGEID 2911 / MENUID 3506).
 *
 * Backed by the Kerisi `lookup_parameter_main` rows where `lpm_code = 'PHRASE'`.
 * Legacy BL: `SZ_SETUPANDMAINTENANCE_LETTERPHRASE_API` exposed
 *   - dt_list     → datatable listing
 *   - phraseinfo  → single-row fetch for the edit modal
 *   - save_med    → update lpm_value_desc_bm / lpm_value_desc by lpm_value
 *
 * Delete was rendered as an action button in the legacy UI but never had
 * server-side BL, so this controller does not expose a destroy endpoint.
 */
class LetterPhraseController extends Controller
{
    use ApiResponse;

    private const LPM_CODE = 'PHRASE';

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 10));
        $q = trim((string) $request->input('q', ''));
        $sortBy = (string) $request->input('sort_by', 'lpm_value');
        $sortDir = strtolower((string) $request->input('sort_dir', 'asc'));

        $allowedSortBy = ['lpm_value', 'lpm_value_desc_bm', 'lpm_value_desc', 'lpm_code'];
        if (! in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'lpm_value';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $query = LookupParameterMain::query()->where('lpm_code', self::LPM_CODE);

        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(lpm_value, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(lpm_value_desc_bm, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(lpm_value_desc, "")) LIKE ?', [$like]);
            });
        }

        $total = $query->count();
        $rows = $query
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function (LookupParameterMain $row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'lpm_value' => (string) $row->lpm_value,
                'lpm_value_desc_bm' => $row->lpm_value_desc_bm,
                'lpm_value_desc' => $row->lpm_value_desc,
                'lpm_code' => (string) $row->lpm_code,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function show(string $lpmValue): JsonResponse
    {
        $row = LookupParameterMain::query()
            ->where('lpm_code', self::LPM_CODE)
            ->where('lpm_value', $lpmValue)
            ->first();

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Letter Phrase not found');
        }

        return $this->sendOk([
            'lpm_value' => (string) $row->lpm_value,
            'lpm_value_desc_bm' => $row->lpm_value_desc_bm,
            'lpm_value_desc' => $row->lpm_value_desc,
        ]);
    }

    public function update(UpdateLetterPhraseRequest $request, string $lpmValue): JsonResponse
    {
        $exists = LookupParameterMain::query()
            ->where('lpm_code', self::LPM_CODE)
            ->where('lpm_value', $lpmValue)
            ->exists();

        if (! $exists) {
            return $this->sendError(404, 'NOT_FOUND', 'Letter Phrase not found');
        }

        $data = $request->validated();
        $bm = trim((string) $data['lpm_value_desc_bm']);
        $en = array_key_exists('lpm_value_desc', $data) && filled($data['lpm_value_desc'])
            ? trim((string) $data['lpm_value_desc'])
            : null;

        LookupParameterMain::query()
            ->where('lpm_code', self::LPM_CODE)
            ->where('lpm_value', $lpmValue)
            ->update([
                'lpm_value_desc_bm' => $bm,
                'lpm_value_desc' => $en,
                'updatedby' => $request->user()?->name ?? 'system',
                'updateddate' => now(),
            ]);

        return $this->sendOk(['success' => true]);
    }
}
