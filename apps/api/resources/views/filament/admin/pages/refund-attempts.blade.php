<div class="space-y-4">
    @forelse ($attempts as $attempt)
        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                    #{{ $attempt->attempt_number }} · {{ str_replace('_', ' ', $attempt->operation) }}
                </p>
                <span @class([
                    'rounded-md px-2 py-0.5 text-xs font-medium',
                    'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => in_array($attempt->outcome, ['succeeded', 'found']),
                    'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $attempt->outcome === 'declined',
                    'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => in_array($attempt->outcome, ['uncertain', 'not_found', 'lookup_failed']),
                ])>{{ $attempt->outcome }}</span>
            </div>

            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                <div>Status: {{ $attempt->status_before ?? '—' }} → {{ $attempt->status_after ?? '—' }}</div>
                <div>At: {{ $attempt->created_at?->format('M j, Y H:i:s') }}</div>
                <div>HTTP: {{ $attempt->http_status ?? '—' }}</div>
                <div>Duration: {{ $attempt->duration_ms !== null ? $attempt->duration_ms . ' ms' : '—' }}</div>
                <div class="col-span-2 break-all">Request id: {{ $attempt->provider_request_id ?? '—' }}</div>
                <div class="col-span-2 break-all">Refund: {{ $attempt->provider_refund_id ?? '—' }}</div>
                <div class="col-span-2 break-all">Idempotency key: {{ $attempt->idempotency_key }}</div>
            </dl>

            @if ($attempt->error_message)
                <p class="mt-2 text-xs text-danger-600 dark:text-danger-400">{{ $attempt->error_message }}</p>
            @endif

            @if ($attempt->response)
                <details class="mt-2">
                    <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400">Provider response</summary>
                    <pre class="mt-1 max-h-64 overflow-auto rounded bg-gray-50 p-2 text-[11px] text-gray-800 dark:bg-gray-900 dark:text-gray-200">{{ json_encode($attempt->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">No provider interactions recorded for this refund yet.</p>
    @endforelse
</div>
