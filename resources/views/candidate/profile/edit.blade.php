@php
    use App\Enums\EducationLevel;
    use App\Enums\Gender;
    $select = 'block w-full rounded border border-slate-300 bg-white px-3 py-2 text-base';
@endphp

<x-layouts.public :title="__('candidate.profile.title')" robots="noindex">
    <h1 class="text-xl font-semibold">{{ __('candidate.profile.title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('candidate.profile.intro') }}</p>

    <form method="POST" action="{{ route('candidate.profile.update') }}" class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        @csrf
        @method('PUT')
        <h2 class="mb-4 font-semibold">{{ __('candidate.profile.identity') }}</h2>

        <x-field name="name" :label="__('candidate.fields.name')" :value="$user->name" required autocomplete="name" />
        <x-field name="phone" type="tel" :label="__('candidate.profile.fields.phone')" :value="$profile->phone" autocomplete="tel" />
        <x-field name="birth_date" type="date" :label="__('candidate.profile.fields.birth_date')" :value="$profile->birth_date?->format('Y-m-d')" autocomplete="bday" />

        <div class="mb-4">
            <label for="field-gender" class="mb-1 block text-sm font-medium">{{ __('candidate.profile.fields.gender') }}</label>
            <select id="field-gender" name="gender" class="{{ $select }}">
                <option value=""></option>
                @foreach (Gender::cases() as $gender)
                    <option value="{{ $gender->value }}" @selected(old('gender', $profile->gender?->value) === $gender->value)>{{ $gender->getLabel() }}</option>
                @endforeach
            </select>
        </div>

        <x-field name="city" :label="__('candidate.profile.fields.city')" :value="$profile->city" autocomplete="address-level2" />

        <div class="mb-4">
            <label for="field-country" class="mb-1 block text-sm font-medium">{{ __('candidate.profile.fields.country') }}</label>
            <select id="field-country" name="country" class="{{ $select }}">
                <option value=""></option>
                @foreach (__('countries') as $code => $label)
                    <option value="{{ $code }}" @selected(old('country', $profile->country) === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-4">
            <label for="field-education_level" class="mb-1 block text-sm font-medium">{{ __('candidate.profile.fields.education_level') }}</label>
            <select id="field-education_level" name="education_level" class="{{ $select }}">
                <option value=""></option>
                @foreach (EducationLevel::cases() as $level)
                    <option value="{{ $level->value }}" @selected(old('education_level', $profile->education_level?->value) === $level->value)>{{ $level->getLabel() }}</option>
                @endforeach
            </select>
            @error('education_level')<p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
        </div>

        <x-field name="diploma" :label="__('candidate.profile.fields.diploma')" :value="$profile->diploma" />
        <x-field name="years_of_experience" type="number" min="0" max="60" inputmode="numeric" :label="__('candidate.profile.fields.years_of_experience')" :value="$profile->years_of_experience" />
        <x-field name="languages" :label="__('candidate.profile.fields.languages')" :value="implode(', ', $profile->languages ?? [])" />
        <x-field name="headline" :label="__('candidate.profile.fields.headline')" :value="$profile->headline" />
        <x-field name="skills" :label="__('candidate.profile.fields.skills')" :value="implode(', ', $profile->skills ?? [])" />
        <x-field name="summary" type="textarea" :label="__('candidate.profile.fields.summary')" :value="$profile->summary" />

        <x-button>{{ __('candidate.profile.save') }}</x-button>
    </form>

    <section id="formations" class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('candidate.profile.educations') }}</h2>
        @forelse ($educations as $education)
            <div class="mt-3 flex items-start justify-between gap-3 border-b border-slate-100 pb-3 text-sm">
                <div>
                    <p class="font-medium">{{ $education->degree }}@if ($education->field) · {{ $education->field }}@endif</p>
                    <p class="text-slate-600">{{ $education->institution }} @if ($education->start_year || $education->end_year)({{ $education->start_year }}–{{ $education->end_year }})@endif</p>
                </div>
                <form method="POST" action="{{ route('candidate.educations.destroy', $education->id) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-700 underline">{{ __('candidate.profile.delete') }}</button>
                </form>
            </div>
        @empty
            <p class="mt-2 text-sm text-slate-600">{{ __('candidate.profile.education.empty') }}</p>
        @endforelse

        <details class="mt-4" @if ($errors->hasAny(['degree', 'institution', 'start_year', 'end_year'])) open @endif>
            <summary class="cursor-pointer text-sm text-brand-700">{{ __('candidate.profile.add') }}</summary>
            <form method="POST" action="{{ route('candidate.educations.store') }}" class="mt-3">
                @csrf
                <x-field name="degree" :label="__('candidate.profile.education.degree')" required />
                <x-field name="institution" :label="__('candidate.profile.education.institution')" required />
                <x-field name="field" :label="__('candidate.profile.education.field')" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="start_year" type="number" inputmode="numeric" min="1950" max="2100" :label="__('candidate.profile.education.start_year')" />
                    <x-field name="end_year" type="number" inputmode="numeric" min="1950" max="2100" :label="__('candidate.profile.education.end_year')" />
                </div>
                <x-button>{{ __('candidate.profile.add') }}</x-button>
            </form>
        </details>
    </section>

    <section id="experiences" class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('candidate.profile.experiences') }}</h2>
        @forelse ($experiences as $experience)
            <div class="mt-3 flex items-start justify-between gap-3 border-b border-slate-100 pb-3 text-sm">
                <div>
                    <p class="font-medium">{{ $experience->title }} · {{ $experience->employer }}</p>
                    <p class="text-slate-600">
                        {{ $experience->start_date?->translatedFormat('M Y') }} – {{ $experience->is_current ? __('candidate.profile.experience.current') : $experience->end_date?->translatedFormat('M Y') }}
                        @if ($experience->location) · {{ $experience->location }}@endif
                    </p>
                    @if ($experience->description)
                        <p class="mt-1 whitespace-pre-line">{{ $experience->description }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('candidate.experiences.destroy', $experience->id) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-700 underline">{{ __('candidate.profile.delete') }}</button>
                </form>
            </div>
        @empty
            <p class="mt-2 text-sm text-slate-600">{{ __('candidate.profile.experience.empty') }}</p>
        @endforelse

        <details class="mt-4" @if ($errors->hasAny(['title', 'employer', 'start_date', 'end_date'])) open @endif>
            <summary class="cursor-pointer text-sm text-brand-700">{{ __('candidate.profile.add') }}</summary>
            <form method="POST" action="{{ route('candidate.experiences.store') }}" class="mt-3">
                @csrf
                <x-field name="title" :label="__('candidate.profile.experience.title')" required />
                <x-field name="employer" :label="__('candidate.profile.experience.employer')" required />
                <x-field name="location" :label="__('candidate.profile.experience.location')" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="start_date" type="date" :label="__('candidate.profile.experience.start_date')" />
                    <x-field name="end_date" type="date" :label="__('candidate.profile.experience.end_date')" />
                </div>
                <label class="mb-4 flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_current" value="1" class="h-5 w-5" @checked(old('is_current'))>
                    {{ __('candidate.profile.experience.is_current') }}
                </label>
                <x-field name="description" type="textarea" :label="__('candidate.profile.experience.description')" />
                <x-button>{{ __('candidate.profile.add') }}</x-button>
            </form>
        </details>
    </section>
</x-layouts.public>
