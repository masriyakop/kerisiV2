<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    use ApiResponse;

    /**
     * List all roles with user count.
     */
    public function index(): JsonResponse
    {
        $roles = Role::withCount('users')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendOk($roles);
    }

    /**
     * Create a new role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Check name uniqueness
        $existing = Role::where('name', $data['name'])->first();
        if ($existing) {
            return $this->sendError(409, 'DUPLICATE_NAME', 'A role with this name already exists');
        }

        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? [],
        ]);

        return $this->sendOk($role);
    }

    /**
     * Show a single role.
     */
    public function show(int $id): JsonResponse
    {
        $role = Role::withCount('users')->find($id);

        if (! $role) {
            return $this->sendError(404, 'NOT_FOUND', 'Role not found');
        }

        return $this->sendOk($role);
    }

    /**
     * Update an existing role.
     */
    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $role = Role::find($id);

        if (! $role) {
            return $this->sendError(404, 'NOT_FOUND', 'Role not found');
        }

        $data = $request->validated();

        // Check name uniqueness if changed
        if ($data['name'] !== $role->name) {
            $nameTaken = Role::where('name', $data['name'])->first();
            if ($nameTaken) {
                return $this->sendError(409, 'DUPLICATE_NAME', 'A role with this name already exists');
            }
        }

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? [],
        ]);

        return $this->sendOk($role);
    }

    /**
     * Delete a role (only if not in use).
     */
    public function destroy(int $id): JsonResponse
    {
        $role = Role::withCount('users')->find($id);

        if (! $role) {
            return $this->sendError(404, 'NOT_FOUND', 'Role not found');
        }

        if ($role->users_count > 0) {
            return $this->sendError(409, 'ROLE_IN_USE', 'Role is currently assigned to users and cannot be deleted');
        }

        $role->delete();

        return $this->sendOk(['success' => true]);
    }
}
