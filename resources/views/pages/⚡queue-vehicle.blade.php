<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\ThermalReceiptService;

new class extends Component
{
    public array $card = [];
    public array $user = [];
    public array $vehicles = [];
    public ?int $selectedVehicleId = null;
    public string $driverName = '';
    public ?string $errorMessage = null;
    public ?string $driverNameError = null;
    public bool $isProcessing = false;

    public function mount(): void
    {
        if (! session()->has('kiosk_card') || ! session()->has('kiosk_user')) {
            $this->redirect(route('login.tap'), navigate: true);
            return;
        }

        $this->card = session('kiosk_card', []);
        $this->user = session('kiosk_user', []);

        if (($this->user['role'] ?? '') !== 'operator') {
            $this->redirect(route('login.tap'), navigate: true);
            return;
        }

        $this->vehicles = $this->user['vehicles'] ?? [];

        if (count($this->vehicles) === 1) {
            $this->selectVehicle((int) $this->vehicles[0]['id']);
        }
    }

    #[Computed]
    public function selectedVehicle(): ?array
    {
        if (! $this->selectedVehicleId) {
            return null;
        }

        return collect($this->vehicles)->firstWhere('id', $this->selectedVehicleId);
    }

    #[Computed]
    public function currentBalance(): float
    {
        return (float) ($this->card['balance'] ?? 0.00);
    }

    #[Computed]
    public function balanceAfterDeduction(): float
    {
        if (! $this->selectedVehicle) {
            return $this->currentBalance;
        }

        $fee = (float) ($this->selectedVehicle['queueing_fee'] ?? 0.00);
        return max(0.00, $this->currentBalance - $fee);
    }

    public function selectVehicle(int $vehicleId): void
    {
        $this->selectedVehicleId = $vehicleId;
        $this->errorMessage = null;
        $this->driverNameError = null;

        // Pre-fill with the vehicle's registered driver, but the operator can
        // change it — a different driver may be on shift for this trip.
        $this->driverName = $this->selectedVehicle['driver_name'] ?? '';
    }

    public function clearSelection(): void
    {
        $this->selectedVehicleId = null;
        $this->driverName = '';
        $this->errorMessage = null;
        $this->driverNameError = null;
    }

    public function cancelSession(): void
    {
        session()->forget(['kiosk_card', 'kiosk_user', 'kiosk_verified_at']);
        $this->redirect(route('menu.options'), navigate: true);
    }

    public function confirmQueue(): void
    {
        if (! $this->selectedVehicle || $this->isProcessing) {
            return;
        }

        $this->driverNameError = null;
        $trimmedDriverName = trim($this->driverName);

        if ($trimmedDriverName === '') {
            $this->driverNameError = 'Enter the name of the driver taking this trip.';
            return;
        }

        $fee = (float) ($this->selectedVehicle['queueing_fee'] ?? 0.00);

        if ($this->currentBalance < $fee) {
            $this->errorMessage = "Insufficient card balance. Available: ₱" . number_format($this->currentBalance, 2) . " | Required: ₱" . number_format($fee, 2);
            return;
        }

        $this->isProcessing = true;
        $this->errorMessage = null;

        $baseUrl = config('services.smarticct.api_url', 'https://smarticct.app');

        try {
            $response = Http::withoutVerifying()
                ->acceptJson()
                ->timeout(8)
                ->post("{$baseUrl}/api/cards/tap", [
                    'uid'              => $this->card['uid'],
                    'vehicle_id'       => $this->selectedVehicle['id'],
                    'driver_name'      => $trimmedDriverName,
                    'transaction_type' => 'operator_payment',
                    'amount'           => $fee,
                    'destination'      => $this->selectedVehicle['destination'],
                    'vehicle_type'     => $this->selectedVehicle['vehicle_type'],
                    'plate_number'     => $this->selectedVehicle['plate_number'],
                ]);

            $result = $response->json();

            if (($result['success'] ?? false) === true) {
                $newBalance = $result['balance_after'] ?? $this->balanceAfterDeduction;
                $this->card['balance'] = $newBalance;
                session(['kiosk_card' => $this->card]);

                $receiptData = [
                    'date'          => now()->format('m/d/y h:i A'),
                    'reference_no'  => $result['reference_no'] ?? ('KIOSK-' . now()->timestamp),
                    'operator_name' => $this->user['name'] ?? 'Unknown',
                    'driver_name'   => $trimmedDriverName,
                    'plate_number'  => $this->selectedVehicle['plate_number'],
                    'vehicle_type'  => $this->selectedVehicle['vehicle_type'],
                    'destination'   => $this->selectedVehicle['destination'] ?? 'N/A',
                    'fee'           => $fee,
                ];

                try {
                    ThermalReceiptService::printKioskQueueSlip($receiptData);
                } catch (\Throwable $printError) {
                    // Don't let a printer/receipt failure look like a failed transaction —
                    // the money has already moved and the vehicle is already queued.
                    Log::error('Kiosk receipt print failed after successful tap', [
                        'error' => $printError->getMessage(),
                        'reference_no' => $receiptData['reference_no'],
                    ]);
                }

                // The menu screen shows the full-screen "take your ticket" confirmation
                // from this flash (replaces the old corner toast). Nothing else changed here.
                session()->flash('kiosk_done', [
                    'kind'    => 'queue',
                    'receipt' => $receiptData,
                ]);

                $this->redirect(route('menu.options'), navigate: true);
                return;
            }

            $this->errorMessage = $result['message'] ?? 'Unable to queue vehicle. Please try again.';

        } catch (\Exception $e) {
            Log::error('Kiosk operator queue tap failed', ['error' => $e->getMessage()]);
            $this->errorMessage = 'Communication error with live server. Please try again.';
        } finally {
            $this->isProcessing = false;
        }
    }
};
?>

@php
    $registeredDriver = $this->selectedVehicle['driver_name'] ?? null;
    $selectedFee      = (float) ($this->selectedVehicle['queueing_fee'] ?? 0);
    $canQueue         = $this->selectedVehicle && $this->currentBalance >= $selectedFee;
@endphp

<div
    class="flex min-h-0 flex-1 flex-col"
    x-data="{
        confirming: false,
        hasName() { return (this.$wire.driverName || '').trim() !== ''; }
    }"
    x-init="
        $watch('$wire.errorMessage', v => { if (v) confirming = false });
        $watch('$wire.driverNameError', v => { if (v) confirming = false });
    "
>
    <div class="shrink-0 px-8 pb-3.5 pt-5">
        <h1 class="font-primary text-[38px] font-extrabold leading-tight text-white">Which vehicle are you queueing?</h1>
        <p class="mt-1 font-secondary text-xl text-tx-2">
            Operator: <span class="font-semibold text-white">{{ $user['name'] ?? 'Unknown' }}</span>
            &bull; Registered units: {{ count($vehicles) }}
        </p>
    </div>

    @if ($errorMessage)
        <div class="mx-8 mb-3 flex items-start gap-3 rounded-2xl border-2 border-stop/50 bg-stop/10 p-4">
            <flux:icon name="exclamation-circle" class="mt-0.5 size-7 shrink-0 text-stop" />
            <div>
                <span class="block font-primary text-xl font-extrabold text-stop">Queue Denied</span>
                <span class="font-secondary text-lg text-stop">{{ $errorMessage }}</span>
            </div>
        </div>
    @endif

    <div class="grid min-h-0 flex-1 grid-cols-[1.3fr_1fr] gap-6 px-8 pb-4">

        {{-- Left: the operator's vehicles --}}
        <div class="min-h-0 space-y-3 overflow-y-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @forelse ($vehicles as $vehicle)
                @php
                    $isSelected = $selectedVehicleId === (int) $vehicle['id'];
                    $fee        = (float) ($vehicle['queueing_fee'] ?? 0.00);
                    $canAfford  = $this->currentBalance >= $fee;
                @endphp
                <button
                    type="button"
                    wire:click="selectVehicle({{ $vehicle['id'] }})"
                    class="relative grid h-[132px] w-full grid-cols-[minmax(0,1fr)_auto] items-center gap-4 overflow-hidden rounded-3xl border-2 px-6 text-left transition {{ $isSelected ? 'border-secondary bg-k-700 ring-2 ring-secondary' : ($canAfford ? 'border-white/15 bg-k-800' : 'border-stop/50 bg-k-800') }}"
                >
                    @if ($isSelected)
                        <span class="absolute right-0 top-0 grid size-9 place-items-center rounded-bl-2xl bg-secondary text-primary">
                            <flux:icon name="check" class="size-6 stroke-[2.5]" />
                        </span>
                    @endif

                    <div class="min-w-0">
                        <div class="flex items-center gap-3">
                            <x-kiosk.plate lg>{{ $vehicle['plate_number'] }}</x-kiosk.plate>
                            <x-kiosk.vehicle-type :type="$vehicle['vehicle_type']" />
                        </div>
                        <div class="mt-2.5 flex items-center gap-2 font-secondary text-[22px] text-tx-2">
                            <flux:icon name="map-pin" class="size-6 shrink-0" />
                            <span class="truncate">{{ $vehicle['destination'] ?? 'Unassigned' }}</span>
                        </div>
                    </div>

                    <div class="text-right">
                        <small class="block font-secondary text-base leading-none text-tx-3">Queue fee</small>
                        <b class="font-primary text-4xl font-extrabold tabular-nums text-white">₱{{ number_format($fee, 2) }}</b>
                        @unless ($canAfford)
                            <div class="whitespace-nowrap font-secondary text-base font-bold text-stop">Not enough balance</div>
                        @endunless
                    </div>
                </button>
            @empty
                <div class="rounded-3xl border-2 border-white/15 bg-k-800 p-10 text-center">
                    <flux:icon name="truck" class="mx-auto mb-3 size-12 text-tx-3" />
                    <h2 class="font-primary text-2xl font-extrabold text-white">No registered vehicles found</h2>
                    <p class="mt-2 font-secondary text-xl text-tx-2">
                        There are no vehicles linked to your operator account. Please contact dispatch or the terminal administrator.
                    </p>
                </div>
            @endforelse
        </div>

        {{-- Right: driver + details for the chosen vehicle --}}
        <div class="min-h-0">
            @if (! $this->selectedVehicle)
                <div class="flex h-full flex-col items-center justify-center gap-4 rounded-3xl border-2 border-white/15 bg-k-800 p-8 text-center">
                    <flux:icon name="truck" class="size-14 text-tx-3" />
                    <p class="font-secondary text-2xl text-tx-2">Choose a vehicle to queue</p>
                </div>
            @else
                <div class="flex h-full flex-col gap-4 overflow-y-auto rounded-3xl border-2 border-white/15 bg-k-800 p-5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <h2 class="font-primary text-[26px] font-extrabold text-white">Driver on duty</h2>
                            <button type="button" wire:click="clearSelection" class="rounded-xl px-3 py-2 font-secondary text-lg font-semibold text-tx-2" aria-label="Remove selection">Clear</button>
                        </div>

                        <label for="driver-name" class="mb-2 block font-secondary text-lg font-semibold text-tx-2">Driver's full name</label>
                        <div class="flex h-[68px] items-center gap-3 rounded-[1.125rem] border-2 bg-k-700 px-5 focus-within:border-secondary focus-within:ring-[3px] focus-within:ring-secondary/30 {{ $driverNameError ? 'border-stop' : 'border-white/30' }}">
                            <flux:icon name="user" class="size-6 shrink-0 text-tx-3" />
                            <input
                                id="driver-name"
                                type="text"
                                wire:model="driverName"
                                autocomplete="off"
                                placeholder="Enter the driver's full name"
                                class="h-full w-full bg-transparent font-secondary text-[24px] text-white placeholder:text-tx-3 focus:outline-none"
                            >
                        </div>

                        @if ($driverNameError)
                            <p class="mt-2 font-secondary text-lg font-semibold text-stop">{{ $driverNameError }}</p>
                        @else
                            <p class="mt-2 font-secondary text-lg leading-snug text-tx-3">
                                {{ $registeredDriver ? 'Pre-filled with the registered driver. Change it if someone else is driving.' : "Used to record this trip's earnings for the correct driver." }}
                            </p>
                        @endif
                    </div>

                    <dl class="mt-auto divide-y divide-white/15 text-xl">
                        <div class="flex items-center justify-between gap-4 py-2.5"><dt class="text-tx-2">Destination</dt><dd class="font-bold text-white">{{ $this->selectedVehicle['destination'] ?? 'N/A' }}</dd></div>
                        <div class="flex items-center justify-between gap-4 py-2"><dt class="text-tx-2">Vehicle</dt><dd><x-kiosk.vehicle-type :type="$this->selectedVehicle['vehicle_type']" /></dd></div>
                        <div class="flex items-center justify-between gap-4 py-2.5"><dt class="text-tx-2">Seats</dt><dd class="font-bold text-white">{{ $this->selectedVehicle['total_seats'] }}</dd></div>
                    </dl>
                </div>
            @endif
        </div>
    </div>

    <x-kiosk.action-bar>
        <x-slot:back>
            <x-kiosk.button variant="second" href="{{ route('menu.options') }}" wire:navigate>
                <flux:icon name="arrow-left" class="size-7" /> Back
            </x-kiosk.button>
        </x-slot:back>

        @if (! $this->selectedVehicle)
            <span wire:key="q-empty" class="font-secondary text-[22px] text-tx-3">Choose a vehicle to see the fee</span>
        @elseif (! $canQueue)
            <div wire:key="q-warn" class="flex items-center gap-3 font-primary text-xl font-bold text-stop">
                <flux:icon name="exclamation-triangle" class="size-7" /> Not enough balance. Add funds at the cashier.
            </div>
        @else
            <div wire:key="q-math" class="flex items-center gap-5">
                <div><small class="block font-secondary text-base leading-tight text-tx-3">Card balance</small><b class="font-primary text-2xl font-extrabold tabular-nums text-white">₱{{ number_format($this->currentBalance, 2) }}</b></div>
                <span class="mt-3.5 text-2xl font-bold text-tx-3">−</span>
                <div><small class="block font-secondary text-base leading-tight text-tx-3">Queue fee</small><b class="font-primary text-2xl font-extrabold tabular-nums text-white">₱{{ number_format($selectedFee, 2) }}</b></div>
                <span class="mt-3.5 text-2xl font-bold text-tx-3">=</span>
                <div><small class="block font-secondary text-base leading-tight text-tx-3">After</small><b class="font-primary text-2xl font-extrabold tabular-nums text-go">₱{{ number_format($this->balanceAfterDeduction, 2) }}</b></div>
            </div>
        @endif

        <x-slot:primary>
            @if ($canQueue)
                <x-kiosk.button wire:key="q-cta-go" size="xl" class="w-[290px]" x-show="hasName()" @click="confirming = true">Queue vehicle</x-kiosk.button>
                <x-kiosk.button wire:key="q-cta-name" variant="off" size="xl" class="w-[290px]" x-show="!hasName()" x-cloak @click="document.getElementById('driver-name')?.focus()">Add driver name</x-kiosk.button>
            @else
                <x-kiosk.button wire:key="q-cta-off" variant="off" size="xl" class="w-[290px]" disabled>{{ $this->selectedVehicle ? 'Not enough balance' : 'Choose a vehicle' }}</x-kiosk.button>
            @endif
        </x-slot:primary>
    </x-kiosk.action-bar>

    {{-- Confirm queue entry --}}
    @if ($this->selectedVehicle)
        <div x-show="confirming" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-k-950/80 p-6">
            <div class="w-[700px] rounded-[2rem] border-2 border-white/30 bg-k-800 p-9 shadow-2xl">
                <h2 class="font-primary text-[34px] font-extrabold leading-tight text-white">Queue this vehicle?</h2>
                <p class="mt-2 font-secondary text-xl text-tx-2">Please verify the vehicle and driver. The queueing fee will be deducted from your card.</p>

                <div class="mb-2 mt-4 flex items-center gap-3">
                    <flux:icon name="map-pin" class="size-8 text-secondary" />
                    <span class="font-primary text-[32px] font-extrabold text-white">Iriga → {{ $this->selectedVehicle['destination'] ?? 'N/A' }}</span>
                </div>
                <div class="mb-3 flex items-center gap-3">
                    <x-kiosk.plate>{{ $this->selectedVehicle['plate_number'] }}</x-kiosk.plate>
                    <x-kiosk.vehicle-type :type="$this->selectedVehicle['vehicle_type']" />
                </div>

                <dl class="divide-y divide-white/15 text-[22px]">
                    <div class="flex items-center justify-between gap-4 py-3"><dt class="text-tx-2">Driver on duty</dt><dd class="truncate font-bold text-white" x-text="$wire.driverName || '—'">—</dd></div>
                    <div class="flex items-center justify-between gap-4 py-3"><dt class="text-tx-2">Queueing fee</dt><dd class="font-primary text-[38px] font-extrabold text-secondary tabular-nums">₱{{ number_format($selectedFee, 2) }}</dd></div>
                </dl>

                <div class="mt-6 flex gap-4">
                    <x-kiosk.button variant="second" size="xl" class="w-[210px]" @click="confirming = false" wire:loading.attr="disabled" wire:target="confirmQueue">Cancel</x-kiosk.button>
                    <x-kiosk.button size="xl" class="flex-1" wire:click="confirmQueue" wire:loading.attr="disabled" wire:target="confirmQueue">
                        <span wire:loading.remove wire:target="confirmQueue">Confirm &amp; deduct fee</span>
                        <span wire:loading.inline-flex wire:target="confirmQueue" class="items-center gap-3">
                            <flux:icon name="arrow-path" class="size-7 animate-spin" /> Processing queue...
                        </span>
                    </x-kiosk.button>
                </div>
            </div>
        </div>
    @endif
</div>