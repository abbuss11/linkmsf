<x-layouts.public :title="__('candidate.login.title')" robots="noindex">
    <x-card :title="__('candidate.login.title')">
        <form method="POST" action="{{ route('login') }}" novalidate>
            @csrf
            <x-field name="email" type="email" :label="__('candidate.fields.email')" autocomplete="email" required inputmode="email" />
            <x-field name="password" type="password" :label="__('candidate.fields.password')" autocomplete="current-password" required />
            <div class="mb-4 flex items-center gap-2">
                <input id="remember" type="checkbox" name="remember" value="1" class="h-4 w-4">
                <label for="remember" class="text-sm">{{ __('candidate.login.remember') }}</label>
            </div>
            <x-button>{{ __('candidate.login.submit') }}</x-button>
        </form>

        <div class="mt-6 space-y-2 text-sm">
            <p><a href="{{ route('password.request') }}" class="text-brand-700 underline">{{ __('candidate.login.forgot') }}</a></p>
            <p>{{ __('candidate.login.no_account') }} <a href="{{ route('register') }}" class="text-brand-700 underline">{{ __('candidate.register.submit') }}</a></p>
            <p class="text-slate-600">{{ __('candidate.login.staff_hint') }} <a href="{{ url('/app/login') }}" class="underline">{{ __('candidate.login.staff_link') }}</a></p>
        </div>
    </x-card>
</x-layouts.public>
