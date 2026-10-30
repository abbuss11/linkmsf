<x-layouts.public :title="__('showcase.career.title', ['organization' => $organization->name])">
    @push('head')
        <link rel="canonical" href="{{ route('career-pages.show', $organization->slug) }}">
        <meta property="og:title" content="{{ __('showcase.career.title', ['organization' => $organization->name]) }}">
        @if ($organization->logo_path)
            <meta property="og:image" content="{{ Storage::disk('public')->url($organization->logo_path) }}">
        @endif
    @endpush

    <header class="flex items-center gap-4">
        @if ($organization->logo_path)
            <img src="{{ Storage::disk('public')->url($organization->logo_path) }}" alt="" width="64" height="64" loading="lazy" class="h-16 w-16 rounded object-contain">
        @endif
        <div>
            <h1 class="text-2xl font-semibold">{{ $organization->name }}</h1>
            @if ($organization->website)
                <a href="{{ $organization->website }}" rel="noopener nofollow" target="_blank" class="text-sm text-brand-700 underline">{{ __('showcase.career.website') }}</a>
            @endif
        </div>
    </header>

    @if ($organization->about)
        <div class="mt-4 space-y-3 leading-relaxed [&_a]:text-brand-700 [&_a]:underline [&_li]:ml-5 [&_ul]:list-disc">{!! $organization->about !!}</div>
    @endif

    <h2 class="mt-6 text-lg font-semibold">{{ __('showcase.career.offers') }}</h2>
    @if ($postings->isEmpty())
        <p class="mt-2 rounded-lg border border-slate-200 bg-white p-4 text-sm">{{ __('showcase.career.empty') }}</p>
    @else
        <ul class="mt-3 space-y-3">
            @foreach ($postings as $posting)
                <x-showcase.posting-card :posting="$posting" />
            @endforeach
        </ul>
    @endif
</x-layouts.public>
