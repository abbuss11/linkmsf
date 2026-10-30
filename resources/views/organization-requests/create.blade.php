<x-layouts.public :title="__('organization_requests.page.title')">
    <x-card :title="__('organization_requests.page.title')" class="max-w-xl">
        <p class="mb-4 text-sm text-slate-700">{{ __('organization_requests.page.intro') }}</p>

        <form method="POST" action="{{ route('organization-requests.store') }}" novalidate>
            @csrf
            <x-honeypot />
            <x-field name="organization_name" :label="__('organization_requests.fields.organization_name')" autocomplete="organization" required maxlength="255" />
            <x-field name="contact_name" :label="__('organization_requests.fields.contact_name')" autocomplete="name" required maxlength="255" />
            <x-field name="email" type="email" :label="__('organization_requests.fields.email')" autocomplete="email" required maxlength="255" inputmode="email" />
            <x-field name="phone" type="tel" :label="__('organization_requests.fields.phone')" autocomplete="tel" value="+227 " maxlength="32" inputmode="tel" :help="__('organization_requests.fields.phone_help')" />

            <div class="mb-4">
                <label for="field-country" class="mb-1 block text-sm font-medium">{{ __('organization_requests.fields.country') }} <span aria-hidden="true" class="text-red-600">*</span></label>
                <select id="field-country" name="country" required class="block w-full rounded border border-slate-300 bg-white px-3 py-2 text-base">
                    @foreach (__('countries') as $code => $label)
                        <option value="{{ $code }}" @selected(old('country', 'NE') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('country')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <x-field name="message" type="textarea" :label="__('organization_requests.fields.message')" maxlength="2000" />
            <x-turnstile />
            <x-button>{{ __('organization_requests.page.submit') }}</x-button>
        </form>
    </x-card>
</x-layouts.public>
