<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    /**
     * Transform the authenticated user with their volunteer profile (null for admins).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = null;

        if ($this->isVolunteer() && $this->volunteerProfile) {
            $profile = [
                'contact_number' => $this->volunteerProfile->contact_number,
                'address_area' => $this->volunteerProfile->address_area,
                'birthdate' => $this->volunteerProfile->birthdate?->toDateString(),
                'is_resident' => (bool) $this->volunteerProfile->is_resident,
                'availability' => $this->volunteerProfile->availability,
                'training' => $this->volunteerProfile->training,
                'skills' => SkillResource::collection($this->skills),
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'is_active' => (bool) $this->is_active,
            'rejection_reason' => $this->rejection_reason,
            'permissions' => $this->permissionNames(),
            'profile' => $profile,
        ];
    }
}
