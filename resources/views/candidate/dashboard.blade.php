<x-layouts.public :title="__('candidate.dashboard.title')" robots="noindex">
    <h1 class="text-xl font-semibold">{{ __('candidate.dashboard.greeting', ['name' => $user->name]) }}</h1>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('candidate.dashboard.applications_title') }}</h2>
        <p class="mt-2 text-sm text-slate-700">{{ __('candidate.dashboard.applications_empty') }}</p>
    </section>

    <section class="mt-4 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('candidate.dashboard.profile_title') }}</h2>
        <dl class="mt-2 grid gap-1 text-sm sm:grid-cols-[10rem_1fr]">
            <dt class="text-slate-600">{{ __('candidate.fields.name') }}</dt>
            <dd>{{ $user->name }}</dd>
            <dt class="text-slate-600">{{ __('candidate.fields.email') }}</dt>
            <dd>{{ $user->email }}</dd>
        </dl>
        <p class="mt-3 text-sm text-slate-600">{{ __('candidate.dashboard.profile_soon') }}</p>
    </section>
</x-layouts.public>
