@props(['posting', 'organizationName' => null])

<li class="rounded-lg border border-slate-200 bg-white p-4">
    <a href="{{ route('job-postings.show', $posting->slug) }}" class="font-semibold text-brand-700 hover:underline">{{ $posting->title }}</a>
    @if ($organizationName)
        <p class="text-sm text-slate-700">{{ $organizationName }}</p>
    @endif
    <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600">
        <span>{{ $posting->type->getLabel() }}</span>
        @if ($posting->location)
            <span>{{ $posting->location }}</span>
        @endif
        @if ($posting->closes_at)
            <span>{{ __('showcase.posting.closes', ['date' => $posting->closes_at->timezone(config('link.display_timezone'))->translatedFormat('j M Y, H:i')]) }}</span>
        @endif
    </p>
</li>
