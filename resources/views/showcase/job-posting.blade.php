@php
    use App\Enums\JobPostingStatus;
    use App\Enums\RequirementUsage;
    $timezone = $organization->timezone;
    $closes = $posting->closes_at?->timezone($timezone);
    $url = route('job-postings.show', $posting->slug);
    $summary = \Illuminate\Support\Str::limit(trim(strip_tags((string) ($posting->description ?: $posting->missions))), 180);
@endphp

<x-layouts.public :title="$posting->title.' · '.$organization->name" :robots="$posting->status === JobPostingStatus::Published ? 'index,follow' : 'noindex'">
    @push('head')
        <link rel="canonical" href="{{ $url }}">
        <meta name="description" content="{{ $summary }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $posting->title }} · {{ $organization->name }}">
        <meta property="og:description" content="{{ $summary }}">
        <meta property="og:url" content="{{ $url }}">
        @if ($posting->share_image_path)
            <meta property="og:image" content="{{ Storage::disk('public')->url($posting->share_image_path) }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta name="twitter:card" content="summary_large_image">
        @endif
        @if ($structuredData)
            <script type="application/ld+json">{!! $structuredData !!}</script>
        @endif
    @endpush

    <article>
        <p class="text-sm"><a href="{{ route('career-pages.show', $organization->slug) }}" class="text-brand-700 hover:underline">{{ $organization->name }}</a></p>
        <h1 class="text-2xl font-semibold">{{ $posting->title }}</h1>
        <p class="mt-1 text-xs text-slate-500">{{ $posting->reference }} · {{ $posting->type->getLabel() }}</p>

        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
            @if ($open)
                <p class="font-medium">{{ __('showcase.posting.closes', ['date' => $closes?->translatedFormat('l j F Y, H:i')]) }}</p>
                <p class="text-sm text-slate-600">{{ __('showcase.posting.closes_in', ['time' => $posting->closes_at?->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE, false, 2)]) }}</p>

                <div class="mt-3">
                    @if ($application?->isDraft())
                        <a href="{{ route('candidate.applications.edit', $application->ulid) }}" class="inline-block rounded bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('showcase.posting.continue') }}</a>
                    @elseif ($application)
                        <a href="{{ route('candidate.applications.show', $application->ulid) }}" class="inline-block rounded bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('showcase.posting.view_application') }}</a>
                    @else
                        <a href="{{ route('job-postings.apply', $posting->slug) }}" rel="nofollow" class="inline-block rounded bg-brand-600 px-5 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('showcase.posting.apply') }}</a>
                        @guest
                            <p class="mt-2 text-xs text-slate-600">{{ __('showcase.posting.login_hint') }}</p>
                        @endguest
                    @endif
                </div>
            @elseif ($posting->status === JobPostingStatus::Suspended)
                <p class="font-medium text-amber-800">{{ __('showcase.posting.suspended') }}</p>
            @elseif ($posting->status === JobPostingStatus::Published && $posting->opens_at?->isFuture())
                <p class="font-medium">{{ __('showcase.posting.not_open_yet', ['date' => $posting->opens_at->timezone($timezone)->translatedFormat('j F Y, H:i')]) }}</p>
            @else
                <p class="font-medium text-slate-700">{{ __('showcase.posting.closed') }}</p>
                @if ($application)
                    <a href="{{ route('candidate.applications.show', $application->ulid) }}" class="mt-2 inline-block text-sm underline">{{ __('showcase.posting.view_application') }}</a>
                @endif
            @endif
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            @if ($posting->location)
                <div><dt class="text-slate-600">{{ __('showcase.posting.location') }}</dt><dd class="font-medium">{{ $posting->location }}</dd></div>
            @endif
            @if ($posting->contract_type)
                <div><dt class="text-slate-600">{{ __('showcase.posting.contract') }}</dt><dd class="font-medium">{{ $posting->contract_type->getLabel() }}</dd></div>
            @endif
            @if ($posting->education_level)
                <div><dt class="text-slate-600">{{ __('showcase.posting.level') }}</dt><dd class="font-medium">{{ $posting->education_level->getLabel() }}</dd></div>
            @endif
            @if ($posting->places)
                <div><dt class="text-slate-600">{{ __('showcase.posting.positions') }}</dt><dd class="font-medium">{{ $posting->places }}</dd></div>
            @endif
        </dl>

        @if ($posting->description)
            <div class="prose-sm mt-6 space-y-3 leading-relaxed [&_a]:text-brand-700 [&_a]:underline [&_li]:ml-5 [&_ol]:list-decimal [&_ul]:list-disc">{!! $posting->description !!}</div>
        @endif
        @if ($posting->missions)
            <div class="mt-4 whitespace-pre-line leading-relaxed">{{ $posting->missions }}</div>
        @endif

        @php($visibleRequirements = $requirements->reject(fn ($r) => $r->usage === RequirementUsage::Informative && blank($r->label)))
        @if ($visibleRequirements->isNotEmpty())
            <h2 class="mt-6 text-lg font-semibold">{{ __('showcase.posting.requirements') }}</h2>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($visibleRequirements as $requirement)
                    <li class="rounded border border-slate-200 bg-white p-3">
                        <span class="font-medium">{{ $requirement->label }}</span>
                        <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ $requirement->usage->getLabel() }}</span>
                        @if ($requirement->description)
                            <p class="mt-1 text-slate-600">{{ $requirement->description }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($documents !== [])
            <h2 class="mt-6 text-lg font-semibold">{{ __('showcase.posting.documents') }}</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach ($documents as $field)
                    <li>
                        {{ $field->label }}@if ($field->required)<span aria-hidden="true" class="text-red-600"> *</span>@endif
                        <span class="text-slate-600">({{ __('showcase.posting.document_constraints', ['formats' => strtoupper(implode(', ', $field->file['extensions'])), 'size' => \App\Support\Applications\AnswerFormatter::megabytes($field->file['max_kb'])]) }})</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="mt-6 text-sm">
            <a href="https://wa.me/?text={{ rawurlencode($posting->title.' · '.$organization->name.' '.$url.'?source=whatsapp') }}" rel="noopener" target="_blank" class="text-brand-700 underline">{{ __('showcase.posting.share_whatsapp') }}</a>
        </p>
        @if ($posting->published_at)
            <p class="mt-2 text-xs text-slate-500">{{ __('showcase.posting.published', ['date' => $posting->published_at->timezone($timezone)->translatedFormat('j F Y')]) }}</p>
        @endif
    </article>
</x-layouts.public>
