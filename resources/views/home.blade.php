<x-layouts.public>
    <section class="py-6 text-center">
        <h1 class="text-2xl font-bold sm:text-3xl">{{ __('public.home.title') }}</h1>
        <p class="mx-auto mt-3 max-w-xl text-slate-700">{{ __('public.home.subtitle') }}</p>
    </section>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">{{ __('public.home.candidates_title') }}</h2>
            <p class="mt-2 text-sm text-slate-700">{{ __('public.home.candidates_text') }}</p>
            <a href="{{ route('register') }}" class="mt-4 inline-block rounded bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">{{ __('public.home.candidates_cta') }}</a>
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-semibold">{{ __('public.home.organizations_title') }}</h2>
            <p class="mt-2 text-sm text-slate-700">{{ __('public.home.organizations_text') }}</p>
            <a href="{{ route('organization-requests.create') }}" class="mt-4 inline-block rounded border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">{{ __('public.home.organizations_cta') }}</a>
        </section>
    </div>
</x-layouts.public>
