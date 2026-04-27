<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCurrencyMasterRequest;
use App\Http\Requests\UpdateCurrencyMasterRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Country;
use App\Models\CurrencyDetail;
use App\Models\CurrencyMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * List of Currency (PAGEID 2636 / MENUID 3198).
 *
 * Migrated from legacy BL `QLA_API_GLOBAL_LISTOFCURRENCY`. Datatable + popup
 * modal full-CRUD on `currency_master`. The legacy `cym_enabled` flag is
 * stored as `1`/`0` (or `Y`/`N`) and surfaced as `Active`/`Inactive` to the
 * frontend.
 *
 * The `saveModal` branch enforces uniqueness on `cny_country_code` (a country
 * may only have one currency entry); the `deleteModal` branch refuses to
 * remove a currency that already has linked `currency_details` rows.
 */
class ListOfCurrencyController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 5));
        $q = (string) ($request->input('q') ?? '');
        $sortBy = $request->input('sort_by', 'cym_currency_id');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc'));

        $allowedSort = [
            'cym_currency_id', 'cym_currency_code', 'cym_currency_desc',
            'cyd_unit', 'cny_country_code', 'cny_country_desc', 'cym_enabled',
        ];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'cym_currency_id';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query = CurrencyMaster::query()
            ->from('currency_master as cym')
            ->leftJoin('country as cny', 'cny.cny_country_code', '=', 'cym.cny_country_code');

        if ($q !== '') {
            $needle = mb_strtolower(trim($q), 'UTF-8');
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $query->where(function ($builder) use ($like) {
                $builder
                    ->whereRaw('LOWER(IFNULL(cym.cym_currency_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(cym.cym_currency_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(cym.cyd_unit, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(cym.cny_country_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(cny.cny_country_desc, "")) LIKE ?', [$like]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy("cym.{$sortBy}", $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get([
                'cym.cym_currency_id',
                'cym.cym_currency_code',
                'cym.cym_currency_desc',
                'cym.cyd_unit',
                'cym.cny_country_code',
                'cny.cny_country_desc',
                'cym.cym_enabled',
            ]);

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'cym_currency_id' => (int) $row->cym_currency_id,
                'cym_currency_code' => $row->cym_currency_code,
                'cym_currency_desc' => $row->cym_currency_desc,
                'cyd_unit' => $row->cyd_unit !== null ? (float) $row->cyd_unit : null,
                'cny_country_code' => $row->cny_country_code,
                'cny_country_desc' => $row->cny_country_desc,
                'cym_enabled' => $this->mapEnabledLabel($row->cym_enabled),
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
        $row = CurrencyMaster::query()
            ->from('currency_master as cym')
            ->leftJoin('country as cny', 'cny.cny_country_code', '=', 'cym.cny_country_code')
            ->where('cym.cym_currency_id', $id)
            ->first([
                'cym.cym_currency_id',
                'cym.cym_currency_code',
                'cym.cym_currency_desc',
                'cym.cyd_unit',
                'cym.cny_country_code',
                'cny.cny_country_desc',
                'cym.cym_enabled',
            ]);

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Currency not found');
        }

        return $this->sendOk([
            'cym_currency_id' => (int) $row->cym_currency_id,
            'cym_currency_code' => $row->cym_currency_code,
            'cym_currency_desc' => $row->cym_currency_desc,
            'cyd_unit' => $row->cyd_unit !== null ? (float) $row->cyd_unit : null,
            'cny_country_code' => $row->cny_country_code,
            'cny_country_desc' => $row->cny_country_desc,
            'cym_enabled' => $this->mapEnabledLabel($row->cym_enabled),
        ]);
    }

    public function searchCountries(Request $request): JsonResponse
    {
        $q = (string) ($request->input('q') ?? '');
        $rows = Country::query()
            ->when($q !== '', function ($builder) use ($q) {
                $needle = mb_strtolower(trim($q), 'UTF-8');
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
                $builder->where(function ($inner) use ($like) {
                    $inner
                        ->whereRaw('LOWER(IFNULL(cny_country_code, "")) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(IFNULL(cny_country_desc, "")) LIKE ?', [$like]);
                });
            })
            ->orderBy('cny_country_code')
            ->limit(50)
            ->get(['cny_country_code', 'cny_country_desc']);

        return $this->sendOk(
            $rows->map(fn (Country $row) => [
                'id' => $row->cny_country_code,
                'label' => trim(($row->cny_country_code ?? '').' - '.($row->cny_country_desc ?? '')),
                'code' => $row->cny_country_code,
                'desc' => $row->cny_country_desc,
            ])
        );
    }

    public function store(StoreCurrencyMasterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $countryCode = trim((string) $data['cny_country_code']);

        if (CurrencyMaster::query()->where('cny_country_code', $countryCode)->exists()) {
            $countryDesc = Country::query()->where('cny_country_code', $countryCode)->value('cny_country_desc');

            return $this->sendError(409, 'CONFLICT',
                "Information for {$countryCode}".($countryDesc ? " - {$countryDesc}" : '').' already exist.');
        }

        $maxId = ((int) CurrencyMaster::query()->max('cym_currency_id')) + 1;
        $username = $request->user()?->name ?? 'system';

        $row = CurrencyMaster::create([
            'cym_currency_id' => $maxId,
            'cym_currency_code' => trim((string) $data['cym_currency_code']),
            'cym_currency_desc' => $data['cym_currency_desc'],
            'cyd_unit' => $data['cyd_unit'],
            'cny_country_code' => $countryCode,
            'cym_enabled' => $this->mapEnabledFlag($data['cym_enabled'] ?? 'Active'),
            'createddate' => now(),
            'createdby' => $username,
        ]);

        return $this->sendCreated([
            'cym_currency_id' => (int) $row->cym_currency_id,
            'cym_currency_code' => $row->cym_currency_code,
        ]);
    }

    public function update(UpdateCurrencyMasterRequest $request, int $id): JsonResponse
    {
        $row = CurrencyMaster::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Currency not found');
        }

        $data = $request->validated();
        $username = $request->user()?->name ?? 'system';

        $row->update([
            'cym_enabled' => $this->mapEnabledFlag($data['cym_enabled']),
            'cyd_unit' => $data['cyd_unit'],
            'updateddate' => now(),
            'updatedby' => $username,
        ]);

        return $this->sendOk(['success' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        $row = CurrencyMaster::find($id);
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Currency not found');
        }

        $hasDetails = CurrencyDetail::query()
            ->where('cym_currency_code', $row->cym_currency_code)
            ->exists();

        if ($hasDetails) {
            return $this->sendError(409, 'CONFLICT',
                "Cannot delete currency code {$row->cym_currency_code} because currency details already exist.");
        }

        $row->delete();

        return $this->sendOk(['success' => true]);
    }

    private function mapEnabledLabel(?string $flag): string
    {
        return in_array((string) $flag, ['1', 'Y', 'Active'], true) ? 'Active' : 'Inactive';
    }

    private function mapEnabledFlag(string $label): string
    {
        return in_array($label, ['Active', '1', 'Y'], true) ? '1' : '0';
    }
}
