{{-- Wizard step 5: summary and pre-publication checks. Inline styles: these
     classes are not part of Filament's compiled stylesheet. --}}
@php
    $box = 'border-radius:.5rem;padding:.75rem 1rem;font-size:.875rem;border:1px solid;';
@endphp
<div style="display:flex;flex-direction:column;gap:1.25rem">
    <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:.75rem 1.5rem;font-size:.875rem">
        @foreach ([
            __('job_postings.fields.reference') => $posting->reference,
            __('job_postings.fields.type') => $posting->type->getLabel(),
            __('job_postings.fields.closes_at') => ($posting->closes_at?->timezone($organization->timezone)->translatedFormat('j F Y à H:i') ?? '—').' ('.$organization->timezone.')',
            __('job_postings.review.questions') => count($schema->inputFields()) - count($schema->documentFields()),
            __('job_postings.review.documents') => count($schema->documentFields()),
            __('job_postings.review.requirements') => $requirementsCount,
            __('job_postings.review.form_version') => $currentVersion ? 'v'.$currentVersion : __('job_postings.review.not_published'),
        ] as $label => $value)
            <div>
                <dt style="color:rgb(107 114 128)">{{ $label }}</dt>
                <dd style="font-weight:500">{{ $value }}</dd>
            </div>
        @endforeach
        <div>
            <dt style="color:rgb(107 114 128)">{{ __('job_postings.fields.status') }}</dt>
            <dd><x-filament::badge :color="$posting->status->getColor()" style="display:inline-flex">{{ $posting->status->getLabel() }}</x-filament::badge></dd>
        </div>
        <div style="grid-column:1/-1">
            <dt style="color:rgb(107 114 128)">{{ __('job_postings.review.public_url') }}</dt>
            <dd style="word-break:break-all">{{ url('/offres/'.$posting->slug) }}</dd>
        </div>
    </dl>

    @if ($hasUnpublishedChanges)
        <div style="{{ $box }}border-color:#fcd34d;background:#fffbeb;color:#92400e">{{ __('job_postings.review.unpublished_changes') }}</div>
    @endif

    @if ($report->blocking)
        <div role="alert" style="{{ $box }}border-color:#fca5a5;background:#fef2f2;color:#991b1b">
            <p style="font-weight:600">{{ __('job_postings.review.blocking') }}</p>
            <ul style="margin:.25rem 0 0 1.25rem;list-style:disc">
                @foreach ($report->blocking as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        </div>
    @elseif ($posting->status === \App\Enums\JobPostingStatus::Draft)
        <div style="{{ $box }}border-color:#86efac;background:#f0fdf4;color:#166534">{{ __('job_postings.review.ready') }}</div>
    @endif

    @if ($report->warnings)
        <div style="{{ $box }}border-color:#e5e7eb">
            <p style="font-weight:600">{{ __('job_postings.review.warnings') }}</p>
            <ul style="margin:.25rem 0 0 1.25rem;list-style:disc">
                @foreach ($report->warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p style="font-size:.875rem;color:rgb(107 114 128)">{{ __('job_postings.review.actions_hint') }}</p>
</div>
