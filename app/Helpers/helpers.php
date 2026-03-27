<?php

use App\Utils\Response;
use Illuminate\Http\JsonResponse;

if (! function_exists('successResponse')) {
    function successResponse(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return Response::success($data, $status, $message);
    }
}

if (! function_exists('errorResponse')) {
    function errorResponse(mixed $data = null, ?string $message = null, int $status = 400): JsonResponse
    {
        return Response::error($data, $status, $message);
    }
}
