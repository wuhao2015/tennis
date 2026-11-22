<?php

namespace App\Policies;

use App\Models\Studio;
use App\Models\User;

class StudioPolicy
{
    public function manage(User $user, Studio $studio): bool
    {
        return $studio->memberships()->where('user_id', $user->id)
            ->where('status', 'approved')->where('is_admin', true)->exists();
    }
}
