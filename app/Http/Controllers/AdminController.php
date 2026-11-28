<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminRequest;
use App\Http\Requests\UpdateAdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * List admin and superadmin accounts (superadmin only).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);

        $admins = User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERADMIN])
            ->with('adminPermissions')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => AdminResource::collection($admins->items()),
            'message' => 'OK',
            'meta' => [
                'page' => $admins->currentPage(),
                'per_page' => $admins->perPage(),
                'total' => $admins->total(),
                'last_page' => $admins->lastPage(),
            ],
        ], 200);
    }

    /**
     * Create a new admin or superadmin account (superadmin only) and log the action.
     */
    public function store(StoreAdminRequest $request): JsonResponse
    {
        $data = $request->validated();

        $admin = DB::transaction(function () use ($request, $data) {
            $admin = User::create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'],
                'role' => $data['role'],
                'status' => User::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'is_active' => true,
            ]);

            if ($admin->isAdmin() && ! empty($data['permissions'])) {
                $this->syncPermissions($admin, $data['permissions']);
            }

            ActivityLog::record(
                $request->user()->id,
                'admin_created',
                'admins',
                "Created {$admin->role} #{$admin->id} ({$admin->email}).",
                $request->ip(),
            );

            return $admin;
        });

        $admin->load('adminPermissions');

        return response()->json([
            'data' => new AdminResource($admin),
            'message' => 'Admin created.',
        ], 201);
    }

    /**
     * Update an existing admin account (superadmin only) and log the action.
     */
    public function update(UpdateAdminRequest $request, int $id): JsonResponse
    {
        $admin = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERADMIN])->find($id);

        if (! $admin) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $request->validated();

        DB::transaction(function () use ($request, $admin, $data) {
            $updates = [];

            if (array_key_exists('name', $data)) {
                $updates['name'] = trim($data['name']);
            }

            if (array_key_exists('role', $data)) {
                $updates['role'] = $data['role'];
            }

            if (array_key_exists('is_active', $data)) {
                $updates['is_active'] = (bool) $data['is_active'];
            }

            if ($updates !== []) {
                $admin->update($updates);
            }

            if ($admin->isSuperadmin()) {
                $admin->adminPermissions()->delete();
            } elseif (array_key_exists('permissions', $data)) {
                $this->syncPermissions($admin, $data['permissions']);
            }

            ActivityLog::record(
                $request->user()->id,
                'admin_updated',
                'admins',
                "Updated {$admin->role} #{$admin->id} ({$admin->email}).",
                $request->ip(),
            );
        });

        $admin->refresh()->load('adminPermissions');

        return response()->json([
            'data' => new AdminResource($admin),
            'message' => 'Admin updated.',
        ], 200);
    }

    /**
     * Replace the assigned permission rows for an admin account.
     *
     * @param  array<int, string>  $permissions
     */
    private function syncPermissions(User $admin, array $permissions): void
    {
        $unique = array_values(array_unique($permissions));

        $admin->adminPermissions()->delete();

        if ($unique !== []) {
            $admin->adminPermissions()->createMany(
                array_map(fn (string $permission) => ['permission' => $permission], $unique)
            );
        }
    }
}
