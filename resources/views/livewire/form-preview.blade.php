<div x-data="linkDynamicForm({ answers: $wire.entangle('answers'), conditions: @js($conditions) })">
    <h1 class="text-lg font-semibold">{{ $posting->title }}</h1>

    @if ($fields === [] && $documents === [])
        <p class="mt-4 text-sm text-slate-600">{{ __('job_postings.preview.empty') }}</p>
    @else
        <form wire:submit="check" novalidate class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
            <x-dynamic.form :fields="$fields" :errors="$errors" />

            @foreach ($documents as $field)
                <div class="mb-5" @if ($field->visibleIf) x-show="visible(@js($field->key))" x-cloak @endif>
                    <p class="text-sm font-medium">{{ $field->label }}@if ($field->required)<span aria-hidden="true" class="text-red-600"> *</span>@endif</p>
                    <p class="text-xs text-slate-500">{{ __('applications.wizard.file_constraints', [
                        'formats' => strtoupper(implode(', ', $field->file['extensions'])),
                        'size' => \App\Support\Applications\AnswerFormatter::megabytes($field->file['max_kb']),
                        'count' => $field->file['max_files'],
                    ]) }}</p>
                    <span class="mt-2 inline-block rounded border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 opacity-60">{{ __('applications.upload.choose') }}</span>
                </div>
            @endforeach

            @if ($valid)
                <p class="mb-3 rounded border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900" role="status">{{ __('job_postings.preview.valid') }}</p>
            @endif

            <button type="submit" class="rounded bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('job_postings.preview.check') }}</button>
        </form>
    @endif
</div>
