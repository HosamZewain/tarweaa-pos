<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\HasFilamentPermissions;

class ShiftPolicy
{
    use HasFilamentPermissions;
}
