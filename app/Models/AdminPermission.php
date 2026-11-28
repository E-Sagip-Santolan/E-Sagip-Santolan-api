<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminPermission extends Model
{
    public const VOLUNTEER_APPROVALS = 'volunteer_approvals';

    public const OPERATION_MANAGEMENT = 'operation_management';

    public const ATTENDANCE_CONFIRMATION = 'attendance_confirmation';

    public const COMMUNITY_PUBLISHING = 'community_publishing';

    public const REPORTS_EXPORTS = 'reports_exports';

    public const ACTIVITY_LOGS = 'activity_logs';

    public const ALL = [
        self::VOLUNTEER_APPROVALS,
        self::OPERATION_MANAGEMENT,
        self::ATTENDANCE_CONFIRMATION,
        self::COMMUNITY_PUBLISHING,
        self::REPORTS_EXPORTS,
        self::ACTIVITY_LOGS,
    ];

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'permission',
    ];

    /**
     * Admin user who holds this permission.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
