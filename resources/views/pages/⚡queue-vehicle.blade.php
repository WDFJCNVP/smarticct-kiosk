<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

new class extends Component
{
    public array $card = [];
    public array $user = [];
    public array $vehicles = [];
    public ?int $selectedVehicleId = null;
    public ?string $errorMessage = null;
    public bool $isProcessing = false;

    public function mount(): void
    {
        // 1. Guard: Ensure active session exists from card tap
        if (! session()->has('kiosk_card') || ! session()->has('kiosk_user')) {
            $this->redirect(route('login.tap'), navigate: true);
            return;
        }

        $this->card = session('kiosk_card', []);
        $this->user = session('kiosk_user', []);

        // 2. Guard: Ensure the authenticated cardholder is an operator
        if (($this->user['role'] ?? '') !== 'operator') {
            $this->redirect(route('login.tap'), navigate: true);
            return;
        }

        $this->vehicles = $this->user['vehicles'] ?? [];

        // Auto-select first vehicle if only one vehicle exists
        if (count($this->vehicles) === 1) {
            $this->selectedVehicleId = (int) $this->vehicles[0]['id'];
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
    }

    public function clearSelection(): void
    {
        $this->selectedVehicleId = null;
        $this->errorMessage = null;
    }

    public function cancelSession(): void
    {
        session()->forget(['kiosk_card', 'kiosk_user', 'kiosk_verified_at']);
        $this->redirect(route('menu.options'), navigate: true);
    }

    /**
     * Submit queueing request to the live cloud API.
     */
    public function confirmQueue(): void
    {
        if (! $this->selectedVehicle || $this->isProcessing) {
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
                    'driver_name'      => $this->selectedVehicle['driver_name'] ?? $this->user['name'],
                    'transaction_type' => 'operator_payment',
                    'amount'           => $fee,
                    'destination'      => $this->selectedVehicle['destination'],
                    'vehicle_type'     => $this->selectedVehicle['vehicle_type'],
                    'plate_number'     => $this->selectedVehicle['plate_number'],
                ]);

            $result = $response->json();

            // dd($result);

            if ($result['success'] === true){
                
                $this->card['balance'] = $result['balance_after'] ?? $this->balanceAfterDeduction;
                session(['kiosk_card' => $this->card]);

                Flux::toast(
                    duration : 5000,
                    variant: 'success',
                    heading: 'Queued Successfully',
                    text: $result['message'] . "Please get your ticket!",
                );

                $this->redirect(route('menu.options'), navigate: true);

                return;
            } 

            // Cloud API returned a business validation failure
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

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3 select-none p-4 sm:p-6 max-w-7xl mx-auto min-h-screen items-start">

    {{-- Left: Operator Vehicles Lineup --}}
    <div class="space-y-6 lg:col-span-2">
        <div class="flex items-center justify-between pb-4 border-b border-zinc-200 dark:border-zinc-800">
            <div>
                <flux:heading size="xl" class="font-black tracking-tight">Select Vehicle to Queue</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    Operator: <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $user['name'] ?? 'Unknown' }}</span>
                    &bull; Registered Units: {{ count($vehicles) }}
                </flux:text>
            </div>
            <flux:button variant="ghost" size="sm" href="{{ route('menu.options') }}" wire:navigate icon="arrow-left">
                Cancel &amp; Exit
            </flux:button>
        </div>

        @if ($errorMessage)
            <div class="p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 flex items-start gap-3">
                <flux:icon name="exclamation-circle" class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
                <div>
                    <span class="text-sm font-bold text-red-800 dark:text-red-300 block">Queue Denied</span>
                    <span class="text-xs text-red-700 dark:text-red-400">{{ $errorMessage }}</span>
                </div>
            </div>
        @endif

        <div class="space-y-4 max-h-[75vh] overflow-y-auto pr-2">
            @forelse ($vehicles as $vehicle)
                @php
                    $isSelected = $selectedVehicleId === (int) $vehicle['id'];
                    $fee = (float) ($vehicle['queueing_fee'] ?? 0.00);
                    $canAfford = $this->currentBalance >= $fee;
                @endphp

                <flux:card
                    wire:click="selectVehicle({{ $vehicle['id'] }})"
                    class="cursor-pointer transition-all duration-200 border-2 {{ $isSelected ? 'border-primary bg-primary/5 dark:bg-primary/10 shadow-md' : 'hover:border-zinc-300 dark:hover:border-zinc-700' }} {{ ! $canAfford ? 'opacity-60' : '' }}"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        {{-- Vehicle Main Details --}}
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xl font-black tracking-wider text-zinc-900 dark:text-white">
                                    {{ $vehicle['plate_number'] }}
                                </span>
                                <flux:badge color="{{ $vehicle['vehicle_type'] === 'Bus' ? 'blue' : ($vehicle['vehicle_type'] === 'UV-express' ? 'green' : 'yellow') }}" size="sm">
                                    {{ $vehicle['vehicle_type'] }}
                                </flux:badge>
                                @if ($isSelected)
                                    <flux:badge color="zinc" size="sm" icon="check">Selected</flux:badge>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                <span><strong class="text-zinc-700 dark:text-zinc-300">Route:</strong> {{ $vehicle['destination'] ?? 'Unassigned' }}</span>
                                <span>&bull;</span>
                                <span><strong class="text-zinc-700 dark:text-zinc-300">Seats:</strong> {{ $vehicle['total_seats'] }}</span>
                                <span>&bull;</span>
                                <span><strong class="text-zinc-700 dark:text-zinc-300">Driver:</strong> {{ $vehicle['driver_name'] ?? 'Not assigned' }}</span>
                            </div>
                        </div>

                        {{-- Queueing Fee Badge --}}
                        <div class="sm:text-right shrink-0">
                            <span class="text-[11px] uppercase tracking-wider text-zinc-400 font-bold block">Queue Fee</span>
                            <span class="text-xl font-bold font-mono text-zinc-900 dark:text-white">
                                ₱{{ number_format($fee, 2) }}
                            </span>
                            @if (! $canAfford)
                                <span class="text-[11px] text-red-500 font-semibold block">Insufficient Balance</span>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @empty
                <flux:card class="p-10 text-center text-zinc-400">
                    <flux:icon name="truck" class="size-10 mx-auto mb-2 opacity-50" />
                    <flux:heading size="lg">No registered vehicles found</flux:heading>
                    <flux:text class="text-sm text-zinc-500 mt-1">
                        There are no vehicles linked to your operator account. Please contact dispatch or the terminal administrator.
                    </flux:text>
                </flux:card>
            @endforelse
        </div>
    </div>

    {{-- Right: Sticky Summary Card --}}
    <div class="lg:sticky lg:top-4 lg:self-start space-y-4">
        <flux:card class="space-y-5 border-zinc-200 dark:border-zinc-800">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Queue Summary</flux:heading>
                @if ($selectedVehicleId)
                    <flux:button
                        wire:click="clearSelection"
                        variant="ghost"
                        size="sm"
                        icon="x-mark"
                        aria-label="Remove selection"
                    />
                @endif
            </div>

            @if (! $this->selectedVehicle)
                <div class="flex flex-col items-center gap-2 py-10 text-center text-zinc-400">
                    <flux:icon name="ticket" class="size-8 opacity-40" />
                    <flux:text>Select a vehicle on the left to review queue details</flux:text>
                </div>
            @else
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <flux:text class="text-zinc-500">Plate Number</flux:text>
                        <span class="font-mono font-bold">{{ $this->selectedVehicle['plate_number'] }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <flux:text class="text-zinc-500">Vehicle Type</flux:text>
                        <span class="font-medium">{{ $this->selectedVehicle['vehicle_type'] }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <flux:text class="text-zinc-500">Terminal Destination</flux:text>
                        <span class="font-medium">{{ $this->selectedVehicle['destination'] ?? 'N/A' }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <flux:text class="text-zinc-500">Assigned Driver</flux:text>
                        <span class="font-medium">{{ $this->selectedVehicle['driver_name'] ?? 'N/A' }}</span>
                    </div>

                    <flux:separator />

                    <div class="flex items-center justify-between text-sm">
                        <flux:text class="text-zinc-500">Current Card Balance</flux:text>
                        <span class="font-mono">₱{{ number_format($this->currentBalance, 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <flux:text class="text-zinc-500">Queueing Fee</flux:text>
                        <span class="font-mono font-bold text-red-500">- ₱{{ number_format($this->selectedVehicle['queueing_fee'], 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm pt-1">
                        <flux:text class="text-zinc-500">Remaining Balance</flux:text>
                        <span class="font-mono font-bold text-emerald-600">₱{{ number_format($this->balanceAfterDeduction, 2) }}</span>
                    </div>

                    <flux:separator />

                    <div class="flex items-center justify-between">
                        <flux:heading size="base">Total Fee</flux:heading>
                        <flux:heading size="xl" class="font-black text-primary">
                            ₱{{ number_format($this->selectedVehicle['queueing_fee'], 2) }}
                        </flux:heading>
                    </div>
                </div>
            @endif

            <flux:modal.trigger name="confirm-queue-modal">
                <flux:button
                    variant="primary"
                    class="w-full font-bold"
                    :disabled="! $this->selectedVehicle || $this->currentBalance < (float)($this->selectedVehicle['queueing_fee'] ?? 0)"
                >
                    Confirm &amp; Queue Vehicle
                </flux:button>
            </flux:modal.trigger>
        </flux:card>
    </div>

    {{-- Confirmation Modal --}}
    <flux:modal name="confirm-queue-modal" class="min-w-[24rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Confirm Queue Entry</flux:heading>
                <flux:text class="mt-2 text-sm text-zinc-500">
                    Please verify your vehicle details before entering the queue. The queueing fee will be automatically deducted from your card.
                </flux:text>
            </div>

            @if ($this->selectedVehicle)
                <div class="space-y-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700/60">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">Plate Number</span>
                        <span class="font-mono font-bold text-zinc-900 dark:text-white">{{ $this->selectedVehicle['plate_number'] }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">Vehicle Type</span>
                        <span class="font-medium text-zinc-900 dark:text-white">{{ $this->selectedVehicle['vehicle_type'] }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">Destination</span>
                        <span class="font-medium text-zinc-900 dark:text-white">{{ $this->selectedVehicle['destination'] }}</span>
                    </div>

                    <flux:separator />

                    <div class="flex items-center justify-between text-base">
                        <span class="font-bold">Queueing Fee</span>
                        <span class="font-mono font-bold text-emerald-600">₱{{ number_format($this->selectedVehicle['queueing_fee'], 2) }}</span>
                    </div>
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button
                    variant="primary"
                    wire:click="confirmQueue"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="confirmQueue">Confirm &amp; Deduct Fee</span>
                    <span wire:loading wire:target="confirmQueue">Processing Queue...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>