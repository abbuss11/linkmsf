@props(['field', 'errors', 'profileValue' => null])

{{--
    One field of a dynamic form, rendered without Filament so that candidate
    pages stay light on slow phones. Values are bound to Alpine's `answers`
    (x-model); the server validates everything again (RulesBuilder).
--}}
@php
    /** @var \App\Forms\Dynamic\FieldDefinition $field */
    $type = $field->type;
    $key = $field->key;
    $id = 'answer-'.$key;
    $error = $key ? $errors->first('answers.'.$key) : null;
    $describedBy = trim(($field->help ? $id.'-help ' : '').($error ? $id.'-error' : ''));
    $base = 'block w-full rounded border border-slate-300 bg-white px-3 py-2 text-base focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/30';
@endphp

@if ($type === \App\Enums\FormFieldType::Section)
    <h2 class="mt-6 mb-2 text-lg font-semibold">{{ $field->label }}</h2>
    @if ($field->help)
        <p class="mb-4 text-sm text-slate-600">{{ $field->help }}</p>
    @endif
@elseif ($type === \App\Enums\FormFieldType::HelpText)
    <p class="mb-4 rounded bg-slate-100 p-3 text-sm text-slate-700">{{ $field->help }}</p>
@else
    <div class="mb-5" @if ($field->visibleIf) x-show="visible(@js($key))" x-cloak @endif>
        @if (in_array($type, [\App\Enums\FormFieldType::Radio, \App\Enums\FormFieldType::YesNo, \App\Enums\FormFieldType::CheckboxList], true))
            <fieldset @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
                <legend class="mb-1 text-sm font-medium">
                    {{ $field->label }}@if ($field->required)<span aria-hidden="true" class="text-red-600"> *</span>@endif
                </legend>
                @php
                    $options = $type === \App\Enums\FormFieldType::YesNo
                        ? ['yes' => __('forms.yes'), 'no' => __('forms.no')]
                        : $field->optionsForSelect();
                @endphp
                <div class="{{ $type === \App\Enums\FormFieldType::YesNo ? 'flex gap-6' : 'space-y-2' }}">
                    @foreach ($options as $value => $label)
                        <label class="flex items-center gap-2 text-base">
                            @if ($type === \App\Enums\FormFieldType::CheckboxList)
                                <input type="checkbox" name="answers[{{ $key }}][]" value="{{ $value }}" x-model="answers[@js($key)]" class="h-5 w-5">
                            @else
                                <input type="radio" name="answers[{{ $key }}]" value="{{ $value }}" x-model="answers[@js($key)]" class="h-5 w-5">
                            @endif
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @elseif ($type === \App\Enums\FormFieldType::Checkbox)
            <label class="flex items-start gap-2 text-base">
                <input type="checkbox" id="{{ $id }}" name="answers[{{ $key }}]" value="1" x-model="answers[@js($key)]" class="mt-1 h-5 w-5"
                    @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
                <span>{{ $field->label }}@if ($field->required)<span aria-hidden="true" class="text-red-600"> *</span>@endif</span>
            </label>
        @else
            <label for="{{ $id }}" class="mb-1 block text-sm font-medium">
                {{ $field->label }}@if ($field->required)<span aria-hidden="true" class="text-red-600"> *</span>@endif
            </label>

            @switch($type)
                @case(\App\Enums\FormFieldType::LongText)
                    <textarea id="{{ $id }}" name="answers[{{ $key }}]" rows="5" x-model="answers[@js($key)]" class="{{ $base }}"
                        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif></textarea>
                    @break
                @case(\App\Enums\FormFieldType::Select)
                    <select id="{{ $id }}" name="answers[{{ $key }}]" x-model="answers[@js($key)]" class="{{ $base }}"
                        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif>
                        <option value="">{{ __('forms.choose') }}</option>
                        @foreach ($field->optionsForSelect() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @break
                @default
                    @php
                        $inputType = match ($type) {
                            \App\Enums\FormFieldType::Email => 'email',
                            \App\Enums\FormFieldType::Phone => 'tel',
                            \App\Enums\FormFieldType::Number => 'number',
                            \App\Enums\FormFieldType::Date => 'date',
                            \App\Enums\FormFieldType::Url => 'url',
                            default => 'text',
                        };
                        $inputMode = match ($type) {
                            \App\Enums\FormFieldType::Phone => 'tel',
                            \App\Enums\FormFieldType::Number => 'decimal',
                            \App\Enums\FormFieldType::Email => 'email',
                            default => null,
                        };
                    @endphp
                    <input id="{{ $id }}" type="{{ $inputType }}" name="answers[{{ $key }}]" x-model="answers[@js($key)]" class="{{ $base }}"
                        @if ($inputMode) inputmode="{{ $inputMode }}" @endif
                        @if ($type === \App\Enums\FormFieldType::Phone) placeholder="+227 90 00 00 00" @endif
                        @if ($type === \App\Enums\FormFieldType::Number) step="any" @endif
                        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif>
            @endswitch
        @endif

        @if ($field->help)
            <p id="{{ $id }}-help" class="mt-1 text-xs text-slate-600">{{ $field->help }}</p>
        @endif
        @if ($error)
            <p id="{{ $id }}-error" class="mt-1 text-sm text-red-700" role="alert">{{ $error }}</p>
        @endif
    </div>
@endif
