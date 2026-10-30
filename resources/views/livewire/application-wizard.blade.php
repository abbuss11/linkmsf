@php
    use App\Support\Applications\AnswerFormatter;
    use App\Support\Applications\ApplicationSteps;
    $stepLabels = [
        ApplicationSteps::PROFILE => __('applications.wizard.steps.profile'),
        ApplicationSteps::QUESTIONS => __('applications.wizard.steps.questions'),
        ApplicationSteps::DOCUMENTS => __('applications.wizard.steps.documents'),
        ApplicationSteps::REVIEW => __('applications.wizard.steps.review'),
    ];
@endphp

<div x-data="linkDynamicForm({ answers: $wire.entangle('answers'), conditions: @js($conditions) })">
    <p class="text-sm text-slate-600">{{ $organization->name }}</p>
    <h1 class="text-xl font-semibold">{{ $posting->title }}</h1>
    @if ($application->isSubmitted())
        <p class="mt-2 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ __('applications.wizard.editing', ['reference' => $application->reference]) }}</p>
    @endif

    <nav aria-label="{{ __('applications.wizard.progress') }}" class="mt-4">
        <ol class="flex flex-wrap gap-2 text-sm">
            @foreach ($steps as $index => $number)
                <li>
                    <button type="button" wire:click="goTo({{ $number }})"
                        @class([
                            'rounded-full px-3 py-1',
                            'bg-brand-600 text-white' => $number === $step,
                            'bg-white text-slate-700 border border-slate-300' => $number !== $step,
                        ])
                        @if ($number === $step) aria-current="step" @endif
                        @if ($number > $application->last_step) disabled @endif>
                        {{ $index + 1 }}. {{ $stepLabels[$number] }}
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    <div class="mt-6 rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        @error('form')
            <p class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>
        @enderror

        @if ($step === ApplicationSteps::PROFILE)
            @if ($posting->setting('instructions'))
                <p class="mb-4 rounded bg-brand-50 p-3 text-sm">{{ $posting->setting('instructions') }}</p>
            @endif
            <p class="mb-4 text-sm text-slate-600">{{ __('applications.wizard.profile_intro') }}</p>
            <x-dynamic.form :fields="$fields" :errors="$errors" />
            <label class="mt-2 flex items-start gap-2 text-sm">
                <input type="checkbox" wire:model="updateProfile" class="mt-0.5 h-5 w-5">
                <span>{{ __('applications.wizard.update_profile') }}</span>
            </label>
        @elseif ($step === ApplicationSteps::QUESTIONS)
            <x-dynamic.form :fields="$fields" :errors="$errors" />
        @elseif ($step === ApplicationSteps::DOCUMENTS)
            <p class="mb-4 text-sm text-slate-600">{{ __('applications.wizard.documents_intro') }}</p>
            @foreach ($fields as $field)
                @php($mine = $documents->where('field_key', $field->key))
                @php($reusable = \App\Livewire\ApplicationWizard::compatibleProfileDocuments($profileDocuments, $field))
                <section class="mb-6 border-b border-slate-100 pb-6 last:border-0" wire:key="doc-{{ $field->key }}"
                    @if ($field->visibleIf) x-show="visible(@js($field->key))" x-cloak @endif>
                    <h2 class="font-medium">{{ $field->label }}@if ($field->required)<span aria-hidden="true" class="text-red-600"> *</span>@endif</h2>
                    @if ($field->help)
                        <p class="text-sm text-slate-600">{{ $field->help }}</p>
                    @endif
                    <p class="text-xs text-slate-500">{{ __('applications.wizard.file_constraints', [
                        'formats' => strtoupper(implode(', ', $field->file['extensions'])),
                        'size' => \App\Support\Applications\AnswerFormatter::megabytes($field->file['max_kb']),
                        'count' => $field->file['max_files'],
                    ]) }}</p>

                    @error('documents.'.$field->key)
                        <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror

                    <ul class="mt-3 space-y-2">
                        @foreach ($mine as $document)
                            <li class="flex items-center justify-between gap-2 rounded bg-slate-50 px-3 py-2 text-sm" wire:key="d-{{ $document->ulid }}">
                                <span class="truncate">✓ {{ $document->original_name }} <span class="text-slate-500">({{ AnswerFormatter::size($document->size) }})</span></span>
                                <button type="button" wire:click="removeDocument('{{ $document->ulid }}')" wire:confirm="{{ __('applications.wizard.remove_confirm') }}" class="text-red-700 underline">{{ __('applications.wizard.remove') }}</button>
                            </li>
                        @endforeach
                    </ul>

                    @if ($mine->count() < $field->file['max_files'])
                        <div class="mt-3" wire:ignore
                            x-data="linkChunkedUpload({
                                initUrl: @js(route('candidate.applications.documents.init', $application->ulid)),
                                field: @js($field->key),
                                chunkSize: {{ (int) config('link.applications.chunk_size_kb') * 1024 }},
                                csrf: @js(csrf_token()),
                                messages: { network: @js(__('applications.upload.network')), failed: @js(__('applications.upload.failed')) },
                                onComplete: () => $wire.$refresh(),
                            })">
                            <label class="inline-block cursor-pointer rounded border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">
                                {{ __('applications.upload.choose') }}
                                <input type="file" class="sr-only" x-on:change="pick($event)" @if ($field->file['max_files'] > 1) multiple @endif
                                    accept="{{ collect($field->file['extensions'])->map(fn ($e) => '.'.$e)->push(in_array('jpg', $field->file['extensions'], true) ? '.jpeg' : null)->filter()->implode(',') }}">
                            </label>
                            <p x-show="reconnecting" x-cloak class="mt-2 text-sm text-amber-800" role="status">{{ __('applications.upload.network') }}</p>
                            <template x-for="item in queue" :key="item.name">
                                <div class="mt-2 text-sm">
                                    <div class="flex justify-between"><span x-text="item.name" class="truncate"></span><span x-text="item.progress + ' %'"></span></div>
                                    <div class="h-2 w-full rounded bg-slate-200" role="progressbar" :aria-valuenow="item.progress" aria-valuemin="0" aria-valuemax="100">
                                        <div class="h-2 rounded bg-brand-600" :style="'width:' + item.progress + '%'"></div>
                                    </div>
                                    <p x-show="item.error" class="mt-1 break-words text-red-700" role="alert">
                                        <span x-text="item.error"></span>
                                        <button type="button" class="underline" x-on:click="retry(item)">{{ __('applications.upload.retry') }}</button>
                                    </p>
                                </div>
                            </template>
                        </div>

                        @if ($reusable->isNotEmpty())
                            <div class="mt-3 text-sm">
                                <p class="text-slate-600">{{ __('applications.wizard.reuse') }}</p>
                                <ul class="mt-1 space-y-1">
                                    @foreach ($reusable as $profileDocument)
                                        <li>
                                            <button type="button" class="text-brand-700 underline" wire:click="attachProfileDocument('{{ $field->key }}', '{{ $profileDocument->ulid }}')">
                                                {{ $profileDocument->label }} ({{ $profileDocument->original_name }})
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                </section>
            @endforeach
        @else
            <h2 class="mb-3 font-semibold">{{ __('applications.wizard.review_title') }}</h2>
            <dl class="space-y-3 text-sm">
                @foreach ($schema->inputFields() as $field)
                    @continue($field->document)
                    <div @if ($field->visibleIf) x-show="visible(@js($field->key))" x-cloak @endif>
                        <dt class="text-slate-600">{{ $field->label }}</dt>
                        <dd class="whitespace-pre-line">{{ AnswerFormatter::display($field, $answers[$field->key] ?? null) }}</dd>
                    </div>
                @endforeach
                @foreach ($schema->documentFields() as $field)
                    <div>
                        <dt class="text-slate-600">{{ $field->label }}</dt>
                        <dd>{{ $documents->where('field_key', $field->key)->pluck('original_name')->implode(', ') ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>

            <label class="mt-6 flex items-start gap-2 text-sm">
                <input type="checkbox" wire:model="consent" class="mt-0.5 h-5 w-5">
                <span>{{ __('applications.consent', ['organization' => $organization->name]) }}</span>
            </label>
            @error('consent')
                <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
            @enderror
            @if ($errors->any() && ! $errors->has('consent') && ! $errors->has('form'))
                <p class="mt-3 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ __('applications.wizard.fix_errors') }}</p>
            @endif
        @endif

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            @if ($step !== $steps[0])
                <button type="button" wire:click="previous" class="rounded border border-slate-300 px-4 py-2.5 text-sm">{{ __('applications.wizard.previous') }}</button>
            @else
                <span></span>
            @endif

            @if ($step === ApplicationSteps::REVIEW)
                <button type="button" wire:click="submit" wire:loading.attr="disabled" class="rounded bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">{{ $application->isSubmitted() ? __('applications.wizard.resubmit') : __('applications.wizard.submit') }}</span>
                    <span wire:loading wire:target="submit">{{ __('applications.wizard.sending') }}</span>
                </button>
            @else
                <button type="button" wire:click="next" wire:loading.attr="disabled" class="rounded bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="next">{{ __('applications.wizard.next') }}</span>
                    <span wire:loading wire:target="next">{{ __('applications.wizard.saving') }}</span>
                </button>
            @endif
        </div>
        <p class="mt-3 text-xs text-slate-500">{{ __('applications.wizard.autosave') }}</p>
    </div>
</div>
