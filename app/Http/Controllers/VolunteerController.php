<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListVolunteersRequest;
use App\Http\Requests\RejectVolunteerRequest;
use App\Http\Resources\VolunteerResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VolunteerController extends Controller
{
    /**
     * List and filter volunteers for authorized admins (includes contact details).
     */
    public function index(ListVolunteersRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = User::query()
            ->where('role', User::ROLE_VOLUNTEER)
            ->with(['volunteerProfile', 'skills'])
            ->latest('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['skill'])) {
            $query->whereHas('skills', fn ($q) => $q->where('skills.id', $filters['skill']));
        }

        if (! empty($filters['availability'])) {
            $query->whereHas('volunteerProfile', fn ($q) => $q->where('availability', $filters['availability']));
        }

        if (! empty($filters['address_area'])) {
            $area = '%'.strtolower(trim($filters['address_area'])).'%';
            $query->whereHas('volunteerProfile', fn ($q) => $q->whereRaw('LOWER(address_area) LIKE ?', [$area]));
        }

        if (! empty($filters['search'])) {
            $term = '%'.strtolower(trim($filters['search'])).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereHas('volunteerProfile', fn ($p) => $p->whereRaw('LOWER(contact_number) LIKE ?', [$term]));
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $volunteers = $query->paginate($perPage);

        return response()->json([
            'data' => VolunteerResource::collection($volunteers->items()),
            'message' => 'OK',
            'meta' => [
                'page' => $volunteers->currentPage(),
                'per_page' => $volunteers->perPage(),
                'total' => $volunteers->total(),
                'last_page' => $volunteers->lastPage(),
            ],
        ], 200);
    }

    /**
     * Return volunteer counts grouped by approval status.
     */
    public function summary(): JsonResponse
    {
        $counts = User::query()
            ->where('role', User::ROLE_VOLUNTEER)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $pending = (int) ($counts[User::STATUS_PENDING] ?? 0);
        $approved = (int) ($counts[User::STATUS_APPROVED] ?? 0);
        $rejected = (int) ($counts[User::STATUS_REJECTED] ?? 0);

        return response()->json([
            'data' => [
                'total' => $pending + $approved + $rejected,
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
            ],
            'message' => 'OK',
        ], 200);
    }

    /**
     * Approve a pending volunteer and record the action in activity_logs.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $volunteer = User::where('role', User::ROLE_VOLUNTEER)->find($id);

        if (! $volunteer) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($volunteer->status !== User::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only pending volunteers can be approved.',
                'errors' => ['status' => ["Volunteer is already {$volunteer->status}."]],
            ], 422);
        }

        DB::transaction(function () use ($request, $volunteer) {
            $volunteer->update([
                'status' => User::STATUS_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            ActivityLog::record(
                $request->user()->id,
                'volunteer_approved',
                'volunteers',
                "Approved volunteer #{$volunteer->id} ({$volunteer->email}).",
                $request->ip(),
            );
        });

        $volunteer->refresh()->load(['volunteerProfile', 'skills']);

        return response()->json([
            'data' => new VolunteerResource($volunteer),
            'message' => 'Volunteer approved.',
        ], 200);
    }

    /**
     * Reject a pending volunteer (reason optional) and record the action in activity_logs.
     */
    public function reject(RejectVolunteerRequest $request, int $id): JsonResponse
    {
        $volunteer = User::where('role', User::ROLE_VOLUNTEER)->find($id);

        if (! $volunteer) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($volunteer->status !== User::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only pending volunteers can be rejected.',
                'errors' => ['status' => ["Volunteer is already {$volunteer->status}."]],
            ], 422);
        }

        $reason = $request->validated('reason');
        $reason = is_string($reason) && trim($reason) !== '' ? trim($reason) : null;

        DB::transaction(function () use ($request, $volunteer, $reason) {
            $volunteer->update([
                'status' => User::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);

            $details = "Rejected volunteer #{$volunteer->id} ({$volunteer->email}).";
            if ($reason !== null) {
                $details .= " Reason: {$reason}";
            }

            ActivityLog::record(
                $request->user()->id,
                'volunteer_rejected',
                'volunteers',
                $details,
                $request->ip(),
            );
        });

        $volunteer->refresh()->load(['volunteerProfile', 'skills']);

        return response()->json([
            'data' => new VolunteerResource($volunteer),
            'message' => 'Volunteer rejected.',
        ], 200);
    }
}
