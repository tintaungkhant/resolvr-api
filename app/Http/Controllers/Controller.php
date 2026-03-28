<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * @throws AuthenticationException
     */
    protected function authUser(): User
    {
        $user = request()->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    protected function authorizePolicy(string $policyClass, string $method, mixed ...$arguments): void
    {
        $policy = app($policyClass);
        $user = request()->user();

        $result = $policy->{$method}($user, ...$arguments);

        if ($result !== true) {
            throw new AuthorizationException;
        }
    }
}
