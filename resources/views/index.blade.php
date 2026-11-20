<style>
    .sched-output {
        white-space: pre-wrap;
        word-break: break-word;
        background: #0f172a;
        color: #4ade80;
        padding: 0.75rem 1rem;
        border-radius: var(--sa-radius-sm, 0.375rem);
        max-height: 480px;
        overflow: auto;
        margin: 0;
    }
</style>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th style="width: 2rem">#</th>
                    <th>Task</th>
                    <th>Schedule</th>
                    <th>Next run</th>
                    <th>Last run</th>
                    <th>Description</th>
                    @if($canRun)
                        <th></th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @forelse($events as $index => $event)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <code>{{ $event['task']['name'] }}</code>
                            @foreach($event['flags'] as $flag)
                                <span class="badge bg-light text-dark ms-1">{{ $flag }}</span>
                            @endforeach
                        </td>
                        <td>
                            <span class="badge bg-success">{{ $event['expression'] }}</span>
                            <div class="small text-muted mt-1">{{ $event['readable'] }}</div>
                        </td>
                        <td>
                            {{ $event['nextRunDate'] }}
                            <div class="small text-muted">{{ $event['timezone'] }}</div>
                        </td>
                        <td class="sched-last-run">
                            @if($event['lastRun'])
                                {{ $event['lastRun']['at'] }}
                                <div class="small text-muted">
                                    {{ $event['lastRun']['source'] === 'panel' ? 'from the panel'.($event['lastRun']['by'] ? ' by '.$event['lastRun']['by'] : '') : 'by the scheduler' }}
                                    @if(! is_null($event['lastRun']['exitCode']))
                                        &middot;
                                        <span class="{{ $event['lastRun']['exitCode'] === 0 ? 'text-success' : 'text-danger' }}">exit {{ $event['lastRun']['exitCode'] }}</span>
                                    @endif
                                    @if(! is_null($event['lastRun']['duration']))
                                        &middot; {{ $event['lastRun']['duration'] }} s
                                    @endif
                                </div>
                            @else
                                <span class="text-muted">never</span>
                            @endif
                        </td>
                        <td>{{ $event['description'] }}</td>
                        @if($canRun)
                            <td class="text-end">
                                <button type="button" class="btn btn-xs btn-primary sched-run" data-id="{{ $event['id'] }}">
                                    <i class="icon-play"></i> Run
                                </button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">
                            No scheduled tasks. Define them in <code>routes/console.php</code> (Laravel 11+) or <code>app/Console/Kernel.php</code>.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card sched-output-box d-none mt-4">
    <div class="card-header">
        <h3 class="card-title"><i class="icon-terminal"></i> Output</h3>
        <span class="sched-output-meta small text-muted ms-auto"></span>
    </div>
    <div class="card-body">
        <pre class="sched-output"></pre>
    </div>
</div>

<script data-exec-on-popstate>
    (function () {
        var box = document.querySelector('.sched-output-box');
        var output = box.querySelector('.sched-output');
        var meta = box.querySelector('.sched-output-meta');

        document.querySelectorAll('.sched-run').forEach(function (button) {
            button.addEventListener('click', function () {
                button.disabled = true;
                box.classList.remove('d-none');
                output.textContent = 'Running ...';
                meta.textContent = '';

                admin.ajax.post('{{ route('scheduling-run') }}', {id: button.dataset.id}, function (result) {
                    button.disabled = false;
                    var response = result.data || {};

                    output.textContent = response.data || '(no output)';

                    if (response.status && response.meta) {
                        meta.textContent = 'exit ' + (response.meta.exitCode === null ? '-' : response.meta.exitCode) + ' · ' + response.meta.duration + ' s';
                    } else if (!response.status) {
                        meta.textContent = 'failed';
                    }
                });
            });
        });
    })();
</script>
