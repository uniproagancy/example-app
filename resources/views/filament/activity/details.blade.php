<div class="space-y-3 text-sm">
    <p class="text-gray-500 dark:text-gray-400">
        {{ $log->created_at->format('d.m.Y H:i') }} · {{ $log->user?->name ?? '—' }} · {{ __("admin.activity.events.{$log->event}") }}@if ($log->ip) · {{ $log->ip }}@endif
    </p>
    <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-white/5 dark:border-white/10">
        @foreach ($lines as $line)
            <li class="px-3 py-2 break-words text-gray-950 dark:text-white">{{ $line }}</li>
        @endforeach
    </ul>
</div>
