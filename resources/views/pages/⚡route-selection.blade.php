<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\ThermalReceiptService;

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
        $user = session('kiosk_user', []); // 1. Load user from session
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

                $newBalance = $result['balance_after'] ?? max(0, (float)($card['balance'] ?? 0) - (float)$this->selectedVehicle['fare']);
                
                $card['balance'] = $newBalance;
                session(['kiosk_card' => $card]);

                $receiptData = [
                    'reference_no'   => $result['reference_no'] ?? ('FARE-' . now()->timestamp),
                    'date'           => now()->format('m/d/y h:i A'),
                    'passenger_name' => $user['name'] ?? 'Commuter',
                    'passenger_type' => $user['commuter_type'] ?? 'Regular',
                    'destination'    => $this->selectedRoute,
                    'vehicle_type'   => $this->selectedVehicle['type'],
                    'plate_number'   => $this->selectedVehicle['plate_number'] ?? 'N/A',
                    'fare'           => (float) $this->selectedVehicle['fare']
                ];

                // Print
                ThermalReceiptService::printCommuterFareSlip($receiptData);

                // The menu screen shows the full-screen "take your ticket" confirmation
                // from this flash (replaces the old corner toast). Nothing else changed here.
                session()->flash('kiosk_done', [
                    'kind'    => 'fare',
                    'receipt' => $receiptData,
                ]);

                $this->redirect(route('menu.options'), navigate: true);
                return;
            } else {
                Flux::toast(
                    duration: 5000,
                    variant: 'warning',
                    heading: 'Payment Denied',
                    text: $result['message'] ?? 'Unable to process fare payment.',
                );
            }

            Flux::toast(
                duration: 5000,
                variant: 'warning',
                heading: 'Payment Not Completed',
                text: $result['message'] ?? 'Payment failed.',
            );

            $this->dispatch('payment-failed', message: $result['message'] ?? 'Payment failed.');
        } catch (\Exception $e) {
            Log::error('Commuter fare print/payment error', ['error' => $e->getMessage()]);
            $this->dispatch('payment-failed', message: 'Could not connect to payment processor.');
        }
    }
};
?>

<div
    class="flex min-h-0 flex-1 flex-col"
    data-initial="{{ $this->selectedRoute ?? array_key_first($routes) }}"
    x-data="{
        dest: $el.dataset.initial,
        confirming: false,
        pick(el) {
            this.dest = el.dataset.dest;
            const selected = $wire.selectedRide;
            if (selected && selected.split('|')[0] !== this.dest) $wire.clearSelection();
        }
    }"
    @payment-failed.window="confirming = false"
>
    <div class="shrink-0 px-8 pb-3.5 pt-5">
        <h1 class="font-primary text-[38px] font-extrabold leading-tight text-white">Where are you going?</h1>
    </div>

    @if ($isOffline)
        <div class="mx-8 mb-3 flex items-center gap-3 rounded-2xl border-2 border-wait/50 bg-wait/10 p-4">
            <flux:icon name="exclamation-triangle" class="size-6 shrink-0 text-wait" />
            <p class="font-secondary text-xl font-medium text-wait">Live queue is temporarily unavailable. Please ask terminal staff for assistance.</p>
        </div>
    @endif

    @php
        $vehicleButton = 'relative grid h-[140px] w-full grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-7 overflow-hidden rounded-3xl border-2 px-7 text-left transition disabled:cursor-not-allowed disabled:opacity-55';
    @endphp

    <div class="grid min-h-0 flex-1 grid-cols-[300px_1fr] gap-7 px-8 pb-4">

        {{-- Left: destinations --}}
        <div class="kiosk-fade-bottom min-h-0 overflow-y-auto pb-10 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @forelse ($routes as $routeName => $vehicles)
                @php
                    $available = collect($vehicles)->where('is_full', false)->count();
                    $cheapest  = collect($vehicles)->min('fare');
                @endphp
                <button
                    type="button"
                    data-dest="{{ $routeName }}"
                    @click="pick($el)"
                    :class="dest === $el.dataset.dest ? 'border-secondary bg-k-700 ring-2 ring-secondary' : 'border-white/15 bg-k-800'"
                    class="mb-2.5 flex h-[72px] w-full items-center justify-between gap-3 rounded-[1.125rem] border-2 px-4 text-left {{ $available ? '' : 'opacity-55' }}"
                >
                    <div class="min-w-0">
                        <div class="truncate font-primary text-2xl font-extrabold leading-tight text-white">{{ $routeName }}</div>
                        <div class="font-secondary text-base text-tx-3">
                            @if (count($vehicles) === 0) No vehicles
                            @elseif ($available === 0) All full
                            @else {{ $available }} {{ \Illuminate\Support\Str::plural('vehicle', $available) }}
                            @endif
                        </div>
                    </div>
                    @if ($cheapest !== null)
                        <div class="shrink-0 text-right">
                            <div class="font-secondary text-base text-tx-3">from</div>
                            <div class="font-primary text-[22px] font-extrabold text-white tabular-nums">₱{{ number_format($cheapest, 0) }}</div>
                        </div>
                    @endif
                </button>
            @empty
                <div class="rounded-3xl border-2 border-white/15 bg-k-800 p-8 text-center font-secondary text-xl text-tx-2">
                    Loading available terminal queues...
                </div>
            @endforelse
        </div>

        {{-- Right: vehicles for the chosen destination --}}
        <div class="kiosk-fade-bottom min-h-0 overflow-y-auto pb-10 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($routes as $routeName => $vehicles)
                <div x-show="dest === $el.dataset.dest" x-cloak data-dest="{{ $routeName }}" class="space-y-3.5">
                    <div class="flex items-center justify-between">
                        <h2 class="font-primary text-[30px] font-extrabold text-white">{{ $routeName }}</h2>
                        <span class="font-secondary text-lg text-tx-3">{{ count($vehicles) }} {{ \Illuminate\Support\Str::plural('vehicle', count($vehicles)) }} at the terminal</span>
                    </div>

                    @forelse ($vehicles as $vehicle)
                        @php
                            $value      = "{$routeName}|{$vehicle['type']}";
                            $isSelected = $selectedRide === $value;
                            $ratio      = ($vehicle['capacity_max'] ?? 0) > 0 ? $vehicle['capacity_current'] / $vehicle['capacity_max'] : 0;
                            $bar        = $vehicle['is_full'] ? 'bg-stop' : ($ratio > .8 ? 'bg-wait' : 'bg-go');
                        @endphp
                        <button
                            type="button"
                            data-ride="{{ $value }}"
                            @click="$wire.set('selectedRide', $el.dataset.ride)"
                            @disabled($vehicle['is_full'])
                            class="{{ $vehicleButton }} {{ $isSelected ? 'border-secondary bg-k-700 ring-2 ring-secondary' : 'border-white/15 bg-k-800' }}"
                        >
                            @if ($isSelected)
                                <span class="absolute right-0 top-0 grid size-9 place-items-center rounded-bl-2xl bg-secondary text-primary">
                                    <flux:icon name="check" class="size-6 stroke-[2.5]" />
                                </span>
                            @endif

                            <div class="min-w-0">
                                <div class="font-primary text-[26px] font-extrabold leading-tight text-white">{{ $vehicle['type'] }}</div>
                                <div class="mt-2.5 flex items-center gap-4">
                                    <x-kiosk.plate>{{ $vehicle['plate_number'] ?? '—' }}</x-kiosk.plate>
                                    <span class="whitespace-nowrap font-secondary text-lg text-tx-2">{{ $vehicle['capacity_current'] }} of {{ $vehicle['capacity_max'] }} seats</span>
                                </div>
                                <div class="mt-3.5 h-2 w-[200px] overflow-hidden rounded-full bg-white/15">
                                    <div class="{{ $bar }} h-full rounded-full" style="width: {{ round($ratio * 100) }}%"></div>
                                </div>
                            </div>

                            <x-kiosk.eta :timestamp="$vehicle['departs_at_timestamp'] ?? null" :full="(bool) $vehicle['is_full']" />

                            <div class="min-w-[112px] text-right">
                                <small class="block font-secondary text-base leading-none text-tx-3">Fare</small>
                                <b class="font-primary text-4xl font-extrabold tabular-nums text-white">₱{{ number_format($vehicle['fare'], 2) }}</b>
                            </div>
                        </button>
                    @empty
                        <div class="rounded-3xl border-2 border-white/15 bg-k-800 p-8 text-center font-secondary text-xl italic text-tx-3">
                            No vehicles currently loading at the terminal for this route.
                        </div>
                    @endforelse
                </div>
            @endforeach
        </div>
    </div>

    <x-kiosk.action-bar>
        <x-slot:back>
            <x-kiosk.button variant="second" href="{{ route('menu.options') }}" wire:navigate>
                <flux:icon name="arrow-left" class="size-7" /> Back
            </x-kiosk.button>
        </x-slot:back>

        @if ($this->selectedVehicle)
            <div wire:key="fare-summary" class="min-w-0 text-center">
                <div class="font-secondary text-lg text-tx-3">Iriga → {{ $this->selectedRoute }}</div>
                <div class="flex items-center justify-center gap-3 font-primary text-2xl font-extrabold text-white">
                    {{ $this->selectedVehicle['type'] }} <x-kiosk.plate>{{ $this->selectedVehicle['plate_number'] ?? '—' }}</x-kiosk.plate>
                </div>
            </div>
        @else
            <span wire:key="fare-empty" class="font-secondary text-[22px] text-tx-3">Choose a vehicle to see your fare</span>
        @endif

        <x-slot:primary>
            @if ($this->selectedVehicle)
                <x-kiosk.button wire:key="pay-cta" size="xl" class="w-[290px]" @click="confirming = true">
                    Pay ₱{{ number_format($this->selectedVehicle['fare'], 2) }}
                </x-kiosk.button>
            @else
                <x-kiosk.button wire:key="pay-cta-off" variant="off" size="xl" class="w-[290px]" disabled>Choose a vehicle</x-kiosk.button>
            @endif
        </x-slot:primary>
    </x-kiosk.action-bar>

    {{-- Confirm: same look as every other dialog, big buttons, and it locks on first tap --}}
    @if ($this->selectedVehicle)
        <div x-show="confirming" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-k-950/80 p-6">
            <div class="w-[700px] rounded-[2rem] border-2 border-white/30 bg-k-800 p-9 shadow-2xl">
                <h2 class="font-primary text-[34px] font-extrabold leading-tight text-white">Confirm your payment</h2>

                <div class="mb-3 mt-4 flex items-center gap-3">
                    <flux:icon name="map-pin" class="size-8 text-secondary" />
                    <span class="font-primary text-[32px] font-extrabold text-white">Iriga → {{ $this->selectedRoute }}</span>
                </div>
                <div class="mb-3 flex items-center gap-3">
                    <x-kiosk.plate>{{ $this->selectedVehicle['plate_number'] ?? '—' }}</x-kiosk.plate>
                    <x-kiosk.vehicle-type :type="$this->selectedVehicle['type']" />
                </div>

                <dl class="divide-y divide-white/15 text-[22px]">
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-tx-2">Total fare</dt>
                        <dd class="font-primary text-[38px] font-extrabold text-secondary tabular-nums">₱{{ number_format($this->selectedVehicle['fare'], 2) }}</dd>
                    </div>
                </dl>
                <p class="mt-2 font-secondary text-xl text-tx-3">This amount will be deducted from your card.</p>

                <div class="mt-6 flex gap-4">
                    <x-kiosk.button variant="second" size="xl" class="w-[210px]" @click="confirming = false" wire:loading.attr="disabled" wire:target="confirmPayment">Cancel</x-kiosk.button>
                    <x-kiosk.button size="xl" class="flex-1" wire:click="confirmPayment" wire:loading.attr="disabled" wire:target="confirmPayment">
                        <span wire:loading.remove wire:target="confirmPayment">Confirm payment</span>
                        <span wire:loading.inline-flex wire:target="confirmPayment" class="items-center gap-3">
                            <flux:icon name="arrow-path" class="size-7 animate-spin" /> Processing…
                        </span>
                    </x-kiosk.button>
                </div>
            </div>
        </div>
    @endif
</div>
