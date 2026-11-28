<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'category',
    ];

    /**
     * Volunteers who have this skill.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'volunteer_skills', 'skill_id', 'user_id');
    }
}
