<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexor\Cms\Http\Controllers\Controller;
use Nexor\Cms\Support\Nexor;

abstract class ApiController extends Controller
{
    /**
     * Page size, capped so a crafted request cannot ask for everything at once.
     */
    protected function perPage(Request $request, string $preset = 'default'): int
    {
        $requested = (int) $request->integer('per_page');

        if ($requested < 1) {
            return Nexor::perPage($preset);
        }

        return min($requested, 200);
    }

    /**
     * Sort column limited to an allow list, plus the direction.
     *
     * @param  array<int, string>  $allowed
     * @return array{0: string, 1: string}
     */
    protected function sorting(Request $request, array $allowed, string $default, string $defaultDirection = 'asc'): array
    {
        $column = in_array($request->get('sort'), $allowed, true) ? $request->get('sort') : $default;
        $direction = in_array($request->get('direction'), ['asc', 'desc'], true)
            ? $request->get('direction')
            : $defaultDirection;

        return [$column, $direction];
    }

    protected function ok(string $message, array $payload = []): JsonResponse
    {
        return response()->json(['message' => $message] + $payload);
    }

    protected function refuse(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
