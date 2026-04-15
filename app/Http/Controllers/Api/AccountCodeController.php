<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountActivityRequest;
use App\Http\Requests\StoreAccountCodeRequest;
use App\Http\Requests\UpdateAccountActivityRequest;
use App\Http\Requests\UpdateAccountCodeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\AccountMain;
use App\Models\LookupDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountCodeController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $level = $this->resolveLevel($request);
        if ($level < 0 || $level > 5) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'level must be between 0 and 5.');
        }

        if ($level === 0) {
            return $this->listActivities($request);
        }

        return $this->listAccounts($request, $level);
    }

    public function store(StoreAccountCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $level = (int) $data['acm_acct_level'];
        $code = Str::upper(trim($data['acm_acct_code']));
        $activity = filled($data['acm_acct_activity'] ?? null) ? Str::upper(trim((string) $data['acm_acct_activity'])) : null;
        $parent = filled($data['acm_acct_parent'] ?? null) ? Str::upper(trim((string) $data['acm_acct_parent'])) : null;

        if ($level === 1 && ! $activity) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'acm_acct_activity is required for level 1.');
        }
        if ($level > 1 && ! $parent) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'acm_acct_parent is required for level 2 and above.');
        }

        if (AccountMain::query()->where('acm_acct_code', $code)->exists()) {
            return $this->sendError(400, 'BAD_REQUEST', 'Account code already exists.');
        }

        if ($activity && ! LookupDetail::query()->where('lma_code_name', 'ACCOUNT_ACTIVITY')->where('lde_value', $activity)->exists()) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'Invalid acm_acct_activity value.');
        }

        if ($parent && ! AccountMain::query()->where('acm_acct_code', $parent)->exists()) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'Parent account code does not exist.');
        }

        AccountMain::create([
            'acm_acct_code' => $code,
            'acm_acct_desc' => trim($data['acm_acct_desc']),
            'acm_acct_desc_eng' => filled($data['acm_acct_desc_eng'] ?? null) ? trim((string) $data['acm_acct_desc_eng']) : null,
            'acm_acct_activity' => $activity,
            'acm_acct_status' => strtoupper($data['acm_acct_status']) === 'ACTIVE' ? '1' : '0',
            'acm_acct_group' => filled($data['acm_acct_group'] ?? null) ? trim((string) $data['acm_acct_group']) : null,
            'acm_acct_level' => $level,
            'acm_acct_parent' => $parent,
            'createddate' => now(),
            'updateddate' => now(),
        ]);

        return $this->sendCreated(['success' => true]);
    }

    public function update(UpdateAccountCodeRequest $request, string $code): JsonResponse
    {
        $originalCode = Str::upper(trim($code));
        $row = AccountMain::query()->where('acm_acct_code', $originalCode)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Account code not found.');
        }

        $data = $request->validated();
        $nextCode = filled($data['acm_acct_code'] ?? null) ? Str::upper(trim((string) $data['acm_acct_code'])) : $originalCode;
        $activity = filled($data['acm_acct_activity'] ?? null) ? Str::upper(trim((string) $data['acm_acct_activity'])) : null;
        $parent = filled($data['acm_acct_parent'] ?? null) ? Str::upper(trim((string) $data['acm_acct_parent'])) : null;

        if ($nextCode !== $originalCode && AccountMain::query()->where('acm_acct_code', $nextCode)->exists()) {
            return $this->sendError(400, 'BAD_REQUEST', 'Account code already exists.');
        }

        if ($activity && ! LookupDetail::query()->where('lma_code_name', 'ACCOUNT_ACTIVITY')->where('lde_value', $activity)->exists()) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'Invalid acm_acct_activity value.');
        }

        if ($parent && ! AccountMain::query()->where('acm_acct_code', $parent)->exists()) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'Parent account code does not exist.');
        }

        DB::connection('mysql_secondary')->transaction(function () use ($row, $originalCode, $nextCode, $data, $activity, $parent) {
            $row->update([
                'acm_acct_code' => $nextCode,
                'acm_acct_desc' => trim($data['acm_acct_desc']),
                'acm_acct_desc_eng' => filled($data['acm_acct_desc_eng'] ?? null) ? trim((string) $data['acm_acct_desc_eng']) : null,
                'acm_acct_activity' => $activity,
                'acm_acct_status' => strtoupper($data['acm_acct_status']) === 'ACTIVE' ? '1' : '0',
                'acm_acct_group' => filled($data['acm_acct_group'] ?? null) ? trim((string) $data['acm_acct_group']) : null,
                'acm_acct_parent' => $parent,
                'updateddate' => now(),
            ]);

            if ($nextCode !== $originalCode) {
                AccountMain::query()->where('acm_acct_parent', $originalCode)->update(['acm_acct_parent' => $nextCode]);
            }
        });

        return $this->sendOk(['success' => true]);
    }

    public function destroy(string $code): JsonResponse
    {
        $accountCode = Str::upper(trim($code));
        if (AccountMain::query()->where('acm_acct_parent', $accountCode)->exists()) {
            return $this->sendError(409, 'BAD_REQUEST', 'Unable to delete account code because child rows exist.');
        }

        AccountMain::query()->where('acm_acct_code', $accountCode)->delete();

        return $this->sendOk(['success' => true]);
    }

    public function listActivity(Request $request): JsonResponse
    {
        return $this->listActivities($request);
    }

    public function storeActivity(StoreAccountActivityRequest $request): JsonResponse
    {
        $data = $request->validated();
        $value = Str::upper(trim($data['lde_value']));

        $exists = LookupDetail::query()
            ->where('lma_code_name', 'ACCOUNT_ACTIVITY')
            ->where('lde_value', $value)
            ->exists();

        if ($exists) {
            return $this->sendError(400, 'BAD_REQUEST', 'Account activity code already exists.');
        }

        $row = DB::connection('mysql_secondary')->transaction(function () use ($data, $value) {
            $maxId = (int) LookupDetail::query()->where('lma_code_name', 'ACCOUNT_ACTIVITY')->lockForUpdate()->max('lde_id');
            $maxSorting = (int) LookupDetail::query()->where('lma_code_name', 'ACCOUNT_ACTIVITY')->lockForUpdate()->max('lde_sorting');

            return LookupDetail::create([
                'lde_id' => $maxId + 1,
                'lma_code_name' => 'ACCOUNT_ACTIVITY',
                'lde_value' => $value,
                'lde_description' => trim($data['lde_description']),
                'lde_description2' => filled($data['lde_description2'] ?? null) ? trim((string) $data['lde_description2']) : null,
                'lde_sorting' => $maxSorting + 1,
                'lde_status' => strtoupper($data['lde_status']) === 'ACTIVE' ? '1' : '0',
                'createddate' => now(),
                'updateddate' => now(),
            ]);
        });

        return $this->sendCreated(['lde_id' => (int) $row->lde_id]);
    }

    public function updateActivity(UpdateAccountActivityRequest $request, int $id): JsonResponse
    {
        $row = LookupDetail::query()
            ->where('lma_code_name', 'ACCOUNT_ACTIVITY')
            ->where('lde_id', $id)
            ->first();

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Account activity not found.');
        }

        $data = $request->validated();
        $value = Str::upper(trim($data['lde_value']));

        $duplicate = LookupDetail::query()
            ->where('lma_code_name', 'ACCOUNT_ACTIVITY')
            ->where('lde_value', $value)
            ->where('lde_id', '!=', $id)
            ->exists();
        if ($duplicate) {
            return $this->sendError(400, 'BAD_REQUEST', 'Account activity code already exists.');
        }

        $row->update([
            'lde_value' => $value,
            'lde_description' => trim($data['lde_description']),
            'lde_description2' => filled($data['lde_description2'] ?? null) ? trim((string) $data['lde_description2']) : null,
            'lde_status' => strtoupper($data['lde_status']) === 'ACTIVE' ? '1' : '0',
            'updateddate' => now(),
        ]);

        return $this->sendOk(['success' => true]);
    }

    public function deleteActivity(int $id): JsonResponse
    {
        $row = LookupDetail::query()
            ->where('lma_code_name', 'ACCOUNT_ACTIVITY')
            ->where('lde_id', $id)
            ->first();

        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Account activity not found.');
        }

        if (AccountMain::query()->where('acm_acct_level', 1)->where('acm_acct_activity', $row->lde_value)->exists()) {
            return $this->sendError(409, 'BAD_REQUEST', 'Unable to delete activity because level 1 account records exist.');
        }

        $row->delete();

        return $this->sendOk(['success' => true]);
    }

    private function resolveLevel(Request $request): int
    {
        if ($request->has('level')) {
            return (int) $request->query('level');
        }
        if ($request->query('dt_accountactvty') === '1') {
            return 0;
        }
        if ($request->query('level_1') === '1') {
            return 1;
        }
        if ($request->query('level2') === '1') {
            return 2;
        }
        if ($request->query('level3') === '1') {
            return 3;
        }
        if ($request->query('level4') === '1') {
            return 4;
        }
        if ($request->query('level5') === '1') {
            return 5;
        }

        return 0;
    }

    private function listActivities(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = strtoupper(trim((string) ($request->query('lde_status', $request->query('smart_filter_lde_status', '')))));

        $query = LookupDetail::query()->where('lma_code_name', 'ACCOUNT_ACTIVITY');
        if ($search !== '') {
            $needle = mb_strtolower($search, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(lde_value, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(lde_description, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(lde_description2, "")) LIKE ?', [$like]);
            });
        }

        if ($status === 'ACTIVE') {
            $query->where('lde_status', '1');
        } elseif ($status === 'INACTIVE') {
            $query->where('lde_status', '0');
        }

        $rows = $query->orderByRaw('CASE WHEN lde_sorting IS NULL THEN 1 ELSE 0 END')->orderBy('lde_sorting')->orderBy('lde_id')
            ->get()
            ->values()
            ->map(fn (LookupDetail $row, int $index) => [
                'no' => $index + 1,
                'lde_value' => $row->lde_value,
                'lde_description' => $row->lde_description,
                'lde_description2' => $row->lde_description2,
                'lde_status' => ((string) $row->lde_status === '1') ? 'ACTIVE' : 'INACTIVE',
                'lde_id' => (int) $row->lde_id,
            ]);

        return $this->sendOk($rows);
    }

    private function listAccounts(Request $request, int $level): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = strtoupper(trim((string) ($request->query('acm_acct_status', $request->query('smart_filter_acm_acct_status', '')))));
        $activity = filled($request->query('activity')) ? Str::upper(trim((string) $request->query('activity'))) : null;
        $parent = filled($request->query('parent')) ? Str::upper(trim((string) $request->query('parent'))) : null;

        if ($level === 1 && ! $activity && ! $parent) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'activity is required for level 1.');
        }
        if ($level > 1 && ! $parent) {
            return $this->sendError(422, 'VALIDATION_ERROR', 'parent is required for level 2 and above.');
        }

        $query = AccountMain::query()->where('acm_acct_level', $level);

        if ($level === 1) {
            $query->where('acm_acct_activity', $activity ?? $parent);
        } else {
            $query->where('acm_acct_parent', $parent);
        }

        if ($search !== '') {
            $needle = mb_strtolower($search, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(acm_acct_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(acm_acct_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(acm_acct_desc_eng, "")) LIKE ?', [$like]);
            });
        }

        if ($status === 'ACTIVE') {
            $query->where('acm_acct_status', '1');
        } elseif ($status === 'INACTIVE') {
            $query->where('acm_acct_status', '0');
        }

        $rows = $query->orderBy('acm_acct_code')
            ->get()
            ->values()
            ->map(fn (AccountMain $row, int $index) => [
                'no' => $index + 1,
                'acm_acct_code' => $row->acm_acct_code,
                'acm_acct_desc' => $row->acm_acct_desc,
                'acm_acct_desc_eng' => $row->acm_acct_desc_eng,
                'acm_acct_activity' => $row->acm_acct_activity,
                'acm_acct_status' => ((string) $row->acm_acct_status === '1') ? 'ACTIVE' : 'INACTIVE',
                'datecreate' => $row->createddate ? date('Y-m-d', strtotime((string) $row->createddate)) : null,
                'acm_acct_group' => $row->acm_acct_group,
                'acm_acct_level' => (int) $row->acm_acct_level,
                'acm_acct_parent' => $row->acm_acct_parent,
            ]);

        return $this->sendOk($rows);
    }
}
