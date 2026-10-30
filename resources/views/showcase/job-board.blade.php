@php
    use App\Enums\EducationLevel;
    use App\Enums\JobPostingType;
    $select = 'mt-1 block w-full rounded border border-slate-300 bg-white px-2 py-2 text-base';
@endphp

<x-layouts.public :title="__('showcase.board.title')">
    @push('head')
        <meta name="description" content="{{ __('showcase.board.description') }}">
        <link rel="canonical" href="{{ route('job-postings.index') }}">
    @endpush

    <h1 class="text-xl font-semibold">{{ __('showcase.board.heading') }}</h1>

    <form method="GET" action="{{ route('job-postings.index') }}" class="mt-4 rounded-lg border border-slate-200 bg-white p-4" role="search">
        <label class="block text-sm font-medium" for="q">{{ __('showcase.board.search') }}</label>
        <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="{{ __('showcase.board.search_placeholder') }}"
            class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-base">

        <details class="mt-3" @if (count(array_diff_key($filters, ['q' => 1]))) open @endif>
            <summary class="cursor-pointer text-sm text-brand-700">{{ __('showcase.board.filter') }}</summary>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="text-sm">{{ __('showcase.board.type') }}
                    <select name="type" class="{{ $select }}">
                        <option value="">{{ __('showcase.board.all') }}</option>
                        @foreach (JobPostingType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ $type->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">{{ __('showcase.board.organization') }}
                    <select name="organisation" class="{{ $select }}">
                        <option value="">{{ __('showcase.board.all') }}</option>
                        @foreach ($organizations as $slug => $name)
                            <option value="{{ $slug }}" @selected(($filters['organisation'] ?? null) === $slug)>{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">{{ __('showcase.board.location') }}
                    <input name="lieu" type="text" value="{{ $filters['lieu'] ?? '' }}" maxlength="100" class="{{ $select }}">
                </label>
                <label class="text-sm">{{ __('showcase.board.level') }}
                    <select name="niveau" class="{{ $select }}">
                        <option value="">{{ __('showcase.board.all') }}</option>
                        @foreach (EducationLevel::cases() as $level)
                            <option value="{{ $level->value }}" @selected(($filters['niveau'] ?? null) === $level->value)>{{ $level->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">{{ __('showcase.board.closing') }}
                    <select name="cloture" class="{{ $select }}">
                        <option value="">{{ __('showcase.board.all') }}</option>
                        <option value="semaine" @selected(($filters['cloture'] ?? null) === 'semaine')>{{ __('showcase.board.closing_week') }}</option>
                        <option value="mois" @selected(($filters['cloture'] ?? null) === 'mois')>{{ __('showcase.board.closing_month') }}</option>
                    </select>
                </label>
                <label class="text-sm">{{ __('showcase.board.sort') }}
                    <select name="tri" class="{{ $select }}">
                        <option value="recentes">{{ __('showcase.board.sort_recent') }}</option>
                        <option value="cloture" @selected(($filters['tri'] ?? null) === 'cloture')>{{ __('showcase.board.sort_closing') }}</option>
                    </select>
                </label>
            </div>
        </details>

        <div class="mt-3 flex items-center gap-4">
            <x-button>{{ __('showcase.board.filter') }}</x-button>
            @if ($filters !== [])
                <a href="{{ route('job-postings.index') }}" class="text-sm underline">{{ __('showcase.board.reset') }}</a>
            @endif
        </div>
    </form>

    <p class="mt-4 text-sm text-slate-600" role="status">{{ trans_choice('showcase.board.count', $postings->total(), ['count' => $postings->total()]) }}</p>

    @if ($postings->isEmpty())
        <p class="mt-4 rounded-lg border border-slate-200 bg-white p-4 text-sm">{{ __('showcase.board.empty') }}</p>
    @else
        <ul class="mt-3 space-y-3">
            @foreach ($postings as $posting)
                <x-showcase.posting-card :posting="$posting" :organization-name="$posting->organization->name" />
            @endforeach
        </ul>
        <div class="mt-6">{{ $postings->links() }}</div>
    @endif
</x-layouts.public>
