<?php

namespace App\Policies;

use App\Models\TeacherInvite;
use App\Models\User;

class TeacherInvitePolicy
{
    public function manage(User $user, TeacherInvite $invite): bool
    {
        return $user->role === 'director'
            && (int) $user->colegio_id > 0
            && (int) $user->colegio_id === (int) $invite->colegio_id;
    }
}
