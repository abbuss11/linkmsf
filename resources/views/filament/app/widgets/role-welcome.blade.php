<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('dashboard.welcome.title', ['name' => $name]) }}
        </x-slot>

        <p>
            @if ($role)
                {{ __('dashboard.welcome.role', ['role' => $role->getLabel(), 'organization' => $organization]) }}
            @endif
        </p>
        <p style="margin-top: .5rem">
            @switch($role?->value)
                @case('orgadmin')
                    {{ __('dashboard.welcome.orgadmin') }}
                    @break
                @case('recruiter')
                    {{ __('dashboard.welcome.recruiter') }}
                    @break
                @case('jury')
                    {{ __('dashboard.welcome.jury') }}
                    @break
            @endswitch
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
