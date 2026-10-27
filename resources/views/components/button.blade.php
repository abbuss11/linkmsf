<button {{ $attributes->merge(['type' => 'submit', 'class' => 'w-full rounded bg-brand-600 px-4 py-2.5 text-base font-medium text-white hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-600/40 sm:w-auto']) }}>
    {{ $slot }}
</button>
