<?php

namespace SuperAdmin\Admin\Scheduling;

use Illuminate\Http\Request;
use SuperAdmin\Admin\Facades\Admin;
use SuperAdmin\Admin\Layout\Content;
use Throwable;

class SchedulingController
{
    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Admin::content(function (Content $content) {
            $content->header('Task scheduling');

            $scheduling = new Scheduling();

            $content->body(view('super-admin-scheduling::index', [
                'events' => $scheduling->getTasks(),
                'canRun' => Scheduling::canRun(),
            ]));
        });
    }

    /**
     * @return array{status: bool, message: string, data: string, meta?: array<string, mixed>}
     */
    public function runEvent(Request $request)
    {
        if (! Scheduling::canRun()) {
            return [
                'status'  => false,
                'message' => 'failed',
                'data'    => 'Running tasks from the panel is disabled (admin.extensions.scheduling.allow_run).',
            ];
        }

        $id = (string) $request->input('id', '');

        try {
            $result = (new Scheduling())->runTask($id);

            return [
                'status'  => true,
                'message' => 'success',
                'data'    => $result['output'],
                'meta'    => [
                    'duration' => $result['duration'],
                    'exitCode' => $result['exitCode'],
                ],
            ];
        } catch (Throwable $e) {
            return [
                'status'  => false,
                'message' => 'failed',
                'data'    => $e->getMessage(),
            ];
        }
    }
}
