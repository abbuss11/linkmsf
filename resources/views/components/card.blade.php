@props(['title' => null])

<section {{ $attributes->merge(['class' => 'mx-auto max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm']) }}>
    @if ($title)
        <h1 class="mb-4 text-xl font-semibold">{{ $title }}</h1>
    @endif
    {{ $slot }}
</section>
