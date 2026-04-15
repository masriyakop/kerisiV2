<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityGroupRequest;
use App\Http\Requests\StoreActivitySubgroupRequest;
use App\Http\Requests\StoreActivitySubsiriRequest;
use App\Http\Requests\StoreActivityTypeRequest;
use App\Http\Requests\UpdateActivityGroupRequest;
use App\Http\Requests\UpdateActivitySubgroupRequest;
use App\Http\Requests\UpdateActivitySubsiriRequest;
use App\Http\Requests\UpdateActivityTypeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\ActivityGroup;
use App\Models\ActivitySubgroup;
use App\Models\ActivitySubsiri;
use App\Models\ActivityType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivityCodeController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $level = (int) $request->input('level', 0);
        $search = trim((string) $request->input('search', ''));

        return match ($level) {
            1 => $this->listSubgroups($request, $search),
            2 => $this->listSubsiri($request, $search),
            3 => $this->listActivityTypes($request, $search),
            default => $this->listGroups($search),
        };
    }

    public function storeGroup(StoreActivityGroupRequest $request): JsonResponse
    {
        $data = $request->validated();
        $code = Str::upper(trim($data['activity_group_code']));

        $exists = ActivityGroup::query()->where('activity_group_code', $code)->exists();
        if ($exists) {
            return $this->sendError(400, 'BAD_REQUEST', 'Activity Group code already exists.');
        }

        ActivityGroup::create([
            'activity_group_code' => $code,
            'activity_group_desc' => trim($data['activity_group_desc']),
            'activity_group_flag_kodso' => '0',
            'createddate' => now(),
        ]);

        return $this->sendCreated(['success' => true]);
    }

    public function updateGroup(UpdateActivityGroupRequest $request, string $code): JsonResponse
    {
        $group = ActivityGroup::query()->where('activity_group_code', Str::upper(trim($code)))->first();
        if (! $group) {
            return $this->sendError(404, 'NOT_FOUND', 'Activity Group not found.');
        }

        $group->update([
            'activity_group_desc' => trim($request->validated()['activity_group_desc']),
            'updateddate' => now(),
        ]);

        return $this->sendOk(['success' => true]);
    }

    public function deleteGroup(string $code): JsonResponse
    {
        $groupCode = Str::upper(trim($code));
        $hasChildren = ActivitySubgroup::query()->where('activity_group_code', $groupCode)->exists();
        if ($hasChildren) {
            return $this->sendError(409, 'BAD_REQUEST', 'Unable to delete Activity Group because subgroups exist.');
        }

        ActivityGroup::query()->where('activity_group_code', $groupCode)->delete();

        return $this->sendOk(['success' => true]);
    }

    public function storeSubgroup(StoreActivitySubgroupRequest $request): JsonResponse
    {
        $data = $request->validated();
        $groupCode = Str::upper(trim($data['activity_group_code']));
        $subgroupCode = Str::upper(trim($data['activity_subgroup_code']));

        $exists = ActivitySubgroup::query()
            ->where('activity_group_code', $groupCode)
            ->where('activity_subgroup_code', $subgroupCode)
            ->exists();
        if ($exists) {
            return $this->sendError(400, 'BAD_REQUEST', 'Activity Subgroup code already exists for selected group.');
        }

        ActivitySubgroup::create([
            'activity_group_code' => $groupCode,
            'activity_subgroup_code' => $subgroupCode,
            'activity_subgroup_desc' => trim($data['activity_subgroup_desc']),
            'createddate' => now(),
        ]);

        return $this->sendCreated(['success' => true]);
    }

    public function updateSubgroup(UpdateActivitySubgroupRequest $request, string $code): JsonResponse
    {
        $data = $request->validated();
        $groupCode = Str::upper(trim($data['activity_group_code']));
        $subgroupCode = Str::upper(trim($code));

        $row = ActivitySubgroup::query()
            ->where('activity_group_code', $groupCode)
            ->where('activity_subgroup_code', $subgroupCode)
            ->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Activity Subgroup not found.');
        }

        $row->update([
            'activity_subgroup_desc' => trim($data['activity_subgroup_desc']),
            'updateddate' => now(),
        ]);

        return $this->sendOk(['success' => true]);
    }

    public function deleteSubgroup(Request $request, string $code): JsonResponse
    {
        $groupCode = Str::upper(trim((string) $request->query('activity_group_code')));
        if ($groupCode === '') {
            return $this->sendError(422, 'VALIDATION_ERROR', 'activity_group_code is required.');
        }

        $subgroupCode = Str::upper(trim($code));
        $hasChildren = ActivitySubsiri::query()
            ->where('activity_group', $groupCode)
            ->where('activity_subgroup_code', $subgroupCode)
            ->exists();
        if ($hasChildren) {
            return $this->sendError(409, 'BAD_REQUEST', 'Unable to delete Activity Subgroup because subsiri records exist.');
        }

        ActivitySubgroup::query()
            ->where('activity_group_code', $groupCode)
            ->where('activity_subgroup_code', $subgroupCode)
            ->delete();

        return $this->sendOk(['success' => true]);
    }

    public function storeSubsiri(StoreActivitySubsiriRequest $request): JsonResponse
    {
        $data = $request->validated();
        $group = Str::upper(trim($data['activity_group']));
        $subgroup = Str::upper(trim($data['activity_subgroup_code']));
        $code = Str::upper(trim($data['activity_subsiri_code']));

        $exists = ActivitySubsiri::query()
            ->where('activity_group', $group)
            ->where('activity_subgroup_code', $subgroup)
            ->where('activity_subsiri_code', $code)
            ->exists();
        if ($exists) {
            return $this->sendError(400, 'BAD_REQUEST', 'Activity Subsiri code already exists for selected subgroup.');
        }

        ActivitySubsiri::create([
            'activity_group' => $group,
            'activity_subgroup_code' => $subgroup,
            'activity_subsiri_code' => $code,
            'activity_subsiri_desc' => trim($data['activity_subsiri_desc']),
            'activity_subsiri_desc_eng' => filled($data['activity_subsiri_desc_eng'] ?? null) ? trim((string) $data['activity_subsiri_desc_eng']) : null,
            'createddate' => now(),
        ]);

        return $this->sendCreated(['success' => true]);
    }

    public function updateSubsiri(UpdateActivitySubsiriRequest $request, string $code): JsonResponse
    {
        $data = $request->validated();
        $group = Str::upper(trim($data['activity_group']));
        $subgroup = Str::upper(trim($data['activity_subgroup_code']));
        $subsiriCode = Str::upper(trim($code));

        $row = ActivitySubsiri::query()
            ->where('activity_group', $group)
            ->where('activity_subgroup_code', $subgroup)
            ->where('activity_subsiri_code', $subsiriCode)
            ->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Activity Subsiri not found.');
        }

        $row->update([
            'activity_subsiri_desc' => trim($data['activity_subsiri_desc']),
            'activity_subsiri_desc_eng' => filled($data['activity_subsiri_desc_eng'] ?? null) ? trim((string) $data['activity_subsiri_desc_eng']) : null,
            'updateddate' => now(),
        ]);

        return $this->sendOk(['success' => true]);
    }

    public function deleteSubsiri(Request $request, string $code): JsonResponse
    {
        $group = Str::upper(trim((string) $request->query('activity_group')));
        $subgroup = Str::upper(trim((string) $request->query('activity_subgroup_code')));
        if ($group === '' || $subgroup === '') {
            return $this->sendError(422, 'VALIDATION_ERROR', 'activity_group and activity_subgroup_code are required.');
        }

        $subsiriCode = Str::upper(trim($code));
        $hasChildren = ActivityType::query()
            ->where('activity_group_code', $group)
            ->where('activity_subgroup_code', $subgroup)
            ->where('activity_subsiri_code', $subsiriCode)
            ->exists();
        if ($hasChildren) {
            return $this->sendError(409, 'BAD_REQUEST', 'Unable to delete Activity Subsiri because activity type records exist.');
        }

        ActivitySubsiri::query()
            ->where('activity_group', $group)
            ->where('activity_subgroup_code', $subgroup)
            ->where('activity_subsiri_code', $subsiriCode)
            ->delete();

        return $this->sendOk(['success' => true]);
    }

    public function storeActivityType(StoreActivityTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $activityCode = Str::upper(trim($data['at_activity_code']));
        $groupCode = Str::upper(trim($data['activity_group_code']));
        $subgroupCode = Str::upper(trim($data['activity_subgroup_code']));
        $subsiriCode = Str::upper(trim($data['activity_subsiri_code']));
        $status = strtoupper($data['at_status']) === 'ACTIVE' ? '1' : '0';

        $exists = ActivityType::query()->where('at_activity_code', $activityCode)->exists();
        if ($exists) {
            return $this->sendError(400, 'BAD_REQUEST', 'Activity code already exists.');
        }

        $row = DB::connection('mysql_secondary')->transaction(function () use ($activityCode, $groupCode, $subgroupCode, $subsiriCode, $data, $status) {
            $maxId = (int) ActivityType::query()->lockForUpdate()->max('at_activity_id');
            $nextId = $maxId + 1;

            return ActivityType::create([
                'at_activity_id' => $nextId,
                'activity_group_code' => $groupCode,
                'activity_subgroup_code' => $subgroupCode,
                'activity_subsiri_code' => $subsiriCode,
                'at_activity_code' => $activityCode,
                'at_activity_description_bm' => trim($data['at_activity_description_bm']),
                'at_activity_description_en' => filled($data['at_activity_description_en'] ?? null) ? trim((string) $data['at_activity_description_en']) : null,
                'at_status' => $status,
                'createddate' => now(),
            ]);
        });

        return $this->sendCreated(['id' => (int) $row->at_activity_id]);
    }

    public function updateActivityType(UpdateActivityTypeRequest $request, int $id): JsonResponse
    {
        $row = ActivityType::query()->where('at_activity_id', $id)->first();
        if (! $row) {
            return $this->sendError(404, 'NOT_FOUND', 'Activity Type not found.');
        }

        $data = $request->validated();
        $status = strtoupper($data['at_status']) === 'ACTIVE' ? '1' : '0';

        $row->update([
            'at_activity_description_bm' => trim($data['at_activity_description_bm']),
            'at_activity_description_en' => filled($data['at_activity_description_en'] ?? null) ? trim((string) $data['at_activity_description_en']) : null,
            'at_status' => $status,
            'updateddate' => now(),
        ]);

        return $this->sendOk(['success' => true]);
    }

    public function deleteActivityType(int $id): JsonResponse
    {
        ActivityType::query()->where('at_activity_id', $id)->delete();

        return $this->sendOk(['success' => true]);
    }

    private function listGroups(string $search): JsonResponse
    {
        $query = ActivityGroup::query();
        if ($search !== '') {
            $needle = mb_strtolower($search, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(activity_group_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(activity_group_desc, "")) LIKE ?', [$like]);
            });
        }

        $rows = $query->orderBy('activity_group_code')->get()->map(fn (ActivityGroup $row) => [
            'activity_group_code' => $row->activity_group_code,
            'activity_group_desc' => $row->activity_group_desc,
        ]);

        return $this->sendOk($rows);
    }

    private function listSubgroups(Request $request, string $search): JsonResponse
    {
        $groupCode = Str::upper(trim((string) $request->input('activity_group_code')));
        if ($groupCode === '') {
            return $this->sendError(422, 'VALIDATION_ERROR', 'activity_group_code is required for level 1.');
        }

        $query = ActivitySubgroup::query()->where('activity_group_code', $groupCode);
        if ($search !== '') {
            $needle = mb_strtolower($search, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(activity_subgroup_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(activity_subgroup_desc, "")) LIKE ?', [$like]);
            });
        }

        $rows = $query->orderBy('activity_subgroup_code')->get()->map(fn (ActivitySubgroup $row) => [
            'activity_group_code' => $row->activity_group_code,
            'activity_subgroup_code' => $row->activity_subgroup_code,
            'activity_subgroup_desc' => $row->activity_subgroup_desc,
        ]);

        return $this->sendOk($rows);
    }

    private function listSubsiri(Request $request, string $search): JsonResponse
    {
        $groupCode = Str::upper(trim((string) $request->input('activity_group_code')));
        $subgroupCode = Str::upper(trim((string) $request->input('activity_subgroup_code')));
        if ($groupCode === '' || $subgroupCode === '') {
            return $this->sendError(422, 'VALIDATION_ERROR', 'activity_group_code and activity_subgroup_code are required for level 2.');
        }

        $query = ActivitySubsiri::query()
            ->where('activity_group', $groupCode)
            ->where('activity_subgroup_code', $subgroupCode);

        if ($search !== '') {
            $needle = mb_strtolower($search, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(activity_subsiri_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(activity_subsiri_desc, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(activity_subsiri_desc_eng, "")) LIKE ?', [$like]);
            });
        }

        $rows = $query->orderBy('activity_subsiri_code')->get()->map(fn (ActivitySubsiri $row) => [
            'activity_group' => $row->activity_group,
            'activity_subgroup_code' => $row->activity_subgroup_code,
            'activity_subsiri_code' => $row->activity_subsiri_code,
            'activity_subsiri_desc' => $row->activity_subsiri_desc,
            'activity_subsiri_desc_eng' => $row->activity_subsiri_desc_eng,
        ]);

        return $this->sendOk($rows);
    }

    private function listActivityTypes(Request $request, string $search): JsonResponse
    {
        $groupCode = Str::upper(trim((string) $request->input('activity_group_code')));
        $subgroupCode = Str::upper(trim((string) $request->input('activity_subgroup_code')));
        $subsiriCode = Str::upper(trim((string) $request->input('activity_subsiri_code')));
        $statusFilter = strtoupper(trim((string) $request->input('smart_filter_at_status', '')));

        if ($groupCode === '' || $subgroupCode === '' || $subsiriCode === '') {
            return $this->sendError(422, 'VALIDATION_ERROR', 'activity_group_code, activity_subgroup_code and activity_subsiri_code are required for level 3.');
        }

        $query = ActivityType::query()
            ->where('activity_group_code', $groupCode)
            ->where('activity_subgroup_code', $subgroupCode)
            ->where('activity_subsiri_code', $subsiriCode);

        if ($search !== '') {
            $needle = mb_strtolower($search, 'UTF-8');
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle) . '%';
            $query->where(function ($builder) use ($like) {
                $builder->whereRaw('LOWER(IFNULL(at_activity_code, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(at_activity_description_bm, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(IFNULL(at_activity_description_en, "")) LIKE ?', [$like]);
            });
        }

        if ($statusFilter === 'ACTIVE') {
            $query->where('at_status', '1');
        } elseif ($statusFilter === 'INACTIVE') {
            $query->where('at_status', '0');
        }

        $rows = $query->orderBy('at_activity_code')->get()->map(fn (ActivityType $row) => [
            'at_activity_id' => (int) $row->at_activity_id,
            'activity_group_code' => $row->activity_group_code,
            'activity_subgroup_code' => $row->activity_subgroup_code,
            'activity_subsiri_code' => $row->activity_subsiri_code,
            'at_activity_code' => $row->at_activity_code,
            'at_activity_description_bm' => $row->at_activity_description_bm,
            'at_activity_description_en' => $row->at_activity_description_en,
            'at_status' => ((string) $row->at_status === '1') ? 'ACTIVE' : 'INACTIVE',
            'at_status_value' => ((string) $row->at_status === '1') ? '1' : '0',
        ]);

        return $this->sendOk($rows);
    }
}
