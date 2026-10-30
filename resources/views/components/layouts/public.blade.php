@props(['title' => null, 'robots' => 'index,follow'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ $robots }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/dynamic-form.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:rounded focus:bg-white focus:p-2">
        {{ __('public.skip_to_content') }}
    </a>

    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-4xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('home') }}" class="text-lg font-bold text-brand-700">{{ config('app.name') }}</a>
            <nav aria-label="{{ __('public.nav.label') }}" class="flex flex-wrap items-center justify-end gap-3 text-sm">
                <a href="{{ route('job-postings.index') }}" class="hover:underline">{{ __('public.nav.offers') }}</a>
                @auth
                    @if (auth()->user()->isCandidate())
                        <a href="{{ route('candidate.dashboard') }}" class="hover:underline">{{ __('public.nav.my_space') }}</a>
                        <a href="{{ route('candidate.profile') }}" class="hover:underline">{{ __('public.nav.profile') }}</a>
                    @else
                        <a href="{{ url('/app') }}" class="hover:underline">{{ __('public.nav.back_office') }}</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="hover:underline">{{ __('public.nav.logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hover:underline">{{ __('public.nav.login') }}</a>
                    <a href="{{ route('register') }}" class="rounded bg-brand-600 px-3 py-1.5 font-medium text-white hover:bg-brand-700">{{ __('public.nav.register') }}</a>
                @endauth
            </nav>
        </div>
    </header>

    <main id="contenu" class="mx-auto max-w-4xl px-4 py-8">
        @if (session('status'))
            <div role="status" class="mb-6 rounded border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 py-6 text-center text-xs text-slate-500">
        <p>{{ config('app.name') }} · <a href="{{ route('organization-requests.create') }}" class="hover:underline">{{ __('public.footer.organizations') }}</a> · <a href="{{ url('/app/login') }}" class="hover:underline">{{ __('public.footer.back_office') }}</a></p>
    </footer>
</body>
</html>
