<?php

namespace SuperAdmin\Admin\Scheduling;

use DateTimeZone;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use SuperAdmin\Admin\Admin;
use SuperAdmin\Admin\Extension;
use SuperAdmin\Admin\Facades\Admin as AdminFacade;

class Scheduling extends Extension
{
    /**
     * Cache key prefix of the "last run" records.
     */
    public const LAST_RUN_KEY = 'super-admin-scheduling:last-run:';

    public $name = 'scheduling';

    /**
     * Get all events of the scheduler.
     *
     * Laravel 11+ defines tasks in routes/console.php and ->withSchedule(); both are
     * only loaded when Artisan starts, so the console kernel is booted first.
     *
     * @return array<int, Event>
     */
    protected function getKernelEvents(): array
    {
        $kernel = app(Kernel::class);

        if (method_exists($kernel, 'all')) {
            $kernel->all();
        }

        return array_values(app(Schedule::class)->events());
    }

    /**
     * Get all formatted tasks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTasks(): array
    {
        $tasks = [];

        foreach ($this->getKernelEvents() as $index => $event) {
            $tasks[] = [
                'id'          => $this->taskId($index, $event),
                'task'        => $this->formatTask($event),
                'expression'  => $event->expression,
                'nextRunDate' => $event->nextRunDate()->format('Y-m-d H:i:s'),
                'timezone'    => $this->timezoneName($event),
                'description' => $event->description,
                'readable'    => CronSchedule::fromCronString($event->expression)->asNaturalLanguage(),
                'flags'       => $this->flags($event),
                'lastRun'     => static::lastRun($event),
            ];
        }

        return $tasks;
    }

    /**
     * Run a task now and return its output.
     *
     * @return array{output: string, duration: float, exitCode: int|null}
     */
    public function runTask(string $id): array
    {
        $events = $this->getKernelEvents();
        $index = ((int) Str::before($id, ':')) - 1;

        if (! isset($events[$index]) || $this->taskId($index, $events[$index]) !== $id) {
            throw new RuntimeException('The task list has changed. Reload the page and try again.');
        }

        $event = $events[$index];

        set_time_limit(0);

        if (PHP_OS_FAMILY === 'Windows' && is_string($event->command)) {
            $event->command = str_replace('php-cgi.exe', 'php.exe', $event->command);
        }

        $outputFile = $this->outputFile($event);
        @unlink($outputFile);
        $event->sendOutputTo($outputFile);

        $started = microtime(true);

        try {
            $event->run(app());
        } finally {
            $result = [
                'output'   => is_file($outputFile) ? (string) file_get_contents($outputFile) : '',
                'duration' => round(microtime(true) - $started, 2),
                'exitCode' => $event->exitCode,
            ];

            static::rememberRun($event, 'panel', $result['duration'], $result['exitCode']);
        }

        return $result;
    }

    /**
     * Record a run of a task (from the panel or from `schedule:run`).
     */
    public static function rememberRun(Event $event, string $source, ?float $duration = null, ?int $exitCode = null): void
    {
        $user = $source === 'panel' ? AdminFacade::user() : null;

        Cache::put(static::LAST_RUN_KEY.static::signature($event), [
            'at'       => now()->toDateTimeString(),
            'source'   => $source,
            'duration' => $duration,
            'exitCode' => $exitCode,
            'by'       => $user ? ($user->name ?? $user->username ?? null) : null,
        ], now()->addDays(30));
    }

    /**
     * @return array{at: string, source: string, duration: float|null, exitCode: int|null, by: string|null}|null
     */
    public static function lastRun(Event $event): ?array
    {
        return Cache::get(static::LAST_RUN_KEY.static::signature($event));
    }

    /**
     * A signature that identifies a task across requests.
     */
    public static function signature(Event $event): string
    {
        $command = $event instanceof CallbackEvent ? 'closure' : (string) $event->command;

        return substr(sha1($event->expression.'|'.$command.'|'.$event->description), 0, 16);
    }

    /**
     * Task id used by the "Run" button: position plus signature, so a changed list is detected.
     */
    protected function taskId(int $index, Event $event): string
    {
        return ($index + 1).':'.static::signature($event);
    }

    /**
     * @return array{type: string, name: string}
     */
    protected function formatTask(Event $event): array
    {
        if ($event instanceof CallbackEvent) {
            return [
                'type' => 'closure',
                'name' => $event->description ?: 'Closure',
            ];
        }

        $command = (string) $event->command;

        foreach (["'artisan'", '"artisan"'] as $artisan) {
            if (Str::contains($command, $artisan)) {
                return [
                    'type' => 'artisan',
                    'name' => 'artisan '.trim(Str::after($command, $artisan)),
                ];
            }
        }

        return [
            'type' => 'command',
            'name' => $command,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function flags(Event $event): array
    {
        $flags = [];

        if ($event->withoutOverlapping) {
            $flags[] = 'without overlapping';
        }
        if ($event->onOneServer) {
            $flags[] = 'on one server';
        }
        if ($event->runInBackground) {
            $flags[] = 'in background';
        }
        if ($event->evenInMaintenanceMode) {
            $flags[] = 'even in maintenance';
        }
        if (! empty($event->environments)) {
            $flags[] = 'env: '.implode(', ', (array) $event->environments);
        }

        return $flags;
    }

    protected function timezoneName(Event $event): string
    {
        $timezone = $event->timezone ?? config('app.timezone');

        return $timezone instanceof DateTimeZone ? $timezone->getName() : (string) $timezone;
    }

    protected function outputFile(Event $event): string
    {
        $directory = storage_path('app/scheduling');

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        return $directory.DIRECTORY_SEPARATOR.static::signature($event).'.log';
    }

    /**
     * Whether the "Run" button is available (`admin.extensions.scheduling.allow_run`).
     */
    public static function canRun(): bool
    {
        return (bool) static::config('allow_run', true);
    }

    /**
     * Bootstrap this package.
     *
     * @return void
     */
    public static function boot()
    {
        static::registerRoutes();

        Admin::extend('scheduling', __CLASS__);
    }

    /**
     * Register routes for super-admin.
     *
     * @return void
     */
    protected static function registerRoutes()
    {
        parent::routes(function ($router) {
            /* @var \Illuminate\Routing\Router $router */
            $router->get('scheduling', 'SuperAdmin\Admin\Scheduling\SchedulingController@index')->name('scheduling-index');
            $router->post('scheduling/run', 'SuperAdmin\Admin\Scheduling\SchedulingController@runEvent')->name('scheduling-run');
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function import()
    {
        parent::createMenu('Scheduling', 'scheduling', 'icon-clock');

        parent::createPermission('Scheduling', 'ext.scheduling', 'scheduling*');
    }
}
