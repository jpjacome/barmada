<?php

namespace Tests\Concerns;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * API-test helper: authenticates the test client as a Sanctum token
 * holder with the same role-derived abilities a real login would stamp.
 */
trait ActsAsApiUser
{
    protected function apiActingAs(User $user): User
    {
        Sanctum::actingAs($user, $user->apiTokenAbilities());

        return $user;
    }
}
