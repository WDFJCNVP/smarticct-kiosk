<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

new class extends Component
{
    public array $routes = [];
    public ?string $selectedRide = null;
    public bool $isOffline = false;

    public function mount(): void
    {
        if (! session()->has('kiosk_card')) {
            $this->redirect(route('login.options'), navigate: true);
            return;
        }

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
            Log::warning('Failed fetching kiosk routes', ['error' => $e->getMessage()]);
            $this->isOffline = true;
        }
    }

    #[On('echo:vehicle-queue,.QueuedVehicleEvent')]
    public function onQueueUpdated(): void
    {
        $this->fetchLiveRoutes();
    }

    public function clearSelection(): void
    {
        $this->selectedRide = null;
    }

    #[Computed]
    public function selectedRoute(): ?string
    {
        return $this->selectedRide ? explode('|', $this->selectedRide)[0] : null;
    }

    #[Computed]
    public function selectedVehicle(): ?array
    {
        if (! $this->selectedRide) {
            return null;
        }

        [$route, $type] = explode('|', $this->selectedRide);

        return collect($this->routes[$route] ?? [])->firstWhere('type', $type);
    }

    public function confirmPayment(): void
    {
        if (! $this->selectedVehicle || ! session()->has('kiosk_card')) {
            return;
        }

        $card = session('kiosk_card');
        $baseUrl = config('services.smarticct.api_url', 'https://smarticct.app');

        try {
            $response = Http::acceptJson()
                ->timeout(8)
                ->post("{$baseUrl}/api/cards/tap", [
                    'uid'              => $card['uid'],
                    'transaction_type' => 'fare_payment',
                    'amount'           => $this->selectedVehicle['fare'],
                    'destination'      => $this->selectedRoute,
                    'vehicle_type'     => $this->selectedVehicle['type'],
                ]);

            $result = $response->json();

            if ($result['success'] === true) {

                Flux::toast(
                    duration: 5000,
                    variant: 'success',
                    heading: 'Fare Payment Successful',
                    text: ($result['message'] ?? '') . ' Please get your ticket!',
                );

                $this->redirect(route('menu.options'), navigate: true);

                return;
            }

            Flux::toast(
                duration: 5000,
                variant: 'warning',
                heading: 'Payment Not Completed',
                text: $result['message'] ?? 'Payment failed.',
            );

            $this->dispatch('payment-failed', message: $result['message'] ?? 'Payment failed.');
        } catch (\Exception $e) {
            $this->dispatch('payment-failed', message: 'Could not connect to payment processor.');
        }
    }
};
?>

<div class="mx-auto grid w-full max-w-7xl grid-cols-1 gap-6 p-4 select-none sm:p-6 lg:grid-cols-3">

    {{-- Left: Route List --}}
    <div class="max-h-[75vh] space-y-4 overflow-y-auto pr-2 lg:col-span-2">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
            <flux:heading size="xl" class="font-primary font-black tracking-tight text-white drop-shadow-sm">
                Pay Your Fare
            </flux:heading>
            <flux:button href="{{ route('menu.options') }}" wire:navigate variant="ghost" icon="arrow-left" class="!text-white/70 hover:!text-white">Back</flux:button>
        </div>

        @if ($isOffline)
            <div class="flex items-center gap-3 rounded-xl border border-warning/30 bg-warning/10 p-4 backdrop-blur-md">
                <flux:icon name="exclamation-triangle" class="size-5 shrink-0 text-warning" />
                <flux:text class="text-sm font-medium text-warning">Live queue is temporarily unavailable. Please ask terminal staff for assistance.</flux:text>
            </div>
        @endif

        @forelse ($routes as $routeName => $vehicles)
            <flux:card class="space-y-4 !rounded-3xl !border !border-white/15 !bg-white/8 !backdrop-blur-md">
                <div class="flex items-center gap-2">
                    <flux:icon name="map-pin" class="size-5 text-secondary" />
                    <flux:heading size="lg" class="!text-white">{{ $routeName }}</flux:heading>
                </div>

                <flux:radio.group wire:model.live="selectedRide" variant="cards" class="max-sm:flex-col">
                    @forelse ($vehicles as $vehicle)
                        @php
                            $value = "{$routeName}|{$vehicle['type']}";
                            $timestamp = $vehicle['departs_at_timestamp'] ?? null;
                        @endphp
                        <flux:radio value="{{ $value }}" :disabled="$vehicle['is_full']">
                            <div
                                class="flex w-full flex-col gap-1"
                                x-data="{
                                    endTime: {{ $timestamp ? (int)$timestamp : 'null' }},
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
                                        this.display = `${m}:${s} mins left`;
                                    }
                                }"
                                x-init="init()"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium">{{ $vehicle['type'] }}</span>

                                    @if ($vehicle['is_full'])
                                        <flux:badge color="red" size="sm">Full</flux:badge>
                                    @else
                                        <template x-if="isDeparting"><flux:badge color="green" size="sm">Departing now</flux:badge></template>
                                        <template x-if="!isDeparting && endTime"><flux:badge color="amber" size="sm">Boarding</flux:badge></template>
                                        <template x-if="!endTime"><flux:badge color="zinc" size="sm">Waiting</flux:badge></template>
                                    @endif
                                </div>

                                <span class="text-sm text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $vehicle['capacity_current'] }}/{{ $vehicle['capacity_max'] }} seats
                                    &bull; ₱{{ number_format($vehicle['fare'], 2) }}
                                    &bull;
                                    <span :class="urgent ? 'text-danger font-semibold' : ''" x-text="display"></span>
                                </span>
                            </div>
                        </flux:radio>
                    @empty
                        <div class="py-4 text-center text-xs italic text-white/50">
                            No vehicles currently loading at the terminal for this route.
                        </div>
                    @endforelse
                </flux:radio.group>
            </flux:card>
        @empty
            <flux:card class="!rounded-3xl !border !border-white/15 !bg-white/8 p-8 text-center text-white/60 !backdrop-blur-md">
                Loading available terminal queues...
            </flux:card>
        @endforelse
    </div>

    {{-- Right: Sticky Summary --}}
    <div class="lg:sticky lg:top-4 lg:self-start">
        <flux:card class="space-y-4 !rounded-3xl !border !border-white/15 !bg-white/8 !backdrop-blur-md">
            <div class="flex items-center justify-between">
                <flux:heading size="lg" class="!text-white">Your Ride</flux:heading>
                @if ($selectedRide)
                    <flux:button wire:click="clearSelection" variant="ghost" size="sm" icon="x-mark" aria-label="Remove selection" class="!text-white/70 hover:!text-white" />
                @endif
            </div>

            @if (! $selectedRide)
                <div class="flex flex-col items-center gap-2 py-10 text-center text-white/60">
                    <flux:icon name="ticket" class="size-8" />
                    <flux:text class="!text-white/60">Select a route and vehicle to continue</flux:text>
                </div>
            @else
                @php $selectedTimestamp = $this->selectedVehicle['departs_at_timestamp'] ?? null; @endphp
                <div
                    class="space-y-3"
                    x-data="{
                        endTime: {{ $selectedTimestamp ? (int)$selectedTimestamp : 'null' }},
                        display: '{{ $selectedTimestamp ? '--:--' : 'Departs when full' }}',
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
                                this.display = 'Departing now';
                                if (this.intervalId) clearInterval(this.intervalId);
                                return;
                            }
                            const m = String(Math.floor(remaining / 60000)).padStart(2, '0');
                            const s = String(Math.floor((remaining % 60000) / 1000)).padStart(2, '0');
                            this.display = `${m}:${s} remaining`;
                        }
                    }"
                    x-init="init()"
                >
                    <div class="flex items-center justify-between">
                        <flux:text class="!text-white/60">Route</flux:text>
                        <flux:text class="!text-white font-medium">{{ $this->selectedRoute }}</flux:text>
                    </div>
                    <div class="flex items-center justify-between">
                        <flux:text class="!text-white/60">Vehicle</flux:text>
                        <flux:text class="!text-white font-medium">{{ $this->selectedVehicle['type'] }} ({{ $this->selectedVehicle['plate_number'] }})</flux:text>
                    </div>
                    <div class="flex items-center justify-between">
                        <flux:text class="!text-white/60">Seats</flux:text>
                        <flux:text class="!text-white font-medium">{{ $this->selectedVehicle['capacity_current'] }}/{{ $this->selectedVehicle['capacity_max'] }}</flux:text>
                    </div>
                    <div class="flex items-center justify-between">
                        <flux:text class="!text-white/60">Departure</flux:text>
                        <flux:text class="!text-white font-medium" x-text="display"></flux:text>
                    </div>

                    <flux:separator class="!border-white/10" />

                    <div class="flex items-center justify-between">
                        <flux:heading size="base" class="!text-white">Total Fare</flux:heading>
                        <flux:heading size="base" class="!text-secondary">₱{{ number_format($this->selectedVehicle['fare'], 2) }}</flux:heading>
                    </div>
                </div>
            @endif

            <flux:modal.trigger name="confirm-ride">
                <flux:button variant="primary" class="kiosk-tap-target w-full !bg-secondary !font-bold !text-primary hover:!bg-secondary-hover" :disabled="! $selectedRide">
                    Confirm &amp; Pay
                </flux:button>
            </flux:modal.trigger>
        </flux:card>
    </div>

    {{-- Modal Confirmation --}}
    <flux:modal name="confirm-ride" class="min-w-[24rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Confirm your ride</flux:heading>
                <flux:text class="mt-2">Please review your selected route before paying.</flux:text>
            </div>

            @if ($selectedRide)
                <div class="space-y-3 rounded-lg bg-light-subtle p-4 dark:bg-dark-subtle">
                    <div class="flex items-center justify-between">
                        <flux:text class="text-light-txt-muted dark:text-dark-txt-muted">Route</flux:text>
                        <flux:text class="font-medium">{{ $this->selectedRoute }}</flux:text>
                    </div>
                    <div class="flex items-center justify-between">
                        <flux:text class="text-light-txt-muted dark:text-dark-txt-muted">Vehicle</flux:text>
                        <flux:text class="font-medium">{{ $this->selectedVehicle['type'] }}</flux:text>
                    </div>
                    <div class="flex items-center justify-between">
                        <flux:text class="text-light-txt-muted dark:text-dark-txt-muted">Plate Number</flux:text>
                        <flux:text class="font-medium font-mono">{{ $this->selectedVehicle['plate_number'] }}</flux:text>
                    </div>

                    <flux:separator />

                    <div class="flex items-center justify-between">
                        <flux:heading size="base">Total Fare</flux:heading>
                        <flux:heading size="base">₱{{ number_format($this->selectedVehicle['fare'], 2) }}</flux:heading>
                    </div>
                </div>

                <flux:text size="sm" class="text-light-txt-muted dark:text-dark-txt-muted">
                    This amount will be deducted from your card.
                </flux:text>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="confirmPayment">Confirm Payment</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
