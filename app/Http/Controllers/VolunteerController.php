<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListVolunteersRequest;
use App\Http\Resources\VolunteerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
}
