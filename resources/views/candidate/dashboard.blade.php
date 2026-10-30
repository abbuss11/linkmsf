@php($tz = config('link.display_timezone'))

<x-layouts.public :title="__('candidate.dashboard.title')" robots="noindex">
    <h1 class="text-xl font-semibold">{{ __('candidate.dashboard.greeting', ['name' => $user->name]) }}</h1>

    @if ($drafts->isNotEmpty())
        <section class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5">
            <h2 class="font-semibold">{{ __('candidate.dashboard.drafts_title') }}</h2>
            <ul class="mt-3 space-y-3">
                @foreach ($drafts as $application)
                    <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span>
                            <span class="font-medium">{{ $application->jobPosting->title }}</span>
                            <span class="text-slate-600">· {{ $organizations[$application->organization_id] ?? '' }}</span>
                            @if ($application->jobPosting->closes_at)
                                <span class="block text-xs text-slate-600">{{ __('candidate.dashboard.deadline', ['date' => $application->jobPosting->closes_at->timezone($tz)->translatedFormat('j M Y, H:i')]) }}</span>
                            @endif
                        </span>
                        @if ($application->canStillBeChanged())
                            <a href="{{ route('candidate.applications.edit', $application->ulid) }}" class="rounded bg-brand-600 px-3 py-1.5 font-medium text-white">{{ __('candidate.dashboard.continue') }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-4 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('candidate.dashboard.applications_title') }}</h2>
        @if ($sent->isEmpty())
            <p class="mt-2 text-sm text-slate-700">{{ __('candidate.dashboard.applications_empty') }}</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($sent as $application)
                    <li class="py-2 text-sm">
                        <a href="{{ route('candidate.applications.show', $application->ulid) }}" class="font-medium text-brand-700 hover:underline">{{ $application->jobPosting->title }}</a>
                        <span class="text-slate-600">· {{ $organizations[$application->organization_id] ?? '' }}</span>
                        <span class="mt-0.5 block text-xs text-slate-600">
                            {{ $application->reference }} ·
                            <span class="rounded bg-slate-100 px-1.5 py-0.5">{{ $application->public_status?->getLabel() ?? $application->status->getLabel() }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
        <a href="{{ route('job-postings.index') }}" class="mt-3 inline-block text-sm text-brand-700 underline">{{ __('candidate.dashboard.browse') }}</a>
    </section>

    <section class="mt-4 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('candidate.dashboard.profile_title') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('candidate.dashboard.profile_hint') }}</p>
        <p class="mt-3 flex flex-wrap gap-4 text-sm">
            <a href="{{ route('candidate.profile') }}" class="text-brand-700 underline">{{ __('candidate.dashboard.profile_link') }}</a>
            <a href="{{ route('candidate.documents') }}" class="text-brand-700 underline">{{ __('candidate.dashboard.documents_link') }}</a>
        </p>
    </section>
</x-layouts.public>
