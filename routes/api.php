<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Agent\AuthController as AgentAuthController;
use App\Http\Controllers\Api\V1\Client\AuthController as ClientAuthController;

include __DIR__.'/api-agent.php';
include __DIR__.'/api-client.php';
