<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CamelCaseMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Convert incoming camelCase keys to snake_case
        if ($request->isJson() || $request->wantsJson()) {
            $input = $request->all();
            $request->replace($this->convertKeysToSnakeCase($input));
        }

        $response = $next($request);

        // Convert outgoing snake_case keys to camelCase
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            $response->setData($this->convertKeysToCamelCase($data));
        }

        return $response;
    }

    private function convertKeysToSnakeCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $snakeKey = Str::snake($key);
            $result[$snakeKey] = is_array($value) ? $this->convertKeysToSnakeCase($value) : $value;
        }
        return $result;
    }

    private function convertKeysToCamelCase($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            // Preserve keys starting with underscore (e.g., _count from Prisma compatibility)
            $camelKey = is_string($key) ? (str_starts_with($key, '_') ? $key : Str::camel($key)) : $key;
            $result[$camelKey] = is_array($value) ? $this->convertKeysToCamelCase($value) : $value;
        }
        return $result;
    }
}
