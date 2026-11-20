Scheduling task manager for Super Admin
=======================================

Lists the tasks of Laravel's scheduler (cron expression in plain words, next run, last run, flags) and runs a task on demand from the admin panel.

**Documentation:** https://super-admin.org/laravel/extension-scheduling/

Requirements: PHP 8.0+, Laravel 8 – 13, Super Admin 1.x.

## Installation

```bash
composer require super-admin-org/scheduling
php artisan admin:import scheduling
```

Open `/admin/scheduling`.

## Defining tasks

Laravel 11 and newer, `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:run')->dailyAt('02:00')->description('Nightly backup');
```

Laravel 8 – 10, `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('backup:run')->dailyAt('02:00')->description('Nightly backup');
}
```

Both are listed. The scheduler itself still needs the cron entry `* * * * * php artisan schedule:run`.

## Configuration

```php
// config/admin.php
'extensions' => [
    'scheduling' => [
        'allow_run' => true,   // false hides the Run buttons (recommended in production)
    ],
],
```

**Last run** shows the latest run from the panel and from `schedule:run`, kept in the cache for 30 days.

If the output shows `sh: : command not found`, set `PHP_BINARY=/path/to/php` in `.env`.

## Tests

```bash
composer install
composer test
```

License
-------
Licensed under [The MIT License (MIT)](LICENSE).
