<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'status',
    'rejection_reason',
    'approved_by',
    'approved_at',
    'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_VOLUNTEER = 'volunteer';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPERADMIN = 'superadmin';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'approved_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Volunteer profile details (null for admin accounts).
     */
    public function volunteerProfile(): HasOne
    {
        return $this->hasOne(VolunteerProfile::class);
    }

    /**
     * Skills linked to this volunteer through volunteer_skills.
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'volunteer_skills', 'user_id', 'skill_id');
    }

    /**
     * Assigned permission rows for an admin account.
     */
    public function adminPermissions(): HasMany
    {
        return $this->hasMany(AdminPermission::class);
    }

    /**
     * Privacy consent records accepted by this user.
     */
    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    /**
     * Activity log entries triggered by this user.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Admin who approved this volunteer.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if the user is a superadmin.
     */
    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    /**
     * Check if the user is an admin (or superadmin when $includeSuperadmin is true).
     */
    public function isAdmin(bool $includeSuperadmin = false): bool
    {
        if ($includeSuperadmin && $this->isSuperadmin()) {
            return true;
        }

        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if the user is a volunteer.
     */
    public function isVolunteer(): bool
    {
        return $this->role === self::ROLE_VOLUNTEER;
    }

    /**
     * Check if the user holds a specific admin permission.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isSuperadmin()) {
            return true;
        }

        if (! $this->isAdmin()) {
            return false;
        }

        // Use already-loaded permissions when available to avoid extra queries.
        if ($this->relationLoaded('adminPermissions')) {
            return $this->adminPermissions->contains('permission', $permission);
        }

        return $this->adminPermissions()->where('permission', $permission)->exists();
    }

    /**
     * Return the list of permission names for API responses.
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        if ($this->isSuperadmin()) {
            return AdminPermission::ALL;
        }

        if (! $this->isAdmin()) {
            return [];
        }

        if ($this->relationLoaded('adminPermissions')) {
            return $this->adminPermissions->pluck('permission')->values()->all();
        }

        return $this->adminPermissions()->pluck('permission')->values()->all();
    }
}
