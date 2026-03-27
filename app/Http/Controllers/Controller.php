<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

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
