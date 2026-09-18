<?php

namespace Nexor\Cms\Support\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Обновление и установка пакетов из панели.
 *
 * Работа долгая, веб-запрос столько не живёт, поэтому в фон уходит одна
 * artisan-команда `nexor:updates:run`, а она уже выполняет шаги и пишет их
 * вывод в файл — панель читает файл и показывает ход. Одновременно идёт одна
 * задача: две установки composer мешают друг другу.
 *
 * В оболочку уходит только идентификатор задачи. Что именно запускать, лежит
 * в файле состояния и собирается из закрытого списка PackageCatalog, поэтому
 * подставить свою команду из браузера нельзя.
 */
class ComposerRunner
{
    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    /** Задача, зависшая дольше этого срока, считается брошенной. */
    public const TIMEOUT = 1800;

    /**
     * Можно ли вообще запускать процессы на этом хостинге.
     *
     * @return array{ok: bool, composer: string|null, reason: string|null}
     */
    public static function availability(): array
    {
        if (! function_exists('proc_open') || in_array('proc_open', self::disabledFunctions(), true)) {
            return ['ok' => false, 'composer' => null, 'reason' => 'На сервере отключена функция proc_open — запускать composer из панели нельзя.'];
        }

        $composer = self::composer();

        if ($composer === null) {
            return ['ok' => false, 'composer' => null, 'reason' => 'Composer не найден. Укажите путь к нему в NEXOR_COMPOSER.'];
        }

        if (! is_writable(base_path('vendor'))) {
            return ['ok' => false, 'composer' => $composer, 'reason' => 'Папка vendor закрыта на запись — обновление не сможет её изменить.'];
        }

        return ['ok' => true, 'composer' => $composer, 'reason' => null];
    }

    /**
     * Команда composer: из конфига, из PATH или лежащий рядом composer.phar.
     */
    public static function composer(): ?string
    {
        $configured = trim((string) config('nexor.updates.composer', ''));

        if ($configured !== '') {
            return $configured;
        }

        if ($found = (new ExecutableFinder)->find('composer')) {
            return $found;
        }

        return is_file(base_path('composer.phar')) ? base_path('composer.phar') : null;
    }

    /**
     * Ставит задачу в работу и возвращает её идентификатор.
     *
     * @param  array<int, array{type: string, arguments: array<int, string>, title: string}>  $steps
     */
    public static function start(string $title, array $steps): string
    {
        $id = (string) Str::uuid();

        File::ensureDirectoryExists(dirname(self::path($id.'.log')));
        self::forget();
        File::put(self::path($id.'.log'), $title.PHP_EOL);
        File::put(self::path('current'), $id);

        self::state($id, [
            'id' => $id,
            'title' => $title,
            'state' => self::RUNNING,
            'steps' => $steps,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'exit_code' => null,
        ]);

        self::launch($id);

        return $id;
    }

    /**
     * Ход и итог задачи; null — такой задачи нет.
     *
     * @return array<string, mixed>|null
     */
    public static function status(?string $id = null): ?array
    {
        $id ??= self::current();

        if ($id === null || ! is_file(self::path($id.'.json'))) {
            return null;
        }

        /** @var array<string, mixed> $state */
        $state = json_decode((string) File::get(self::path($id.'.json')), true) ?: [];

        // Процесс мог умереть молча — тогда задача закрывается по времени.
        if (($state['state'] ?? null) === self::RUNNING && strtotime((string) $state['started_at']) < time() - self::TIMEOUT) {
            $state['state'] = self::FAILED;
            $state['finished_at'] = now()->toIso8601String();
            $state['exit_code'] = -1;
            self::state($id, $state);
        }

        $state['output'] = self::plain(is_file(self::path($id.'.log')) ? (string) File::get(self::path($id.'.log')) : '');

        return $state;
    }

    public static function current(): ?string
    {
        $path = self::path('current');

        return is_file($path) ? trim((string) File::get($path)) : null;
    }

    public static function isRunning(): bool
    {
        return (self::status()['state'] ?? null) === self::RUNNING;
    }

    /**
     * Выполняет шаги задачи. Зовётся командой `nexor:updates:run` уже в фоне.
     */
    public static function execute(string $id): int
    {
        $state = self::status($id);

        if ($state === null) {
            return 1;
        }

        $exitCode = 0;

        foreach ($state['steps'] as $step) {
            self::append($id, PHP_EOL.'$ '.$step['title'].PHP_EOL);

            $process = new Process(self::command($step), base_path(), self::environment(), null, self::TIMEOUT);
            $exitCode = $process->run(fn (string $type, string $chunk) => self::append($id, $chunk));

            if ($exitCode !== 0) {
                break;
            }
        }

        $state['state'] = $exitCode === 0 ? self::DONE : self::FAILED;
        $state['exit_code'] = $exitCode;
        $state['finished_at'] = now()->toIso8601String();
        unset($state['output']);

        self::state($id, $state);
        self::append($id, PHP_EOL.($exitCode === 0 ? 'Готово.' : 'Не получилось, код выхода '.$exitCode.'.').PHP_EOL);

        return $exitCode;
    }

    /**
     * @param  array{type: string, arguments: array<int, string>}  $step
     * @return array<int, string>
     */
    protected static function command(array $step): array
    {
        $php = (new PhpExecutableFinder)->find() ?: 'php';
        $composer = self::composer() ?? 'composer';

        // composer.phar запускается тем же php, что и сайт.
        $binary = $step['type'] === 'artisan'
            ? [$php, base_path('artisan')]
            : (str_ends_with($composer, '.phar') ? [$php, $composer] : [$composer]);

        return [...$binary, ...$step['arguments']];
    }

    /**
     * Запускает фоновый процесс так, чтобы он пережил конец веб-запроса.
     */
    protected static function launch(string $id): void
    {
        $php = (new PhpExecutableFinder)->find() ?: 'php';
        $command = [$php, base_path('artisan'), 'nexor:updates:run', $id];
        $line = implode(' ', array_map(fn (string $part) => escapeshellarg($part), $command));

        $shell = self::isWindows()
            ? 'start /B "" '.$line
            : 'nohup '.$line.' > /dev/null 2>&1 &';

        Process::fromShellCommandline($shell, base_path(), self::environment())
            ->setTimeout(null)
            ->start();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function state(string $id, array $state): void
    {
        File::ensureDirectoryExists(dirname(self::path($id.'.json')));
        File::put(self::path($id.'.json'), json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Вывод без управляющих последовательностей: в браузере они только мешают.
     */
    public static function plain(string $text): string
    {
        return (string) preg_replace('/\e\[[0-9;]*[A-Za-z]/', '', $text);
    }

    public static function append(string $id, string $text): void
    {
        File::append(self::path($id.'.log'), $text);
    }

    /**
     * Стирает задачи старше недели: их логи больше никому не нужны.
     */
    public static function forget(int $days = 7): void
    {
        $directory = dirname(self::path('x'));

        if (! is_dir($directory)) {
            return;
        }

        foreach (File::files($directory) as $file) {
            if ($file->getFilename() !== 'current' && $file->getMTime() < time() - $days * 86400) {
                File::delete($file->getPathname());
            }
        }
    }

    public static function path(string $file): string
    {
        return storage_path('app/nexor/updates/'.$file);
    }

    /**
     * Окружение процесса: без домашней папки composer не знает, где кеш.
     *
     * @return array<string, string>
     */
    protected static function environment(): array
    {
        $home = (string) (getenv('COMPOSER_HOME') ?: getenv('HOME') ?: getenv('USERPROFILE') ?: storage_path('app/nexor/composer-home'));

        File::ensureDirectoryExists($home);

        return [
            'COMPOSER_HOME' => $home,
            'COMPOSER_NO_INTERACTION' => '1',
            'COMPOSER_ALLOW_SUPERUSER' => '1',
        ];
    }

    protected static function isWindows(): bool
    {
        return str_starts_with(strtoupper(PHP_OS_FAMILY), 'WIN');
    }

    /**
     * @return array<int, string>
     */
    protected static function disabledFunctions(): array
    {
        return array_map('trim', explode(',', (string) ini_get('disable_functions')));
    }
}
