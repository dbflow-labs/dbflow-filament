<x-filament-panels::page>
    @if ($this->isCoreRuntimeDisabled())
        <x-filament::section
            class="mb-6"
            :heading="__('dbflow-filament::dbflow-filament.pages.my_tasks.runtime_disabled_heading')"
            :description="__('dbflow-filament::dbflow-filament.pages.my_tasks.runtime_disabled_description')"
        />
    @endif

    {{ $this->table }}
</x-filament-panels::page>
