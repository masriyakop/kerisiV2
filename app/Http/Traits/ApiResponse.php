<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function sendOk($data, ?array $meta = null, int $status = 200): JsonResponse
    {
        $response = ['data' => $data];
        if ($meta !== null) {
            $response['meta'] = $meta;
        }
        return response()->json($response, $status);
    }

    protected function sendError(int $status, string $code, string $message, $details = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }
        return response()->json(['error' => $error], $status);
    }

    protected function sendCreated($data): JsonResponse
    {
        return $this->sendOk($data, null, 201);
    }

    protected function sendNoContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
