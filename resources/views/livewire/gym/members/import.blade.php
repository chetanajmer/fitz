<div>
    <x-ui.page-header title="Import members" subtitle="Upload a CSV or Excel (.xlsx) file to add members in bulk.">
        <x-slot:actions>
            <a href="{{ route('gym.members.import.sample') }}" class="btn-secondary">Download sample CSV</a>
            <a href="{{ route('gym.members.index') }}" class="btn-secondary">Back to members</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="import" class="card card-body space-y-4 lg:col-span-2">
            <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 px-6 py-10 text-center hover:border-indigo-400">
                <x-ui.icon name="upload" class="size-8 text-gray-400" />
                <span class="mt-2 text-sm font-medium text-gray-700">{{ $file ? $file->getClientOriginalName() : 'Choose a .csv or .xlsx file' }}</span>
                <span class="text-xs text-gray-500">Max 5 MB · first row must be headers</span>
                <input wire:model="file" type="file" accept=".csv,.xlsx" class="sr-only">
            </label>
            <div wire:loading wire:target="file" class="text-sm text-gray-500">Uploading…</div>
            @error('file') <p class="input-error">{{ $message }}</p> @enderror
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" @disabled(! $file)>
                <span wire:loading.remove wire:target="import">Import members</span>
                <span wire:loading wire:target="import">Importing…</span>
            </button>

            @if ($result)
                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-sm font-medium">
                        <span class="text-emerald-600">{{ $result['imported'] }} imported</span> ·
                        <span class="text-amber-700">{{ $result['skipped'] }} skipped</span>
                    </p>
                    @if ($result['errors'])
                        <ul class="mt-3 max-h-64 list-disc space-y-1 overflow-y-auto pl-5 text-xs text-red-700">
                            @foreach ($result['errors'] as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </form>

        <div class="card card-body text-sm">
            <h2 class="card-title">Supported columns</h2>
            <p class="mt-1 text-gray-500"><strong>name</strong> and <strong>phone</strong> are required. Rows with a phone number that already exists are skipped.</p>
            <ul class="mt-3 grid grid-cols-2 gap-1 font-mono text-xs text-gray-700">
                @foreach ($columns as $c)<li>{{ $c }}</li>@endforeach
            </ul>
            <p class="mt-3 text-xs text-gray-500">Dates: YYYY-MM-DD or DD-MM-YYYY. Imported members start without a plan — assign plans from each profile or the Memberships page.</p>
        </div>
    </div>
</div>
