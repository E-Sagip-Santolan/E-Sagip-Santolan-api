<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerResource extends JsonResource
{
    /**
     * Transform a volunteer user for admin list and approval responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'contact_number' => $this->volunteerProfile?->contact_number,
            'address_area' => $this->volunteerProfile?->address_area,
            'birthdate' => $this->volunteerProfile?->birthdate?->toDateString(),
            'is_resident' => $this->volunteerProfile ? (bool) $this->volunteerProfile->is_resident : null,
            'status' => $this->status,
            'is_active' => (bool) $this->is_active,
            'rejection_reason' => $this->rejection_reason,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'availability' => $this->volunteerProfile?->availability,
            'training' => $this->volunteerProfile?->training,
            'skills' => SkillResource::collection($this->skills),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
