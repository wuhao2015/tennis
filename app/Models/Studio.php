<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studio extends Model
{
    protected $guarded = ['id'];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')->wherePivot('status', 'approved');
    }

    public function adminIds(): array
    {
        return $this->members()->wherePivot('is_admin', true)->pluck('users.id')->all();
    }

    public function coaches(): BelongsToMany
    {
        return $this->members()->wherePivot('is_coach', true);
    }
}
