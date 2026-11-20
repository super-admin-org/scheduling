<?php

namespace SuperAdmin\Admin\Scheduling;

use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SchedulingServiceProvider extends ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        // Template-aware: <views>/<template>/ and <template>/extensions/super-admin-scheduling/ override <views>/.
        if (class_exists(\SuperAdmin\Admin\Template\TemplateManager::class)) {
            \SuperAdmin\Admin\Facades\Admin::template()->extensionViews('super-admin-scheduling', __DIR__.'/../resources/views');
        } else {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'super-admin-scheduling');
        }

        Scheduling::boot();

        // Record runs made by `schedule:run` / `schedule:work`, shown as "Last run".
        Event::listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event) {
            Scheduling::rememberRun($event->task, 'scheduler', round((float) $event->runtime, 2), $event->task->exitCode);
        });
    }
}
