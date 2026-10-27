<x-layouts.public :title="__('candidate.register.title')" robots="noindex">
    <x-card :title="__('candidate.register.title')">
        <p class="mb-4 text-sm text-slate-700">{{ __('candidate.register.intro') }}</p>

        <form method="POST" action="{{ route('register') }}" novalidate>
            @csrf
            <x-honeypot />
            <x-field name="name" :label="__('candidate.fields.name')" autocomplete="name" required maxlength="255" />
            <x-field name="email" type="email" :label="__('candidate.fields.email')" autocomplete="email" required maxlength="255" inputmode="email" />
            <x-field name="password" type="password" :label="__('candidate.fields.password')" autocomplete="new-password" required :help="__('candidate.fields.password_help')" />
            <x-field name="password_confirmation" type="password" :label="__('candidate.fields.password_confirmation')" autocomplete="new-password" required />
            <x-turnstile />
            <x-button>{{ __('candidate.register.submit') }}</x-button>
        </form>

        <p class="mt-6 text-sm">{{ __('candidate.register.have_account') }} <a href="{{ route('login') }}" class="text-brand-700 underline">{{ __('candidate.login.submit') }}</a></p>
    </x-card>
</x-layouts.public>
