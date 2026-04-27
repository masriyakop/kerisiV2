<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgRateEntryRequest;
use App\Http\Traits\ApiResponse;
use App\Models\CurrencyDetail;
use App\Models\CurrencyMaster;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AG Rate (PAGEID 2647 / MENUID 3199).
 *
 * Migrated from legacy BL `QLA_API_GLOBAL_UPLOADCURRENCY`. The list view is
 * grouped by `(cyd_year, cyd_month)` from `currency_details`. Manual entry
 * inserts one row per (currency, day) for the selected year/month
 * (mirrors the legacy `entry_save` branch). The `deleteList` branch removes
 * all detail rows for a given year/month combo. Currency-code search powers
 * the legacy `getCurrencyCode` autosuggest.
 */
class AgRateController extends Controller
{
    use ApiResponse;

    private const MONTH_NAMES = [
        1 => 'JANUARY', 2 => 'FEBRUARY', 3 => 'MARCH', 4 => 'APRIL',
        5 => 'MAY', 6 => 'JUNE', 7 => 'JULY', 8 => 'AUGUST',
        9 => 'SEPTEMBER', 10 => 'OCTOBER', 11 => 'NOVEMBER', 12 => 'DECEMBER',
    ];

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, (int) $request->input('limit', 5));
        $q = (string) ($request->input('q') ?? '');
        $sortBy = $request->input('sort_by', 'cyd_year');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc'));

        $allowedSort = ['cyd_year', 'cyd_month', 'cyd_file_name'];
        if (! in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'cyd_year';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $base = CurrencyDetail::query();

        if (($year = $request->input('cyd_year')) !== null && $year !== '') {
            $base->where('cyd_year', $year);
        }
        if (($month = $request->input('cyd_month')) !== null && $month !== '') {
            $base->where('cyd_month', $month);
        }

        if ($q !== '') {
            $needle = mb_strtolower(trim($q), 'UTF-8');
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
            $base->where(function ($builder) use ($like) {
                $builder
                    ->whereRaw('LOWER(IFNULL(cyd_year, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(cyd_month, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(cyd_file_name, "")) LIKE ?', [$like]);
            });
        }

        $totalQuery = (clone $base)
            ->select('cyd_year', 'cyd_month')
            ->groupBy('cyd_year', 'cyd_month');
        $total = $totalQuery->get()->count();

        $rows = $base
            ->selectRaw('cyd_year, cyd_month, GROUP_CONCAT(DISTINCT cyd_file_name ORDER BY cyd_file_name DESC SEPARATOR " & ") AS cyd_file_name')
            ->groupBy('cyd_year', 'cyd_month')
            ->orderBy($sortBy, $sortDir)
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $data = $rows->values()->map(function ($row, int $i) use ($page, $limit) {
            return [
                'index' => (($page - 1) * $limit) + $i + 1,
                'cyd_year' => $row->cyd_year,
                'cyd_month' => $row->cyd_month,
                'cyd_file_name' => $row->cyd_file_name,
            ];
        });

        return $this->sendOk($data, [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ]);
    }

    public function options(): JsonResponse
    {
        $years = CurrencyDetail::query()
            ->whereNotNull('cyd_year')
            ->distinct()
            ->orderBy('cyd_year', 'desc')
            ->pluck('cyd_year')
            ->filter()
            ->values()
            ->map(fn ($y) => ['id' => (string) $y, 'label' => (string) $y]);

        $months = collect(self::MONTH_NAMES)
            ->map(fn ($name, $num) => ['id' => $name, 'label' => sprintf('%02d - %s', $num, $name)])
            ->values();

        return $this->sendOk([
            'years' => $years,
            'months' => $months,
        ]);
    }

    public function searchCurrencies(Request $request): JsonResponse
    {
        $q = (string) ($request->input('q') ?? '');
        $rows = CurrencyMaster::query()
            ->where('cym_enabled', 1)
            ->whereNotNull('cyd_unit')
            ->when($q !== '', function ($builder) use ($q) {
                $needle = mb_strtolower(trim($q), 'UTF-8');
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle).'%';
                $builder->where(function ($inner) use ($like) {
                    $inner
                        ->whereRaw('LOWER(IFNULL(cym_currency_code, "")) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(IFNULL(cym_currency_desc, "")) LIKE ?', [$like]);
                });
            })
            ->orderBy('cym_currency_code')
            ->limit(50)
            ->get(['cym_currency_code', 'cym_currency_desc', 'cyd_unit']);

        return $this->sendOk(
            $rows->map(fn (CurrencyMaster $row) => [
                'id' => $row->cym_currency_code,
                'label' => trim(($row->cym_currency_code ?? '').' - '.($row->cym_currency_desc ?? '')),
                'code' => $row->cym_currency_code,
                'desc' => $row->cym_currency_desc,
                'unit' => $row->cyd_unit !== null ? (float) $row->cyd_unit : null,
            ])
        );
    }

    public function periodLines(Request $request): JsonResponse
    {
        $year = (int) $request->input('cyd_year');
        $month = (string) $request->input('cyd_month');
        if ($year < 1900 || $month === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'cyd_year and cyd_month are required');
        }

        $rows = CurrencyDetail::query()
            ->where('cyd_year', $year)
            ->where('cyd_month', $month)
            ->orderBy('cym_currency_code')
            ->orderBy('cyd_start_date')
            ->get([
                'cyd_id',
                'cym_currency_code',
                'cyd_start_date',
                'cyd_end_date',
                'cyd_exchange_type_code',
                'cyd_conversation_rate',
                'cyd_unit',
                'cyd_file_name',
                'cyd_status',
            ]);

        $data = $rows->map(fn (CurrencyDetail $row) => [
            'cyd_id' => (int) $row->cyd_id,
            'cym_currency_code' => $row->cym_currency_code,
            'cyd_start_date' => optional($row->cyd_start_date)->format('Y-m-d'),
            'cyd_end_date' => optional($row->cyd_end_date)->format('Y-m-d'),
            'cyd_exchange_type_code' => $row->cyd_exchange_type_code,
            'cyd_conversation_rate' => $row->cyd_conversation_rate !== null ? (float) $row->cyd_conversation_rate : null,
            'cyd_unit' => $row->cyd_unit !== null ? (float) $row->cyd_unit : null,
            'cyd_file_name' => $row->cyd_file_name,
            'cyd_status' => $row->cyd_status,
        ]);

        return $this->sendOk($data);
    }

    public function checkExist(Request $request): JsonResponse
    {
        $year = (int) $request->input('cyd_year');
        $month = (string) $request->input('cyd_month');
        $exists = CurrencyDetail::query()
            ->where('cyd_year', $year)
            ->where('cyd_month', $month)
            ->exists();

        return $this->sendOk(['exists' => $exists]);
    }

    public function store(StoreAgRateEntryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $year = (int) $data['cyd_year'];
        $monthNum = (int) $data['cyd_month'];
        $monthDesc = self::MONTH_NAMES[$monthNum] ?? null;

        if (! $monthDesc) {
            return $this->sendError(400, 'BAD_REQUEST', 'Invalid month');
        }

        $username = $request->user()?->name ?? 'system';
        $daysInMonth = (int) Carbon::create($year, $monthNum, 1)->daysInMonth;

        // Whitelist enabled currencies that have a unit configured.
        $masterUnits = CurrencyMaster::query()
            ->where('cym_enabled', 1)
            ->whereNotNull('cyd_unit')
            ->pluck('cyd_unit', 'cym_currency_code');

        DB::connection('mysql_secondary')->transaction(function () use ($year, $monthDesc, $data, $daysInMonth, $masterUnits, $username) {
            $codes = collect($data['rates'])->pluck('cym_currency_code')->unique()->values();

            CurrencyDetail::query()
                ->where('cyd_year', $year)
                ->where('cyd_month', $monthDesc)
                ->whereIn('cym_currency_code', $codes->all())
                ->delete();

            $maxId = (int) CurrencyDetail::query()->max('cyd_id');
            $monthNum = array_search($monthDesc, self::MONTH_NAMES, true);

            foreach ($data['rates'] as $rate) {
                $code = trim((string) $rate['cym_currency_code']);
                if (! $masterUnits->has($code)) {
                    continue;
                }
                $unit = $rate['cyd_unit'] ?? (float) $masterUnits->get($code);
                $rateValue = $rate['cyd_conversation_rate'];

                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $dayStr = sprintf('%04d-%02d-%02d', $year, $monthNum, $d);
                    CurrencyDetail::create([
                        'cyd_id' => ++$maxId,
                        'cym_currency_code' => $code,
                        'cyd_year' => $year,
                        'cyd_month' => $monthDesc,
                        'cyd_start_date' => $dayStr,
                        'cyd_end_date' => $dayStr,
                        'cyd_exchange_type_code' => 'AG',
                        'cyd_conversation_rate' => $rateValue,
                        'cyd_file_name' => 'Manual Entry',
                        'cyd_unit' => $unit,
                        'cyd_status' => '1',
                        'createdby' => $username,
                        'createddate' => now(),
                    ]);
                }
            }
        });

        return $this->sendCreated(['success' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $year = (int) $request->input('cyd_year');
        $month = (string) $request->input('cyd_month');
        if ($year < 1900 || $month === '') {
            return $this->sendError(400, 'BAD_REQUEST', 'cyd_year and cyd_month are required');
        }

        CurrencyDetail::query()
            ->where('cyd_year', $year)
            ->where('cyd_month', $month)
            ->delete();

        return $this->sendOk(['success' => true]);
    }
}
