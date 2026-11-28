<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerProfile extends Model
{
    public const AVAILABILITY_AVAILABLE = 'available';

    public const AVAILABILITY_UNAVAILABLE = 'unavailable';

    public const AVAILABILITY_ON_OPERATION = 'on_operation';

    public const AVAILABILITIES = [
        self::AVAILABILITY_AVAILABLE,
        self::AVAILABILITY_UNAVAILABLE,
        self::AVAILABILITY_ON_OPERATION,
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'contact_number',
        'address_area',
        'birthdate',
        'is_resident',
        'availability',
        'training',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'is_resident' => 'boolean',
        ];
    }

    /**
     * User that owns this volunteer profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
