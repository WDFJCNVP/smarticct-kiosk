<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

new class extends Component
{
    public array $routes = [];
    public bool $isOffline = false;

    public function mount(): void
    {
        $this->fetchLiveRoutes();
    }

    public function fetchLiveRoutes(): void
    {
        $baseUrl = config('services.smarticct.api_url', 'https://smarticct.app');

        try {
            $response = Http::acceptJson()->timeout(5)->get("{$baseUrl}/api/queued/routes");

            if ($response->successful()) {
                $this->routes = $response->json('data') ?? [];
                $this->isOffline = false;
                return;
            }

            $this->isOffline = true;
        } catch (\Exception $e) {
            Log::warning('Failed fetching guest live queue', ['error' => $e->getMessage()]);
            $this->isOffline = true;
        }
    }

    #[On('echo:vehicle-queue,.QueuedVehicleEvent')]
    public function onQueueUpdated(): void
    {
        $this->fetchLiveRoutes();
    }
};
?>

@php
    // One row per vehicle, soonest departure first; full and "leaves when full" vehicles sink to the bottom.
    $rows = collect($routes)
        ->flatMap(fn ($vehicles, $routeName) => collect($vehicles)->map(fn ($vehicle) => $vehicle + ['route' => $routeName]))
        ->sort(fn ($a, $b) => [(int) $a['is_full'], $a['departs_at_timestamp'] ?? PHP_INT_MAX] <=> [(int) $b['is_full'], $b['departs_at_timestamp'] ?? PHP_INT_MAX])
        ->values();

    $isSignedIn = session()->has('kiosk_card');
    $isOperator = data_get(session('kiosk_user'), 'role') === 'operator';
    $cols = 'grid grid-cols-[minmax(0,1.7fr)_minmax(0,2.8fr)_150px_120px_160px] items-center gap-4 px-5';
@endphp

<div class="flex min-h-0 flex-1 flex-col" wire:poll.15s="fetchLiveRoutes">
    <div class="shrink-0 px-8 pb-2 pt-5">
        <h1 class="font-primary text-[38px] font-extrabold leading-tight text-white">Live queue</h1>
        <p class="mt-1 flex items-center gap-2 font-secondary text-xl text-tx-2">
            <span class="size-2.5 rounded-full bg-go"></span> Updates as vehicles board
        </p>
    </div>

    @if ($isOffline)
        <div class="mx-8 mb-2 flex items-center gap-3 rounded-2xl border-2 border-wait/50 bg-wait/10 p-4">
            <flux:icon name="exclamation-triangle" class="size-6 shrink-0 text-wait" />
            <p class="font-secondary text-xl font-medium text-wait">Live queue is temporarily unavailable. Please ask terminal staff for assistance.</p>
        </div>
    @endif

    <div class="px-8">
        <div class="{{ $cols }} h-11 font-secondary text-[17px] font-semibold text-tx-3">
            <div>Destination</div><div>Vehicle</div><div>Seats</div><div>Fare</div><div class="text-center">Leaves</div>
        </div>
    </div>

    <div class="kiosk-fade-bottom min-h-0 flex-1 overflow-y-auto px-8 pb-10 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @forelse ($rows as $vehicle)
            @php
                $ratio = ($vehicle['capacity_max'] ?? 0) > 0 ? $vehicle['capacity_current'] / $vehicle['capacity_max'] : 0;
                $bar   = $vehicle['is_full'] ? 'bg-stop' : ($ratio > .8 ? 'bg-wait' : 'bg-go');
            @endphp
            <div class="{{ $cols }} mb-2 h-[84px] rounded-[1.125rem] border-2 border-white/15 bg-k-800 {{ $vehicle['is_full'] ? 'opacity-60' : '' }}">
                <div class="truncate font-primary text-[28px] font-extrabold leading-tight text-white">{{ $vehicle['route'] }}</div>
                <div class="flex items-center gap-3">
                    <x-kiosk.plate>{{ $vehicle['plate_number'] ?? '—' }}</x-kiosk.plate>
                    <x-kiosk.vehicle-type :type="$vehicle['type']" />
                </div>
                <div>
                    <div class="font-secondary text-lg text-tx-2">{{ $vehicle['capacity_current'] }} of {{ $vehicle['capacity_max'] }}</div>
                    <div class="mt-1.5 h-2 w-[130px] overflow-hidden rounded-full bg-white/15">
                        <div class="{{ $bar }} h-full rounded-full" style="width: {{ round($ratio * 100) }}%"></div>
                    </div>
                </div>
                <div class="font-primary text-[26px] font-extrabold tabular-nums text-white">₱{{ number_format($vehicle['fare'], 2) }}</div>
                <x-kiosk.eta :timestamp="$vehicle['departs_at_timestamp'] ?? null" :full="(bool) $vehicle['is_full']" size="sm" />
            </div>
        @empty
            <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
                <flux:icon name="queue-list" class="size-10 text-tx-3" />
                <p class="font-secondary text-2xl text-tx-2">Loading available terminal queues...</p>
            </div>
        @endforelse
    </div>

    <x-kiosk.action-bar>
        <x-slot:back>
            <x-kiosk.button variant="second" href="{{ $isSignedIn ? route('menu.options') : route('kiosk.home') }}" wire:navigate>
                <flux:icon name="arrow-left" class="size-7" /> Back
            </x-kiosk.button>
        </x-slot:back>

        <x-slot:primary>
            @if ($isSignedIn)
                <x-kiosk.button size="xl" href="{{ $isOperator ? route('queue.vehicle') : route('route.select') }}" wire:navigate>
                    <flux:icon name="ticket" class="size-7" /> {{ $isOperator ? 'Queue a vehicle' : 'Pay fare' }}
                </x-kiosk.button>
            @else
                <x-kiosk.button size="xl" href="{{ route('kiosk.home') }}" wire:navigate>
                    <flux:icon name="credit-card" class="size-7" /> Ready to ride? Sign in to pay
                </x-kiosk.button>
            @endif
        </x-slot:primary>
    </x-kiosk.action-bar>
</div>
