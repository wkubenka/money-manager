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

<section class="w-full">
    <x-page-heading title="Settings" subtitle="Manage your application settings" />

    <x-pages::settings.layout :heading="__('Data')" :subheading="__('Export and import your financial data')">
        <div class="space-y-6">
            {{-- Export Section --}}
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Export') }}</flux:heading>
                <flux:text>{{ __('Download all your data as a JSON file for backup.') }}</flux:text>
                <flux:button wire:click="exportData" variant="primary" icon="arrow-down-tray">
                    {{ __('Export Data') }}
                </flux:button>
            </div>

            <flux:separator />

            {{-- Import Section --}}
            <div class="space-y-3">
                <flux:heading size="sm">{{ __('Import') }}</flux:heading>
                <flux:text>{{ __('Restore data from a backup file. This will replace all existing data.') }}</flux:text>

                <input type="file" wire:model="importFile" accept=".json"
                    class="block w-full text-sm text-zinc-500 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-300" />

                <div wire:loading wire:target="importFile">
                    <flux:text class="text-sm">{{ __('Reading file...') }}</flux:text>
                </div>

                @if ($importErrors)
                    <flux:callout variant="danger" icon="exclamation-triangle">
                        <flux:callout.heading>{{ __('Invalid backup file') }}</flux:callout.heading>
                        <flux:callout.text>
                            <ul class="list-disc pl-4">
                                @foreach ($importErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </div>

            @if ($importSuccess)
                <flux:callout variant="success" icon="check-circle">
                    <flux:callout.text>{{ __('Data imported successfully.') }}</flux:callout.text>
                </flux:callout>
            @endif
        </div>

        {{-- Import Confirmation Modal --}}
        <flux:modal wire:model="showConfirmModal" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Replace all data?') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('This will permanently delete all your existing data and replace it with the backup. This cannot be undone.') }}</flux:text>
                </div>

                @if ($importSummary)
                    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                        <flux:text class="mb-2 font-medium">{{ __('Backup contains:') }}</flux:text>
                        <ul class="space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                            <li>{{ $importSummary['spending_plans'] }} {{ __('spending plans') }} ({{ $importSummary['spending_plan_items'] }} {{ __('items') }})</li>
                            <li>{{ $importSummary['net_worth_accounts'] }} {{ __('net worth accounts') }}</li>
                            <li>{{ $importSummary['rich_life_visions'] }} {{ __('rich life visions') }}</li>
                            <li>{{ $importSummary['expense_accounts'] }} {{ __('expense accounts') }} ({{ $importSummary['expenses'] }} {{ __('expenses') }})</li>
                        </ul>
                    </div>
                @endif

                <div class="flex gap-2">
                    <flux:button wire:click="cancelImport" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button wire:click="confirmImport" variant="danger">{{ __('Replace All Data') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </x-pages::settings.layout>
</section>
