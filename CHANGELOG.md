# Changelog

## v1.1.0 - 2026-11-20

### Changed

- Works with super-admin 2.x: the package now requires `super-admin-org/super-admin` `^1.0|^2.0`. Earlier versions required `^1.0`, so Composer could not install them next to super-admin v2.0.0.
- Package homepage and documentation: https://super-admin.org/laravel/extension-scheduling/

## v1.0.4 - 2026-11-20

### Fixed

- Laravel 11+: tasks defined in `routes/console.php` or `->withSchedule()` were not listed (they load only when Artisan starts); the console kernel is now booted before reading the schedule.
- PHP 8: `CronSchedule` declared 20 `final private` methods ("Private methods cannot be final" warning on load) and converted `false` to an array (8.1 deprecation).
- Task output was inserted as HTML; it is shown as text now.
- **Run** used the position in the list only; a changed task list could run the wrong task. Task ids now include a signature and stale ids are refused.
- All runs shared one output file; each task has its own (`storage/app/scheduling/<id>.log`).

### Added

- **Last run** column: time, source (panel or scheduler), user, exit code and duration, recorded for runs from the panel and from `schedule:run`.
- Task flags (without overlapping, on one server, in background, environments) and the timezone of the next run.
- `allow_run` option to hide the Run buttons.
- Tests on Orchestra Testbench.

### Changed

- Requires PHP 8.0+ and declares Laravel 8 – 13 (`illuminate/support`).
