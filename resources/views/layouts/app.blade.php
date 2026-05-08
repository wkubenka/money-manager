<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main role="main" class="!bg-vault-bg !text-vault-text !p-0 flex-1 overflow-y-auto !w-full !max-w-none">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
