@php
    $chunkBytes = \App\Http\Controllers\JsonUploadController::CHUNK_BYTES;
    $maxBytes = \App\Filament\Pages\ImportJson::MAX_UPLOAD_KB * 1024;
    $endpoint = route('json-upload', ['upload' => '00000000-0000-0000-0000-000000000000'], absolute: false);
@endphp

<div
    x-data="{
        name: @js($this->uploadName),
        progress: @js($this->upload ? 100 : null),
        sent: 0,
        size: 0,
        busy: false,
        error: null,
        megabytes(bytes) {
            return (bytes / 1048576).toFixed(1);
        },
        async choose(event) {
            const file = event.target.files[0];
            event.target.value = '';

            if (! file || this.busy) {
                return;
            }

            this.error = null;

            if (! /\.(json|txt)$/i.test(file.name)) {
                this.error = @js(__('admin.json.wrong_file'));

                return;
            }

            if (file.size > {{ $maxBytes }}) {
                this.error = @js(__('admin.json.too_big', ['mb' => \App\Filament\Pages\ImportJson::MAX_UPLOAD_KB / 1024]));

                return;
            }

            const id = crypto.randomUUID();
            this.busy = true;
            this.name = file.name;
            this.size = file.size;
            this.sent = 0;
            this.progress = 0;
            await $wire.uploaded(null, null);

            try {
                let offset = 0;

                while (offset < file.size) {
                    offset = await this.send(id, offset, file.slice(offset, offset + {{ $chunkBytes }}));
                    this.sent = offset;
                    this.progress = Math.floor(offset / file.size * 100);
                }

                await $wire.uploaded(id, file.name);
            } catch (error) {
                this.error = @js(__('admin.json.upload_failed'));
                this.name = null;
                this.progress = null;
            } finally {
                this.busy = false;
            }
        },
        async send(id, offset, chunk) {
            for (let attempt = 0; ; attempt++) {
                let response = null;

                try {
                    response = await fetch(@js($endpoint).replace('00000000-0000-0000-0000-000000000000', id), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/octet-stream',
                            'X-CSRF-TOKEN': @js(csrf_token()),
                            'X-Upload-Offset': String(offset),
                        },
                        body: chunk,
                    });
                } catch (error) {
                    response = null;
                }

                if (response && (response.ok || response.status === 409)) {
                    return (await response.json()).size;
                }

                if (response && response.status < 500 && response.status !== 429) {
                    throw new Error('upload_' + response.status);
                }

                if (attempt >= 5) {
                    throw new Error('upload_failed');
                }

                await new Promise(resolve => setTimeout(resolve, 2000 * (attempt + 1)));
            }
        },
    }"
    class="flex flex-col gap-2"
>
    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('admin.json.file') }}</span>

    <label
        class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-gray-300 px-4 py-3 hover:border-primary-500 dark:border-white/15"
        :class="busy && 'pointer-events-none opacity-70'"
    >
        <input type="file" accept=".json,.txt,application/json,text/plain" class="sr-only" x-on:change="choose($event)" :disabled="busy">
        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowUpTray" class="size-5 shrink-0 text-gray-400" />
        <span class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-200" x-text="name || @js(__('admin.json.choose_file'))"></span>
        <span x-show="progress !== null" class="shrink-0 text-xs font-semibold tabular-nums text-gray-500 dark:text-gray-400" x-text="progress === 100 && ! busy ? @js(__('admin.json.uploaded')) : progress + '%'"></span>
    </label>

    <div x-show="busy" x-cloak class="flex flex-col gap-1">
        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
            <div class="h-full rounded-full bg-primary-500 transition-[width] duration-300" :style="{ width: progress + '%' }"></div>
        </div>
        <span class="text-xs text-gray-500 dark:text-gray-400" x-text="megabytes(sent) + ' / ' + megabytes(size) + ' MB'"></span>
    </div>

    <p x-show="error" x-cloak class="text-sm text-danger-600 dark:text-danger-400" x-text="error"></p>

    @error('data.file')
        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
    @enderror

    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.json.file_help', ['mb' => \App\Filament\Pages\ImportJson::MAX_UPLOAD_KB / 1024]) }}</p>
</div>
