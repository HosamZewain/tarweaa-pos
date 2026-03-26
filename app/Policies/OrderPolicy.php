<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Traits\HasFilamentPermissions;

class OrderPolicy
{
    use HasFilamentPermissions;
}
