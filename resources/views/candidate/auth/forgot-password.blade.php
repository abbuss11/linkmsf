<x-layouts.public :title="__('candidate.forgot.title')" robots="noindex">
    <x-card :title="__('candidate.forgot.title')">
        <p class="mb-4 text-sm text-slate-700">{{ __('candidate.forgot.intro') }}</p>
        <form method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf
            <x-field name="email" type="email" :label="__('candidate.fields.email')" autocomplete="email" required inputmode="email" />
            <x-button>{{ __('candidate.forgot.submit') }}</x-button>
        </form>
    </x-card>
</x-layouts.public>
