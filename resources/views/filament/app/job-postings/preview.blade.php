<x-filament-panels::page>
    <div x-data="{ device: 'mobile' }" class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::tabs>
                <x-filament::tabs.item alpine-active="device === 'mobile'" x-on:click="device = 'mobile'" icon="heroicon-o-device-phone-mobile">
                    {{ __('job_postings.preview.mobile') }}
                </x-filament::tabs.item>
                <x-filament::tabs.item alpine-active="device === 'desktop'" x-on:click="device = 'desktop'" icon="heroicon-o-computer-desktop">
                    {{ __('job_postings.preview.desktop') }}
                </x-filament::tabs.item>
            </x-filament::tabs>

            @if (count($this->versions()))
                <label class="text-sm">
                    {{ __('job_postings.preview.version') }}
                    <select wire:model.live="version" class="ms-2 rounded border-gray-300 text-sm dark:bg-gray-800">
                        <option value="">{{ __('job_postings.preview.working_copy') }}</option>
                        @foreach ($this->versions() as $number)
                            <option value="{{ $number }}">v{{ $number }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>

        <div class="mx-auto transition-all" x-bind:style="device === 'mobile' ? 'max-width: 375px' : 'max-width: 48rem'">
            <iframe
                src="{{ $this->previewUrl() }}"
                wire:key="preview-{{ $version ?? 'draft' }}"
                title="{{ __('job_postings.preview.title', ['title' => $this->posting()->title]) }}"
                style="width:100%;height:75vh;border:1px solid rgb(226 232 240);border-radius:0.5rem;background:#fff"
                loading="lazy"></iframe>
        </div>

        <p class="text-center text-xs text-gray-500">{{ __('job_postings.preview.notice') }}</p>
    </div>
</x-filament-panels::page>
