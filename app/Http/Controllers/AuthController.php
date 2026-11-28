<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Register a new volunteer with status "pending" and return a Sanctum token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            // Always force volunteer + pending on public sign-up.
            $user = User::create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'],
                'role' => User::ROLE_VOLUNTEER,
                'status' => User::STATUS_PENDING,
                'is_active' => true,
            ]);

            $user->volunteerProfile()->create([
                'contact_number' => trim($data['contact_number']),
                'address_area' => trim($data['address_area']),
                'birthdate' => $data['birthdate'],
                'is_resident' => true,
                'availability' => $data['availability'] ?? VolunteerProfile::AVAILABILITY_AVAILABLE,
                'training' => $data['training'] ?? null,
            ]);

            if (! empty($data['skills'])) {
                $user->skills()->sync($data['skills']);
            }

            $user->consentRecords()->create([
                'policy_version' => $data['policy_version'] ?? '1.0',
                'accepted_at' => now(),
            ]);

            return $user;
        });

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
            'message' => 'Registration received. Waiting for admin approval.',
        ], 201);
    }
}
