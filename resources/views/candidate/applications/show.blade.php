@php
    use App\Http\Controllers\Candidate\DocumentDownloadController;
    use App\Support\Applications\AnswerFormatter;
    $tz = $organization->timezone;
    $posting = $application->jobPosting;
@endphp

<x-layouts.public :title="__('applications.show.title')" robots="noindex">
    <p class="text-sm text-slate-600">{{ $organization->name }}</p>
    <h1 class="text-xl font-semibold">{{ $posting->title }}</h1>
    <a href="{{ route('job-postings.show', $posting->slug) }}" class="text-sm text-brand-700 underline">{{ __('applications.show.view_offer') }}</a>

    <section class="mt-4 rounded-lg border border-slate-200 bg-white p-5">
        @if ($application->isDraft())
            <p class="text-sm">{{ __('applications.show.draft_notice') }}</p>
            @if ($posting->closes_at)
                <p class="text-sm text-slate-600">{{ __('applications.show.deadline', ['date' => $posting->closes_at->timezone($tz)->translatedFormat('j F Y, H:i')]) }}</p>
            @endif
        @else
            <dl class="grid gap-2 text-sm sm:grid-cols-[10rem_1fr]">
                <dt class="text-slate-600">{{ __('applications.show.reference') }}</dt>
                <dd class="font-mono font-medium">{{ $application->reference }}</dd>
                <dt class="text-slate-600">{{ __('applications.show.status') }}</dt>
                <dd>{{ $application->public_status?->getLabel() ?? $application->status->getLabel() }}</dd>
                @if ($application->submitted_at)
                    <dt class="text-slate-600">{{ __('applications.show.submitted_at') }}</dt>
                    <dd>{{ $application->submitted_at->timezone($tz)->translatedFormat('j F Y, H:i') }}</dd>
                @endif
            </dl>
        @endif

        <div class="mt-4 flex flex-wrap gap-3">
            @if ($canEdit)
                <a href="{{ route('candidate.applications.edit', $application->ulid) }}" class="rounded bg-brand-600 px-4 py-2 text-sm font-medium text-white">
                    {{ $application->isDraft() ? __('applications.show.continue') : __('applications.show.edit') }}
                </a>
            @endif
            @if ($canWithdraw)
                <form method="POST" action="{{ route('candidate.applications.withdraw', $application->ulid) }}" onsubmit="return confirm(@js(__('applications.show.withdraw_confirm')))">
                    @csrf
                    <button type="submit" class="rounded border border-red-300 px-4 py-2 text-sm text-red-800">{{ __('applications.show.withdraw') }}</button>
                </form>
            @endif
        </div>
    </section>

    @unless ($application->isDraft())
        <section class="mt-4 rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">{{ __('applications.show.answers') }}</h2>
            <dl class="mt-3 space-y-3 text-sm">
                @foreach ($schema->inputFields() as $field)
                    @continue($field->document || ! $schema->isVisible($field, $answers))
                    <div>
                        <dt class="text-slate-600">{{ $field->label }}</dt>
                        <dd class="whitespace-pre-line">{{ AnswerFormatter::display($field, $answers[$field->key] ?? null) }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($documents->isNotEmpty())
                <h2 class="mt-6 font-semibold">{{ __('applications.show.documents') }}</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($documents as $document)
                        <li>
                            {{ $document->original_name }} ({{ AnswerFormatter::size($document->size) }}) ·
                            <a href="{{ DocumentDownloadController::applicationUrl($document) }}" class="text-brand-700 underline">{{ __('applications.show.download') }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endunless
</x-layouts.public>
