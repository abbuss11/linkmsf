<x-layouts.public :title="__('applications.confirmation.title')" robots="noindex">
    <section class="rounded-lg border border-emerald-200 bg-white p-6 text-center">
        <p class="text-4xl" aria-hidden="true">✓</p>
        <h1 class="mt-2 text-xl font-semibold">{{ __('applications.confirmation.heading') }}</h1>
        <p class="mt-3 text-sm">{{ __('applications.confirmation.body', [
            'title' => $application->jobPosting->title,
            'organization' => $organization->name,
            'date' => $application->submitted_at?->timezone($organization->timezone)->translatedFormat('j F Y à H:i'),
        ]) }}</p>
        <p class="mt-3 font-mono text-lg font-semibold">{{ __('applications.confirmation.reference', ['reference' => $application->reference]) }}</p>
        <p class="mt-2 text-sm text-slate-600">{{ __('applications.confirmation.email') }}</p>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
            <a href="{{ route('candidate.applications.show', $application->ulid) }}" class="rounded bg-brand-600 px-4 py-2 text-sm font-medium text-white">{{ __('applications.confirmation.follow') }}</a>
            <a href="{{ route('job-postings.index') }}" class="rounded border border-slate-300 px-4 py-2 text-sm">{{ __('applications.confirmation.more') }}</a>
        </div>
    </section>
</x-layouts.public>
