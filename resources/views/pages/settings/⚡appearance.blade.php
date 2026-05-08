<?php

use Livewire\Component;

new class extends Component {
    //
}; ?>

<section class="w-full px-10 py-9 max-w-[1320px] mx-auto">
    <x-page-heading eyebrow="Settings" title="Preferences" subtitle="Manage how Astute Money looks and behaves" />

    <flux:heading class="sr-only">{{ __('Appearance Settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Switch between light, dark, and system themes.')">
        <div class="rounded-2xl border border-vault-card-bd bg-vault-card max-w-lg" style="padding: 22px 24px;">
            <div class="eyebrow mb-3">{{ __('Theme') }}</div>
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
            </flux:radio.group>
        </div>
    </x-pages::settings.layout>
</section>
