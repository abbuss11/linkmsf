{{-- $state: invalid | candidate_account | wrong_account | login_required | confirm | register --}}
<x-layouts.public :title="__('invitations.page.title')" robots="noindex,nofollow">
    <x-card :title="__('invitations.page.title')">
        @if ($state === 'invalid')
            <p class="text-sm text-slate-700">{{ __('invitations.page.invalid') }}</p>
        @else
            <p class="mb-4 text-sm text-slate-700">
                {{ __('invitations.page.intro', ['organization' => $organization->name, 'role' => $invitation->role->getLabel()]) }}
            </p>

            @error('invitation')
                <p class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>
            @enderror

            @switch($state)
                @case('candidate_account')
                    <p class="text-sm text-slate-700">{{ __('invitations.errors.email_is_candidate') }}</p>
                    @break

                @case('wrong_account')
                    <p class="mb-4 text-sm text-slate-700">{{ __('invitations.page.wrong_account', ['email' => $invitation->email]) }}</p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button>{{ __('public.nav.logout') }}</x-button>
                    </form>
                    @break

                @case('login_required')
                    <p class="mb-4 text-sm text-slate-700">{{ __('invitations.page.login_required', ['email' => $invitation->email]) }}</p>
                    <a href="{{ $loginUrl }}" class="inline-block rounded bg-brand-600 px-4 py-2.5 font-medium text-white hover:bg-brand-700">{{ __('invitations.page.login') }}</a>
                    @break

                @case('confirm')
                    <form method="POST" action="{{ route('invitations.accept', ['invitation' => $invitation->ulid]) }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <x-button>{{ __('invitations.page.accept') }}</x-button>
                    </form>
                    @break

                @case('register')
                    <p class="mb-4 text-sm text-slate-700">{{ __('invitations.page.create_account', ['email' => $invitation->email]) }}</p>
                    <form method="POST" action="{{ route('invitations.accept', ['invitation' => $invitation->ulid]) }}" novalidate>
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <x-field name="name" :label="__('candidate.fields.name')" autocomplete="name" required maxlength="255" />
                        <x-field name="password" type="password" :label="__('candidate.fields.password')" autocomplete="new-password" required :help="__('candidate.fields.password_help')" />
                        <x-field name="password_confirmation" type="password" :label="__('candidate.fields.password_confirmation')" autocomplete="new-password" required />
                        <x-button>{{ __('invitations.page.accept_and_create') }}</x-button>
                    </form>
                    @break
            @endswitch
        @endif
    </x-card>
</x-layouts.public>
