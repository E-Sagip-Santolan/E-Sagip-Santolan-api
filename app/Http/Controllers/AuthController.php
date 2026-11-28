<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\VolunteerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

    /**
     * Authenticate a volunteer for the mobile app and log the sign-in with IP.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('email', strtolower(trim($data['email'])))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if (! $user->isVolunteer()) {
            return response()->json(['message' => 'Please use the admin portal to log in.'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'This account is disabled.'], 403);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        ActivityLog::record(
            $user->id,
            'login',
            'auth',
            'Volunteer signed in.',
            $request->ip(),
        );

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
            'message' => 'Logged in.',
        ], 200);
    }
}
