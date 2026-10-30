{{-- Cloudflare Turnstile, loaded only when enabled. --}}
@if (config('link.turnstile.enabled'))
    @push('head')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endpush
    <div class="mb-4">
        <div class="cf-turnstile" data-sitekey="{{ config('link.turnstile.site_key') }}" data-language="fr"></div>
        @error('cf-turnstile-response')
            <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror
    </div>
@endif
