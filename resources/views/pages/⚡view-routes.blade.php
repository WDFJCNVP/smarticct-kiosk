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

<div class="flex min-h-0 flex-1 flex-col">
    <div class="flex shrink-0 items-end justify-between gap-4 px-8 pb-3.5 pt-5">
        <div>
            <h1 class="font-primary text-[38px] font-extrabold leading-tight text-white">Routes &amp; fares</h1>
            <p class="mt-1 font-secondary text-xl text-tx-2">{{ count($this->groupedRoutes) }} destinations from Iriga City</p>
        </div>
    </div>

    @if (count($this->vehicleTypes) > 1)
        <div class="flex shrink-0 gap-2.5 overflow-x-auto px-8 pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach (array_merge(['All'], $this->vehicleTypes) as $type)
                <button
                    type="button"
                    wire:click="setFilter('{{ $type }}')"
                    class="h-[60px] shrink-0 rounded-full border-2 px-6 font-primary text-[21px] font-bold transition {{ $activeFilter === $type ? 'border-secondary bg-secondary text-primary' : 'border-white/30 text-white hover:bg-white/10' }}"
                >
                    {{ $type }}
                </button>
            @endforeach
        </div>
    @endif

    <div class="kiosk-fade-bottom min-h-0 flex-1 overflow-y-auto px-8 pb-10 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @if (count($this->groupedRoutes) > 0)
            <div class="grid grid-cols-2 content-start gap-4">
                @foreach ($this->groupedRoutes as $group)
                    <div class="rounded-3xl border-2 border-white/15 bg-k-800 px-5 pb-2 pt-5">
                        <div class="mb-2 flex items-center gap-2.5 font-primary text-[28px] font-extrabold leading-tight text-white">
                            <flux:icon name="map-pin" class="size-7 text-secondary" />
                            {{ $group['terminal'] }}
                        </div>

                        {{-- One row per vehicle type serving this city --}}
                        <div class="divide-y divide-white/15 border-t border-white/15">
                            @foreach ($group['options'] as $option)
                                @php
                                    $vehicleType = $option['operator_ticket_rate']['vehicle_type'] ?? 'N/A';
                                    $firstTrip = $option['metadata']['first_trip'] ?? null;
                                    $lastTrip  = $option['metadata']['last_trip'] ?? null;
                                @endphp
                                <div class="flex items-center justify-between gap-3 py-3">
                                    <div class="min-w-0 space-y-1">
                                        <x-kiosk.vehicle-type :type="$vehicleType" />
                                        @if ($firstTrip || $lastTrip)
                                            <p class="font-secondary text-base text-tx-3">{{ $firstTrip ?? '—' }} – {{ $lastTrip ?? '—' }}</p>
                                        @endif
                                    </div>
                                    <span class="shrink-0 font-primary text-[28px] font-extrabold text-secondary tabular-nums">
                                        ₱{{ number_format($option['fare'], 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex h-full flex-col items-center justify-center gap-3 py-16 text-center">
                <flux:icon name="map-pin" class="size-10 text-tx-3" />
                <p class="font-secondary text-2xl text-tx-2">No routes found for this filter.</p>
            </div>
        @endif
    </div>

    <x-kiosk.action-bar>
        <x-slot:back>
            <x-kiosk.button variant="second" href="{{ route('menu.options') }}" wire:navigate>
                <flux:icon name="arrow-left" class="size-7" /> Back
            </x-kiosk.button>
        </x-slot:back>
    </x-kiosk.action-bar>
</div>
