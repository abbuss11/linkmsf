<x-layouts.public :title="__('candidate.verify.title')" robots="noindex">
    <x-card :title="__('candidate.verify.title')">
        <p class="mb-4 text-sm text-slate-700">{{ __('candidate.verify.intro', ['email' => auth()->user()->email]) }}</p>
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button>{{ __('candidate.verify.resend') }}</x-button>
        </form>
    </x-card>
</x-layouts.public>
