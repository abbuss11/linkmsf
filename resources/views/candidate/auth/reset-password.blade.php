<x-layouts.public :title="__('candidate.reset.title')" robots="noindex">
    <x-card :title="__('candidate.reset.title')">
        <form method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-field name="email" type="email" :label="__('candidate.fields.email')" :value="$email" autocomplete="email" required />
            <x-field name="password" type="password" :label="__('candidate.fields.password')" autocomplete="new-password" required :help="__('candidate.fields.password_help')" />
            <x-field name="password_confirmation" type="password" :label="__('candidate.fields.password_confirmation')" autocomplete="new-password" required />
            <x-button>{{ __('candidate.reset.submit') }}</x-button>
        </form>
    </x-card>
</x-layouts.public>
