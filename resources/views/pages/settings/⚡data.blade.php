<?php

use App\Services\DataExporter;
use App\Services\DataImporter;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $importFile;

    /** @var array<int, string> */
    public array $importErrors = [];

    public bool $showConfirmModal = false;

    /** @var array<string, int> */
    public array $importSummary = [];

    public bool $importSuccess = false;

    public function exportData()
    {
        $exporter = new DataExporter;
        $data = $exporter->export();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'astute-money-backup-' . now()->format('Y-m-d') . '.json';

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function updatedImportFile(): void
    {
        $this->importErrors = [];
        $this->importSummary = [];
        $this->showConfirmModal = false;
        $this->importSuccess = false;

        if (! $this->importFile) {
            return;
        }

        $contents = file_get_contents($this->importFile->getRealPath());
        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->importErrors = ['The file is not valid JSON.'];

            return;
        }

        $importer = new DataImporter;
        $errors = $importer->validate($data);

        if (! empty($errors)) {
            $this->importErrors = $errors;

            return;
        }

        // Store validated data in session so it survives between Livewire requests
        session()->put('import_data', $data);

        $expenseCount = 0;
        foreach ($data['expense_accounts'] ?? [] as $account) {
            $expenseCount += count($account['expenses'] ?? []);
        }

        $itemCount = 0;
        foreach ($data['spending_plans'] ?? [] as $plan) {
            $itemCount += count($plan['items'] ?? []);
        }

        $this->importSummary = [
            'spending_plans' => count($data['spending_plans'] ?? []),
            'spending_plan_items' => $itemCount,
            'net_worth_accounts' => count($data['net_worth_accounts'] ?? []),
            'rich_life_visions' => count($data['rich_life_visions'] ?? []),
            'expense_accounts' => count($data['expense_accounts'] ?? []),
            'expenses' => $expenseCount,
        ];

        $this->showConfirmModal = true;
    }

    public function confirmImport(): void
    {
        $data = session()->pull('import_data');

        if (! $data) {
            return;
        }

        $importer = new DataImporter;
        $importer->import($data);

        $this->reset(['importFile', 'importErrors', 'importSummary', 'showConfirmModal']);
        $this->importSuccess = true;
    }

    public function cancelImport(): void
    {
        session()->forget('import_data');
        $this->reset(['importFile', 'importErrors', 'importSummary', 'showConfirmModal']);
    }
}; ?>

<section class="w-full px-10 py-9 max-w-[1320px] mx-auto">
    <x-page-heading eyebrow="Settings" title="Preferences" subtitle="Manage how Astute Money looks and behaves" />

    <x-pages::settings.layout :heading="__('Data')" :subheading="__('Export and import your financial data.')">
        <div class="flex flex-col gap-5 max-w-2xl">
            {{-- Export Card --}}
            <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 22px 24px;">
                <div class="eyebrow mb-2">{{ __('Export') }}</div>
                <div class="font-display text-vault-text mb-1.5" style="font-size: 18px;">{{ __('Download a backup') }}</div>
                <p class="text-[13px] text-vault-textsub mb-4">{{ __('Save all your data to a JSON file you can keep or import later.') }}</p>
                <flux:button wire:click="exportData" variant="primary" icon="arrow-down-tray">
                    {{ __('Export Data') }}
                </flux:button>
            </div>

            {{-- Import Card --}}
            <div class="rounded-2xl border border-vault-card-bd bg-vault-card" style="padding: 22px 24px;">
                <div class="eyebrow mb-2">{{ __('Import') }}</div>
                <div class="font-display text-vault-text mb-1.5" style="font-size: 18px;">{{ __('Restore from a backup') }}</div>
                <p class="text-[13px] text-vault-textsub mb-4">{{ __('Replace all existing data with the contents of a previously exported JSON file.') }}</p>

                <label
                    for="data-import-input"
                    class="flex flex-col items-center justify-center rounded-xl cursor-pointer transition hover:bg-vault-card-hov"
                    style="padding: 28px; border: 1px dashed var(--color-vault-card-bd); background: var(--color-vault-input);"
                >
                    <flux:icon name="arrow-up-tray" variant="micro" class="!size-5 text-vault-textsub mb-2" />
                    <div class="text-[13px] text-vault-textsub">
                        <span class="text-vault-accent font-semibold uppercase tracking-[0.13em]" style="font-size: 11px;">{{ __('Browse') }}</span>
                        <span class="ml-2">{{ __('to select a JSON file') }}</span>
                    </div>
                    <input id="data-import-input" type="file" wire:model="importFile" accept=".json" class="hidden" />
                </label>

                <div wire:loading wire:target="importFile" class="text-[12px] text-vault-textsub mt-3">{{ __('Reading file…') }}</div>

                @if ($importErrors)
                    <div class="rounded-xl mt-4"
                        style="padding: 14px 18px; background: color-mix(in srgb, var(--color-vault-red) 10%, transparent); border: 1px solid color-mix(in srgb, var(--color-vault-red) 32%, transparent);">
                        <div class="text-[12px] font-semibold mb-1" style="color: var(--color-vault-red);">{{ __('Invalid backup file') }}</div>
                        <ul class="list-disc pl-4 text-[12px]" style="color: var(--color-vault-red);">
                            @foreach ($importErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($importSuccess)
                    <div class="rounded-xl mt-4 flex items-center gap-2"
                        style="padding: 12px 18px; background: color-mix(in srgb, var(--color-vault-accent) 10%, transparent); border: 1px solid color-mix(in srgb, var(--color-vault-accent) 32%, transparent); color: var(--color-vault-accent); font-size: 12px;">
                        <flux:icon name="check-circle" variant="micro" class="!size-4" />
                        {{ __('Data imported successfully.') }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Import Confirmation Modal --}}
        <flux:modal wire:model="showConfirmModal" class="min-w-[22rem]">
            <div class="space-y-5">
                <div>
                    <div class="eyebrow mb-1.5">{{ __('Confirm import') }}</div>
                    <div class="font-display text-vault-text" style="font-size: 22px;">{{ __('Replace all data?') }}</div>
                    <p class="text-[13px] text-vault-textsub mt-2">{{ __('This will permanently delete all your existing data and replace it with the backup. This cannot be undone.') }}</p>
                </div>

                @if ($importSummary)
                    <div class="rounded-xl border border-vault-card-bd" style="padding: 16px 18px; background: var(--color-vault-input);">
                        <div class="eyebrow mb-2">{{ __('Backup contains') }}</div>
                        <ul class="space-y-1 text-[13px] text-vault-textsub">
                            <li>{{ $importSummary['spending_plans'] }} {{ __('spending plans') }} ({{ $importSummary['spending_plan_items'] }} {{ __('items') }})</li>
                            <li>{{ $importSummary['net_worth_accounts'] }} {{ __('net worth accounts') }}</li>
                            <li>{{ $importSummary['rich_life_visions'] }} {{ __('rich life visions') }}</li>
                            <li>{{ $importSummary['expense_accounts'] }} {{ __('expense accounts') }} ({{ $importSummary['expenses'] }} {{ __('expenses') }})</li>
                        </ul>
                    </div>
                @endif

                <div class="flex gap-2 justify-end">
                    <flux:button wire:click="cancelImport" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button wire:click="confirmImport" variant="danger">{{ __('Replace All Data') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </x-pages::settings.layout>
</section>
