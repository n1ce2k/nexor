<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Nexor\Cms\Support\ActivityLogger;
use Throwable;

/**
 * Developer console, in the spirit of Bitrix's SQL and PHP command lines.
 *
 * These endpoints run whatever they are given, so they carry deliberate
 * guardrails that the rest of the panel does not need:
 *
 *   - super administrators only; the ability is not a grantable permission,
 *     so it can never be handed out through the roles screen;
 *   - a kill switch in config (`nexor.tools.enabled`) for locked-down installs;
 *   - every run is written to the activity log with the source text, so there
 *     is always a record of what was executed and by whom.
 */
class ToolsController extends ApiController
{
    public function state(Request $request): JsonResponse
    {
        return response()->json([
            'enabled' => $this->enabled(),
            'allowed' => (bool) $request->user()?->isSuperAdmin(),
            'php_allowed' => $this->phpEnabled(),
            'connection' => config('database.default'),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'php_version' => PHP_VERSION,
            'environment' => app()->environment(),
        ]);
    }

    public function sql(Request $request): JsonResponse
    {
        $this->authorizeTools($request);

        $validated = $request->validate([
            'query' => ['required', 'string', 'max:20000'],
        ], [], ['query' => 'запрос']);

        $sql = trim($validated['query']);
        $started = microtime(true);

        ActivityLogger::log('tool.sql', null, 'SQL: '.mb_substr($sql, 0, 180), ['query' => $sql]);

        try {
            // A SELECT-shaped statement returns rows; anything else reports the
            // number of affected rows instead.
            if ($this->isRead($sql)) {
                $rows = DB::select($sql);

                return response()->json([
                    'type' => 'rows',
                    'columns' => $rows === [] ? [] : array_keys((array) $rows[0]),
                    'rows' => array_map(fn ($row) => (array) $row, $rows),
                    'count' => count($rows),
                    'duration' => $this->elapsed($started),
                ]);
            }

            $affected = DB::affectingStatement($sql);

            return response()->json([
                'type' => 'affected',
                'affected' => $affected,
                'duration' => $this->elapsed($started),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'type' => 'error',
                'message' => $exception->getMessage(),
                'duration' => $this->elapsed($started),
            ], 422);
        }
    }

    public function php(Request $request): JsonResponse
    {
        $this->authorizeTools($request);

        abort_unless($this->phpEnabled(), 403, 'PHP-консоль отключена в конфигурации.');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20000'],
        ], [], ['code' => 'код']);

        $code = trim($validated['code']);
        $started = microtime(true);

        ActivityLogger::log('tool.php', null, 'PHP: '.mb_substr($code, 0, 180), ['code' => $code]);

        try {
            ob_start();
            $returned = eval($this->prepare($code));
            $output = ob_get_clean();

            return response()->json([
                'type' => 'result',
                'output' => $output,
                'returned' => $this->present($returned),
                'duration' => $this->elapsed($started),
            ]);
        } catch (Throwable $exception) {
            ob_end_clean();

            return response()->json([
                'type' => 'error',
                'message' => $exception::class.': '.$exception->getMessage(),
                'line' => $exception->getLine(),
                'duration' => $this->elapsed($started),
            ], 422);
        }
    }

    /**
     * Super admin plus the config switch — checked on every call, not just once.
     */
    protected function authorizeTools(Request $request): void
    {
        abort_unless($this->enabled(), 403, 'Инструменты разработчика отключены в конфигурации.');
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Доступно только супер-администратору.');
    }

    protected function enabled(): bool
    {
        return (bool) config('nexor.tools.enabled', false);
    }

    protected function phpEnabled(): bool
    {
        return $this->enabled() && (bool) config('nexor.tools.php', false);
    }

    /**
     * A statement that only reads, so its rows can be shown as a table.
     */
    protected function isRead(string $sql): bool
    {
        return (bool) preg_match('/^\s*(select|show|describe|desc|explain|pragma|with)\b/i', $sql);
    }

    /**
     * `eval` needs a statement, and a bare expression is the common case, so a
     * snippet without a trailing semicolon is treated as one to return.
     */
    protected function prepare(string $code): string
    {
        $code = preg_replace('/^\s*<\?php/i', '', $code);

        return str_contains($code, ';') ? $code : 'return '.$code.';';
    }

    protected function present(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_scalar($value)) {
            return var_export($value, true);
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
            ?: get_debug_type($value);
    }

    protected function elapsed(float $started): float
    {
        return round((microtime(true) - $started) * 1000, 2);
    }
}
