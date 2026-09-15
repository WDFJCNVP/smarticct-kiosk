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

<div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-8" wire:poll.15s="fetchLiveRoutes">

    <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-4">
        <flux:heading size="xl" class="font-primary font-extrabold text-white drop-shadow-sm">Live Queue</flux:heading>
        <flux:button href="{{ route('kiosk.home') }}" wire:navigate variant="ghost" icon="arrow-left" class="!text-white/70 hover:!text-white">Back</flux:button>
    </div>

    @if ($isOffline)
        <div class="flex items-center gap-3 rounded-xl border border-warning/30 bg-warning/10 p-4 backdrop-blur-md">
            <flux:icon name="exclamation-triangle" class="size-5 shrink-0 text-warning" />
            <flux:text class="text-sm font-medium text-warning">Live queue is temporarily unavailable. Please ask terminal staff for assistance.</flux:text>
        </div>
    @endif

    <div class="space-y-5">
        @forelse ($routes as $routeName => $vehicles)
            <flux:card class="space-y-4 !rounded-3xl !border !border-white/15 !bg-white/8 !backdrop-blur-md">
                <div class="flex items-center gap-2">
                    <flux:icon name="map-pin" class="size-5 text-secondary" />
                    <flux:heading size="lg" class="!text-white">{{ $routeName }}</flux:heading>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @forelse ($vehicles as $vehicle)
                        @php $timestamp = $vehicle['departs_at_timestamp'] ?? null; @endphp
                        <div
                            class="rounded-xl border border-white/10 bg-white/5 p-4 {{ $vehicle['is_full'] ? 'opacity-60' : '' }}"
                            x-data="{
                                endTime: {{ $timestamp ? (int) $timestamp : 'null' }},
                                display: '{{ $timestamp ? '--:--' : 'Departs when full' }}',
                                isDeparting: false,
                                urgent: false,
                                intervalId: null,
                                init() {
                                    if (!this.endTime) { this.display = 'Departs when full'; return; }
                                    this.update();
                                    this.intervalId = setInterval(() => this.update(), 1000);
                                },
                                destroy() { if (this.intervalId) clearInterval(this.intervalId); },
                                update() {
                                    if (!this.endTime) return;
                                    const remaining = this.endTime - Date.now();
                                    if (remaining <= 0) {
                                        this.display = 'Departing now'; this.isDeparting = true; this.urgent = false;
                                        if (this.intervalId) clearInterval(this.intervalId);
                                        return;
                                    }
                                    this.urgent = remaining < 30000;
                                    const m = String(Math.floor(remaining / 60000)).padStart(2, '0');
                                    const s = String(Math.floor((remaining % 60000) / 1000)).padStart(2, '0');
                                    this.display = `${m}:${s} left`;
                                }
                            }"
                            x-init="init()"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-white">{{ $vehicle['type'] }}</span>
                                @if ($vehicle['is_full'])
                                    <flux:badge color="red" size="sm">Full</flux:badge>
                                @else
                                    <template x-if="isDeparting"><flux:badge color="green" size="sm">Departing now</flux:badge></template>
                                    <template x-if="!isDeparting && endTime"><flux:badge color="amber" size="sm">Boarding</flux:badge></template>
                                    <template x-if="!endTime"><flux:badge color="zinc" size="sm">Waiting</flux:badge></template>
                                @endif
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-white/60">
                                <span>{{ $vehicle['capacity_current'] }}/{{ $vehicle['capacity_max'] }} seats</span>
                                <span>&bull;</span>
                                <span class="font-semibold text-success">₱{{ number_format($vehicle['fare'], 2) }}</span>
                                <span>&bull;</span>
                                <span :class="urgent ? 'text-danger font-semibold' : ''" x-text="display"></span>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-2 text-center text-sm italic text-white/50">
                            No vehicles currently loading for this route.
                        </div>
                    @endforelse
                </div>
            </flux:card>
        @empty
            <flux:card class="!rounded-3xl !border !border-white/15 !bg-white/8 p-10 text-center text-white/60 !backdrop-blur-md">
                <flux:icon name="queue-list" class="mx-auto mb-2 size-10 opacity-50" />
                Loading available terminal queues...
            </flux:card>
        @endforelse
    </div>

    <div class="pt-2 text-center">
        <flux:button href="{{ route('login.options') }}" wire:navigate variant="primary" class="kiosk-tap-target !rounded-xl !bg-secondary !font-bold !text-primary hover:!bg-secondary-hover">
            Ready to ride? Sign in to pay your fare
        </flux:button>
    </div>
</div>
