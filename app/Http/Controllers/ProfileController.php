<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\MeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /**
     * Return the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['volunteerProfile', 'skills', 'adminPermissions']);

        return response()->json([
            'data' => new MeResource($user),
            'message' => 'OK',
        ], 200);
    }

    /**
     * Update only the submitted fields on the authenticated user's own profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            if (array_key_exists('name', $data)) {
                $user->update(['name' => trim($data['name'])]);
            }

            if ($user->isVolunteer() && $user->volunteerProfile) {
                $profileFields = array_intersect_key($data, array_flip([
                    'contact_number',
                    'address_area',
                    'availability',
                    'training',
                ]));

                if (isset($profileFields['contact_number'])) {
                    $profileFields['contact_number'] = trim($profileFields['contact_number']);
                }

                if (isset($profileFields['address_area'])) {
                    $profileFields['address_area'] = trim($profileFields['address_area']);
                }

                if ($profileFields !== []) {
                    $user->volunteerProfile->update($profileFields);
                }

                if (array_key_exists('skills', $data)) {
                    $user->skills()->sync($data['skills']);
                }
            }
        });

        $user->refresh()->load(['volunteerProfile', 'skills', 'adminPermissions']);

        return response()->json([
            'data' => new MeResource($user),
            'message' => 'Profile updated.',
        ], 200);
    }
}
