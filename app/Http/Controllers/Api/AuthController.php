<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Traits\ApiResponse;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService,
    ) {}

    /**
     * Authenticate a user and start a session.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return $this->sendError(401, 'INVALID_CREDENTIALS', 'Invalid email or password');
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $this->auditService->logAuth('login', $user);

        return $this->sendOk([
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Log the user out and invalidate the session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user) {
            $this->auditService->logAuth('logout', $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->sendOk(['success' => true]);
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->sendOk([
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Update the authenticated user's profile (name and/or email).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [];
        if ($request->has('name')) {
            $data['name'] = $request->input('name');
        }
        if ($request->has('email')) {
            $data['email'] = $request->input('email');
        }

        $user->update($data);
        $user->refresh();

        return $this->sendOk([
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6',
        ]);

        $user = $request->user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return $this->sendError(400, 'INVALID_PASSWORD', 'Current password is incorrect');
        }

        $user->update([
            'password' => Hash::make($request->input('new_password')),
        ]);

        return $this->sendOk(['message' => 'Password changed successfully']);
    }

    /**
     * Upload an avatar for the authenticated user.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:png,jpg,jpeg,gif,webp|max:2048',
        ]);

        $user = $request->user();

        // Remove old avatar if exists
        if ($user->photo_url) {
            $oldPath = 'uploads/' . basename($user->photo_url);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $filename = 'avatar-' . time() . '.' . $ext;

        $file->storeAs('uploads', $filename, 'public');

        $user->update([
            'photo_url' => '/storage/uploads/' . $filename,
        ]);
        $user->refresh();

        return $this->sendOk([
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Remove the authenticated user's avatar.
     */
    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->photo_url) {
            $oldPath = 'uploads/' . basename($user->photo_url);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $user->update(['photo_url' => null]);
        $user->refresh();

        return $this->sendOk([
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Build the user payload for API responses.
     */
    protected function userPayload($user): array
    {
        return [
            'id'       => $user->id,
            'email'    => $user->email,
            'name'     => $user->name,
            'photo_url' => $user->photo_url,
            'role'     => $user->role,
        ];
    }
}
