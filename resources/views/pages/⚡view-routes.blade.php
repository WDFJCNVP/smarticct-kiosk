<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component
{
    public array $routeList = [];
    public string $activeFilter = 'All';

    public function mount(): void
    {
        if (! session()->has('kiosk_card')) {
            $this->redirect(route('login.options'), navigate: true);
            return;
        }

        $this->routeList = session('kiosk_route_list') ?? [];
    }

    #[Computed]
    public function vehicleTypes(): array
    {
        return collect($this->routeList)
            ->pluck('operator_ticket_rate.vehicle_type')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * One entry per terminal/city, each carrying the list of vehicle
     * types+fares that serve it — instead of a separate card per
     * terminal+vehicle combination. A city with 4 vehicle types used to mean
     * 4 near-duplicate cards; now it's 1 card with 4 rows inside.
     */
    #[Computed]
    public function groupedRoutes(): array
    {
        $rows = collect($this->routeList);

        if ($this->activeFilter !== 'All') {
            $rows = $rows->filter(
                fn ($route) => ($route['operator_ticket_rate']['vehicle_type'] ?? null) === $this->activeFilter
            );
        }

        return $rows
            ->groupBy('terminal')
            ->map(fn ($routes, $terminal) => [
                'terminal' => $terminal,
                'options'  => $routes->values()->all(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    public function setFilter(string $type): void
    {
        $this->activeFilter = $type;
    }
};
?>

{{-- gap-2 instead of gap-3: the panel already carries its own padding, so the
     stack gap only needs to separate, not breathe. sm:p-6 instead of sm:p-8
     to stop the outer frame from adding another 32px on top. --}}
<div class="mx-auto flex h-full w-full max-w-6xl flex-col gap-2 p-4 sm:p-6">

    {{-- Plain elements here, not flux:heading/flux:text — kept this page's
         header spacing fully in our own hands rather than inheriting
         whatever margins those components carry internally. --}}
    <div class="flex shrink-0 items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="font-primary text-2xl font-extrabold leading-tight text-white drop-shadow-sm sm:text-3xl">Routes &amp; Fares</h1>
            <p class="mt-0.5 font-secondary text-xs leading-tight text-white/50">{{ count($this->groupedRoutes) }} destinations</p>
        </div>
        {{-- !h-10 instead of !h-12: a 48px button was forcing the whole header
             row taller than the title block, creating dead space underneath. --}}
        <flux:button
            href="{{ route('menu.options') }}"
            wire:navigate
            variant="ghost"
            class="!h-10 !shrink-0 !rounded-xl !border !border-white/10 !px-5 !text-sm !font-semibold !text-white/70 transition hover:!bg-white/10 hover:!text-white"
        >
            <span class="inline-flex items-center gap-2">
                <flux:icon name="arrow-left" class="size-4" />
                Back
            </span>
        </flux:button>
    </div>

    @if (count($this->vehicleTypes) > 1)
        <div class="flex shrink-0 gap-2 overflow-x-auto">
            <button
                type="button"
                wire:click="setFilter('All')"
                class="shrink-0 rounded-full border px-5 py-2 font-secondary text-sm font-semibold transition {{ $activeFilter === 'All' ? 'border-secondary bg-secondary text-primary' : 'border-white/15 bg-white/8 text-white/70 hover:border-white/25' }}"
            >
                All
            </button>
            @foreach ($this->vehicleTypes as $type)
                <button
                    type="button"
                    wire:click="setFilter('{{ $type }}')"
                    class="shrink-0 rounded-full border px-5 py-2 font-secondary text-sm font-semibold transition {{ $activeFilter === $type ? 'border-secondary bg-secondary text-primary' : 'border-white/15 bg-white/8 text-white/70 hover:border-white/25' }}"
                >
                    {{ $type }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- p-3 sm:p-4 instead of p-4 sm:p-6: the panel's own top padding was
         stacking on top of the flex gap, doubling the header-to-cards space. --}}
    <div class="relative min-h-0 flex-1 overflow-hidden rounded-3xl border border-white/15 bg-white/8 backdrop-blur-md">
        <div class="h-full overflow-y-auto p-3 [-ms-overflow-style:none] [scrollbar-width:none] sm:p-4 [&::-webkit-scrollbar]:hidden">
            @if (count($this->groupedRoutes) > 0)
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->groupedRoutes as $group)
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="flex items-center gap-2 pb-2.5">
                                <flux:icon name="map-pin" class="size-4 text-secondary" />
                                <span class="font-primary text-base font-bold text-white sm:text-lg">
                                    {{ $group['terminal'] }}
                                </span>
                            </div>

                            {{-- One row per vehicle type serving this city —
                                 this is the "separate by vehicle type" part,
                                 kept inside the one card instead of spawning
                                 a new card each. --}}
                            <div class="divide-y divide-white/10 border-t border-white/10">
                                @foreach ($group['options'] as $option)
                                    @php
                                        $vehicleType = $option['operator_ticket_rate']['vehicle_type'] ?? 'N/A';
                                        $vehicle = strtolower($vehicleType);
                                        $badgeColor = match (true) {
                                            str_contains($vehicle, 'jeep')      => 'yellow',
                                            str_contains($vehicle, 'multicab')  => 'purple',
                                            str_contains($vehicle, 'aircon')    => 'indigo',
                                            str_contains($vehicle, 'uv')        => 'green',
                                            str_contains($vehicle, 'bus')       => 'blue',
                                            default                              => 'zinc',
                                        };
                                        $firstTrip = $option['metadata']['first_trip'] ?? null;
                                        $lastTrip  = $option['metadata']['last_trip'] ?? null;
                                    @endphp
                                    <div class="flex items-center justify-between gap-3 py-2 first:pt-2.5 last:pb-0">
                                        <div class="min-w-0 space-y-1">
                                            <flux:badge color="{{ $badgeColor }}" size="sm">{{ $vehicleType }}</flux:badge>
                                            @if ($firstTrip || $lastTrip)
                                                <p class="font-secondary text-xs text-white/50">
                                                    {{ $firstTrip ?? '—' }} – {{ $lastTrip ?? '—' }}
                                                </p>
                                            @endif
                                        </div>
                                        <span class="shrink-0 font-primary text-lg font-black text-secondary sm:text-xl">
                                            ₱{{ number_format($option['fare'], 2) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex h-full flex-col items-center justify-center gap-2 py-16 text-center">
                    <flux:icon name="map-pin" class="size-8 text-white/40" />
                    <flux:text class="text-white/60">No routes found for this filter.</flux:text>
                </div>
            @endif
        </div>
    </div>
</div>