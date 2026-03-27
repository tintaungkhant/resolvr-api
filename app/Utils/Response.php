<?php

namespace App\Utils;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class Response
{
    public const string CODE_SUCCESS = 'success';

    public const string CODE_ERROR = 'error';

    /**
     * Standard success envelope. Plain arrays are wrapped under `data` for a consistent shape.
     */
    public static function success(
        mixed $data = null,
        int $status = 200,
        ?string $message = null,
    ): JsonResponse {
        return response()->json(
            array_merge(
                [
                    'success' => true,
                    'code' => self::CODE_SUCCESS,
                    'message' => $message ?? 'Success',
                ],
                self::normalizeSuccessPayload($data),
            ),
            $status,
        );
    }

    /**
     * Error response. Accepts a {@see Throwable}, a structured array, or any other value that can be normalized
     * (e.g. string message, {@see Arrayable}, {@see \JsonSerializable}, generic object).
     */
    public static function error(
        mixed $payload = null,
        int $status = 400,
        ?string $message = null,
    ): JsonResponse {
        if ($payload instanceof Throwable) {
            return self::fromThrowable($payload);
        }

        if ($payload === null) {
            return self::fromArrayPayload([], $status, $message);
        }

        if (is_array($payload)) {
            return self::fromArrayPayload($payload, $status, $message);
        }

        if ($payload instanceof Arrayable) {
            return self::fromArrayPayload($payload->toArray(), $status, $message);
        }

        if (is_string($payload)) {
            return self::fromArrayPayload([], $status, $message ?? $payload);
        }

        if (is_scalar($payload)) {
            return self::fromArrayPayload([], $status, $message ?? (string) $payload);
        }

        if (is_object($payload)) {
            if ($payload instanceof \JsonSerializable) {
                $decoded = $payload->jsonSerialize();

                if (is_array($decoded)) {
                    return self::fromArrayPayload($decoded, $status, $message);
                }

                return self::fromArrayPayload(
                    ['data' => $decoded],
                    $status,
                    $message,
                );
            }

            $encoded = json_encode($payload);
            $asArray = is_string($encoded) ? json_decode($encoded, true) : null;

            return self::fromArrayPayload(is_array($asArray) ? $asArray : [], $status, $message);
        }

        return self::fromArrayPayload([], $status, $message);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeSuccessPayload(mixed $data): array
    {
        if ($data === null) {
            return ['data' => null];
        }

        if (is_scalar($data)) {
            return ['data' => $data];
        }

        if ($data instanceof ResourceCollection) {
            $resolved = $data->response()->getData(true);
            $items = Arr::pull($resolved, 'data');

            return array_merge(['data' => $items], $resolved);
        }

        if ($data instanceof JsonResource) {
            return $data->response()->getData(true);
        }

        if ($data instanceof Arrayable) {
            return ['data' => $data->toArray()];
        }

        if (is_array($data)) {
            return ['data' => $data];
        }

        return ['data' => $data];
    }

    private static function fromThrowable(Throwable $e): JsonResponse
    {
        $status = self::resolveHttpStatus($e);

        $body = [
            'success' => false,
            'code' => self::resolveErrorCode($e),
            'message' => self::resolveErrorMessage($e, $status),
            'errors' => self::resolveValidationErrors($e),
        ];

        if (config('app.debug')) {
            $body['debug'] = [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        return response()->json($body, $status);
    }

    /**
     * Builds a JSON error body from a loose array: `code`, `message`, and `errors` are optional.
     * If `errors` is omitted, every key other than `code`, `message`, or `success` is treated as the errors bag.
     * If `errors` is present, any additional keys are merged at the top level (e.g. `retry_after`).
     *
     * @param  array<string, mixed>  $payload
     */
    private static function fromArrayPayload(array $payload, int $status, ?string $message): JsonResponse
    {
        $reserved = ['code', 'message', 'errors', 'success'];

        $code = $payload['code'] ?? self::CODE_ERROR;
        if (! is_string($code)) {
            $code = is_scalar($code) ? (string) $code : self::CODE_ERROR;
        }

        $resolvedMessage = $message ?? self::resolvePayloadMessage($payload);

        if (array_key_exists('errors', $payload)) {
            $errors = $payload['errors'];
            $errors = is_array($errors) ? $errors : [];
            $extra = Arr::except($payload, $reserved);
        } else {
            $errors = Arr::except($payload, $reserved);
            $extra = [];
        }

        $body = array_merge(
            [
                'success' => false,
                'code' => $code,
                'message' => $resolvedMessage,
                'errors' => $errors,
            ],
            $extra,
        );

        return response()->json($body, $status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function resolvePayloadMessage(array $payload): string
    {
        if (! array_key_exists('message', $payload)) {
            return 'Error';
        }

        $value = $payload['message'];

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return 'Error';
    }

    private static function resolveHttpStatus(Throwable $e): int
    {
        return match (true) {
            $e instanceof ValidationException => $e->status,
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => $e->status() ?? 403,
            $e instanceof ModelNotFoundException => 404,
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            default => 500,
        };
    }

    private static function resolveErrorCode(Throwable $e): string
    {
        if (is_callable([$e, 'getErrorCode'])) {
            /** @var mixed $custom */
            $custom = call_user_func([$e, 'getErrorCode']);
            if (is_string($custom) && $custom !== '') {
                return $custom;
            }
        }

        if ($e instanceof ValidationException) {
            return 'validation_failed';
        }

        if ($e instanceof AuthenticationException) {
            return 'unauthenticated';
        }

        if ($e instanceof AuthorizationException) {
            return 'forbidden';
        }

        if ($e instanceof ModelNotFoundException) {
            return 'model_not_found';
        }

        if ($e instanceof HttpExceptionInterface) {
            return match ($e->getStatusCode()) {
                404 => 'not_found',
                403 => 'forbidden',
                401 => 'unauthorized',
                422 => 'unprocessable_entity',
                default => 'http_error',
            };
        }

        return self::defaultCodeFromExceptionClass($e);
    }

    private static function defaultCodeFromExceptionClass(Throwable $e): string
    {
        $base = class_basename($e);
        $base = (string) preg_replace('/Exception$/', '', $base);

        return Str::kebab($base);
    }

    /**
     * @return array<string, list<string>|string>
     */
    private static function resolveValidationErrors(Throwable $e): array
    {
        if ($e instanceof ValidationException) {
            return $e->errors();
        }

        if (is_callable([$e, 'errors'])) {
            /** @var mixed $errors */
            $errors = call_user_func([$e, 'errors']);

            return is_array($errors) ? $errors : [];
        }

        return [];
    }

    private static function resolveErrorMessage(Throwable $e, int $status): string
    {
        if ($e instanceof ValidationException) {
            return $e->getMessage();
        }

        $message = $e->getMessage();
        if ($message !== '') {
            return $message;
        }

        if ($e instanceof HttpExceptionInterface) {
            return SymfonyResponse::$statusTexts[$e->getStatusCode()] ?? 'Error';
        }

        if ($status === 500 && ! config('app.debug')) {
            return 'Server Error';
        }

        return SymfonyResponse::$statusTexts[$status] ?? 'Error';
    }
}
