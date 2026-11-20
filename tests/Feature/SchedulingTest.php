<?php

namespace SuperAdmin\Admin\Scheduling\Tests\Feature;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use SuperAdmin\Admin\Scheduling\CronSchedule;
use SuperAdmin\Admin\Scheduling\Scheduling;
use SuperAdmin\Admin\Scheduling\Tests\TestCase;

class SchedulingTest extends TestCase
{
    protected function tearDown(): void
    {
        Artisan::forgetBootstrappers();

        parent::tearDown();
    }

    /**
     * Simulate an HTTP request on Laravel 11+: tasks are registered by an Artisan::starting
     * callback (routes/console.php, ->withSchedule()) and Artisan has not started yet.
     */
    protected function defineTasksTheLaravel11Way(callable $define): void
    {
        Artisan::starting(function () use ($define) {
            $define(app(Schedule::class));
        });

        $this->app->forgetInstance(Kernel::class);
        $this->app->forgetInstance(Schedule::class);
    }

    public function test_tasks_registered_like_routes_console_php_are_listed(): void
    {
        $this->defineTasksTheLaravel11Way(function (Schedule $schedule) {
            $schedule->command('inspire')->hourly()->description('Show a quote');
            $schedule->command('cache:prune-stale-tags')->dailyAt('02:00')->withoutOverlapping();
        });

        $this->get('admin/scheduling')
            ->assertOk()
            ->assertSee('artisan inspire')
            ->assertSee('Show a quote')
            ->assertSee('0 * * * *')
            ->assertSee('artisan cache:prune-stale-tags')
            ->assertSee('At 02:00 on every day.')
            ->assertSee('without overlapping');
    }

    public function test_empty_schedule_shows_a_hint(): void
    {
        $this->defineTasksTheLaravel11Way(function () {
        });

        $this->get('admin/scheduling')
            ->assertOk()
            ->assertSee('No scheduled tasks');
    }

    public function test_run_executes_the_task_and_records_the_last_run(): void
    {
        $marker = storage_path('framework/testing-scheduling-marker');
        @unlink($marker);

        $this->defineTasksTheLaravel11Way(function (Schedule $schedule) use ($marker) {
            $schedule->call(function () use ($marker) {
                file_put_contents($marker, 'ran');
            })->everyMinute()->description('Write a marker');
        });

        $tasks = (new Scheduling())->getTasks();
        $this->assertCount(1, $tasks);
        $this->assertNull($tasks[0]['lastRun']);

        $this->post('admin/scheduling/run', ['id' => $tasks[0]['id']])
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertFileExists($marker);
        @unlink($marker);

        $this->get('admin/scheduling')
            ->assertOk()
            ->assertSee('from the panel')
            ->assertDontSee('never');
    }

    public function test_run_captures_command_output(): void
    {
        $this->defineTasksTheLaravel11Way(function (Schedule $schedule) {
            $schedule->exec('echo scheduling-output-check')->daily();
        });

        $id = (new Scheduling())->getTasks()[0]['id'];

        $response = $this->post('admin/scheduling/run', ['id' => $id])->assertOk();

        $this->assertTrue($response->json('status'));
        $this->assertStringContainsString('scheduling-output-check', $response->json('data'));
        $this->assertSame(0, $response->json('meta.exitCode'));
    }

    public function test_a_stale_task_id_is_rejected(): void
    {
        $this->defineTasksTheLaravel11Way(function (Schedule $schedule) {
            $schedule->command('inspire')->hourly();
        });

        $this->post('admin/scheduling/run', ['id' => '1:0000000000000000'])
            ->assertOk()
            ->assertJson(['status' => false, 'data' => 'The task list has changed. Reload the page and try again.']);
    }

    public function test_run_can_be_disabled(): void
    {
        config(['admin.extensions.scheduling.allow_run' => false]);

        $this->defineTasksTheLaravel11Way(function (Schedule $schedule) {
            $schedule->command('inspire')->hourly();
        });

        $this->get('admin/scheduling')->assertOk()->assertDontSee('sched-run"', false);

        $id = (new Scheduling())->getTasks()[0]['id'];

        $this->post('admin/scheduling/run', ['id' => $id])->assertJson(['status' => false]);
    }

    public function test_runs_by_the_scheduler_are_recorded(): void
    {
        $this->defineTasksTheLaravel11Way(function (Schedule $schedule) {
            $schedule->command('inspire')->hourly();
        });

        app(Kernel::class)->all();
        $event = app(Schedule::class)->events()[0];
        $event->exitCode = 0;

        event(new ScheduledTaskFinished($event, 1.234));

        $lastRun = Scheduling::lastRun($event);
        $this->assertSame('scheduler', $lastRun['source']);
        $this->assertSame(0, $lastRun['exitCode']);
        $this->assertSame(1.23, $lastRun['duration']);
    }

    public function test_cron_descriptions_load_without_php_8_warnings(): void
    {
        $this->assertStringNotContainsString(
            'final private',
            file_get_contents(dirname(__DIR__, 2).'/src/CronSchedule.php')
        );

        set_error_handler(function ($severity, $message) {
            throw new \ErrorException($message, 0, $severity);
        });

        try {
            $this->assertSame('At 02:00 on every day.', CronSchedule::fromCronString('0 2 * * *')->asNaturalLanguage());
            $this->assertSame('Every minute on every day.', CronSchedule::fromCronString('* * * * *')->asNaturalLanguage());
        } finally {
            restore_error_handler();
        }
    }

    public function test_page_renders_in_the_bootstrap_classic_template(): void
    {
        config(['admin.template' => 'bootstrap-classic']);

        $this->defineTasksTheLaravel11Way(function (Schedule $schedule) {
            $schedule->command('inspire')->hourly();
        });

        $this->get('admin/scheduling')->assertOk()->assertSee('artisan inspire');
    }
}
