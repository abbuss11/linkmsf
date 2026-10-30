@php
    use App\Enums\CandidateDocumentType;
    use App\Http\Controllers\Candidate\DocumentDownloadController;
    use App\Support\Applications\AnswerFormatter;
@endphp

<x-layouts.public :title="__('candidate.documents.title')" robots="noindex">
    <h1 class="text-xl font-semibold">{{ __('candidate.documents.title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('candidate.documents.intro') }}</p>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        @if ($documents->isEmpty())
            <p class="text-sm text-slate-600">{{ __('candidate.documents.empty') }}</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($documents as $document)
                    <li class="py-3 text-sm">
                        <p class="font-medium">
                            {{ $document->label }}
                            <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-normal">{{ $document->type->getLabel() }}</span>
                            @if ($document->id === $defaultCvId)
                                <span class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-xs font-normal text-brand-800">{{ __('candidate.documents.default_cv') }}</span>
                            @endif
                        </p>
                        <p class="text-slate-600">{{ $document->original_name }} · {{ AnswerFormatter::size($document->size) }}</p>
                        <div class="mt-1 flex flex-wrap gap-4">
                            <a href="{{ DocumentDownloadController::profileUrl($document) }}" class="text-brand-700 underline">{{ __('candidate.documents.download') }}</a>
                            @if ($document->type === CandidateDocumentType::Cv && $document->id !== $defaultCvId)
                                <form method="POST" action="{{ route('candidate.documents.default', $document->ulid) }}">
                                    @csrf
                                    <button type="submit" class="text-brand-700 underline">{{ __('candidate.documents.make_default') }}</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('candidate.documents.destroy', $document->ulid) }}" onsubmit="return confirm(@js(__('candidate.documents.delete_confirm')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-700 underline">{{ __('candidate.documents.delete') }}</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="mb-4 font-semibold">{{ __('candidate.documents.upload') }}</h2>
        <form method="POST" action="{{ route('candidate.documents.store') }}" enctype="multipart/form-data">
            @csrf
            <x-field name="file" type="file" :label="__('candidate.documents.file')" required accept=".pdf,.jpg,.jpeg,.png,.docx" />
            <div class="mb-4">
                <label for="field-type" class="mb-1 block text-sm font-medium">{{ __('candidate.documents.type') }}</label>
                <select id="field-type" name="type" class="block w-full rounded border border-slate-300 bg-white px-3 py-2 text-base">
                    @foreach (CandidateDocumentType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <x-field name="label" :label="__('candidate.documents.label')" />
            <x-button>{{ __('candidate.documents.submit') }}</x-button>
        </form>
    </section>
</x-layouts.public>
