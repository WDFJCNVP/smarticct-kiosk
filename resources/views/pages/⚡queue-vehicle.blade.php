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

    /**
     * Submit queueing request to the live cloud API. Driver name is required
     * here because the operator's earnings are tracked per-driver in the
     * back office, not per-vehicle.
     */
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

            if ($result['success'] === true) {
                $newBalance = $result['balance_after'] ?? $this->balanceAfterDeduction;
                $this->card['balance'] = $newBalance;
                    'date'          => now()->format('m/d/y h:i A'),
                    'operator_name' => $this->user['name'] ?? 'Unknown',
                    'driver_name'   => $this->selectedVehicle['driver_name'] ?? $this->user['name'],
                    'plate_number'  => $this->selectedVehicle['plate_number'],
                    'vehicle_type'  => $this->selectedVehicle['vehicle_type'],
                    'destination'   => $this->selectedVehicle['destination'] ?? 'N/A',
                    'fee'           => $fee,
                    'balance_after' => (float) $newBalance,
                ];

                //Print
                ThermalReceiptService::printKioskQueueSlip($receiptData);

                Flux::toast(
                    duration: 5000,
                    variant: 'success',
                    heading: 'Queued Successfully',
                    text: ($result['message'] ?? '') . " Please get your ticket!",
                );

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

<div class="mx-auto grid w-full max-w-7xl grid-cols-1 items-start gap-6 p-4 select-none sm:p-6 lg:grid-cols-3">

    {{-- Left: Operator Vehicles Lineup --}}
    <div class="space-y-6 lg:col-span-2">
        <div class="flex items-center justify-between border-b border-white/10 pb-4">
            <div>
                <flux:heading size="xl" class="font-primary font-black tracking-tight text-white drop-shadow-sm">
                    Select Vehicle to Queue
                </flux:heading>
                <flux:text class="text-sm text-white/60">
                    Operator: <span class="font-semibold text-white/85">{{ $user['name'] ?? 'Unknown' }}</span>
                    &bull; Registered Units: {{ count($vehicles) }}
                </flux:text>
            </div>
            <flux:button variant="ghost" size="sm" href="{{ route('menu.options') }}" wire:navigate icon="arrow-left" class="!text-white/70 hover:!text-white">
                Cancel &amp; Exit
            </flux:button>
        </div>

        @if ($errorMessage)
            <div class="flex items-start gap-3 rounded-xl border border-danger/30 bg-danger/10 p-4 backdrop-blur-md">
                <flux:icon name="exclamation-circle" class="mt-0.5 size-5 shrink-0 text-danger" />
                <div>
                    <span class="block text-sm font-bold text-danger">Queue Denied</span>
                    <span class="text-xs text-danger">{{ $errorMessage }}</span>
                </div>
            </div>
        @endif

        <div class="max-h-[65vh] space-y-4 overflow-y-auto pr-2">
            @forelse ($vehicles as $vehicle)
                @php
                    $isSelected = $selectedVehicleId === (int) $vehicle['id'];
                    $fee = (float) ($vehicle['queueing_fee'] ?? 0.00);
                    $canAfford = $this->currentBalance >= $fee;
                @endphp

                <flux:card
                    wire:click="selectVehicle({{ $vehicle['id'] }})"
                    class="!rounded-2xl !border-2 !bg-white/8 !backdrop-blur-md cursor-pointer transition-all duration-200 {{ $isSelected ? '!border-secondary !bg-secondary/10 shadow-md' : '!border-white/15 hover:!border-white/30' }} {{ ! $canAfford ? 'opacity-60' : '' }}"
                >
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xl font-black tracking-wider text-white">
                                    {{ $vehicle['plate_number'] }}
                                </span>
                                <flux:badge color="{{ $vehicle['vehicle_type'] === 'Bus' ? 'blue' : ($vehicle['vehicle_type'] === 'UV-express' ? 'green' : 'yellow') }}" size="sm">
                                    {{ $vehicle['vehicle_type'] }}
                                </flux:badge>
                                @if ($isSelected)
                                    <flux:badge color="zinc" size="sm" icon="check">Selected</flux:badge>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-white/60">
                                <span><strong class="text-white/85">Route:</strong> {{ $vehicle['destination'] ?? 'Unassigned' }}</span>
                                <span>&bull;</span>
                                <span><strong class="text-white/85">Seats:</strong> {{ $vehicle['total_seats'] }}</span>
                                <span>&bull;</span>
                                <span><strong class="text-white/85">Registered Driver:</strong> {{ $vehicle['driver_name'] ?? 'Not set' }}</span>
                            </div>
                        </div>

                        <div class="shrink-0 sm:text-right">
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-white/50">Queue Fee</span>
                            <span class="font-mono text-xl font-bold text-white">
                                ₱{{ number_format($fee, 2) }}
                            </span>
                            @if (! $canAfford)
                                <span class="block text-[11px] font-semibold text-danger">Insufficient Balance</span>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @empty
                <flux:card class="!rounded-3xl !border !border-white/15 !bg-white/8 p-10 text-center text-white/60 !backdrop-blur-md">
                    <flux:icon name="truck" class="mx-auto mb-2 size-10 opacity-50" />
                    <flux:heading size="lg" class="!text-white">No registered vehicles found</flux:heading>
                    <flux:text class="mt-1 text-sm text-white/60">
                        There are no vehicles linked to your operator account. Please contact dispatch or the terminal administrator.
                    </flux:text>
                </flux:card>
            @endforelse
        </div>
    </div>

    {{-- Right: Sticky Summary Card --}}
    <div class="space-y-4 lg:sticky lg:top-4 lg:self-start">
        <flux:card class="space-y-5 !rounded-3xl !border !border-white/15 !bg-white/8 !backdrop-blur-md">
            <div class="flex items-center justify-between">
                <flux:heading size="lg" class="!text-white">Queue Summary</flux:heading>
                @if ($selectedVehicleId)
                    <flux:button wire:click="clearSelection" variant="ghost" size="sm" icon="x-mark" aria-label="Remove selection" class="!text-white/70 hover:!text-white" />
                @endif
            </div>

            @if (! $this->selectedVehicle)
                <div class="flex flex-col items-center gap-2 py-10 text-center text-white/60">
                    <flux:icon name="ticket" class="size-8 opacity-40" />
                    <flux:text class="!text-white/60">Select a vehicle on the left to review queue details</flux:text>
                </div>
            @else
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <flux:text class="!text-white/60">Plate Number</flux:text>
                        <span class="font-mono font-bold text-white">{{ $this->selectedVehicle['plate_number'] }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <flux:text class="!text-white/60">Vehicle Type</flux:text>
                        <span class="font-medium text-white">{{ $this->selectedVehicle['vehicle_type'] }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <flux:text class="!text-white/60">Terminal Destination</flux:text>
                        <span class="font-medium text-white">{{ $this->selectedVehicle['destination'] ?? 'N/A' }}</span>
                    </div>

                    <flux:separator class="!border-white/10" />

                    {{-- Driver name — required. This is what ties the trip's
                         earnings to the correct driver in the back office. --}}
                    <flux:field>
                        <flux:label class="!text-white/80">Driver on Duty</flux:label>
                        <flux:input
                            wire:model="driverName"
                            placeholder="Enter driver's full name"
                            icon="user"
                            required
                        />
                        @if ($driverNameError)
                            <flux:error>{{ $driverNameError }}</flux:error>
                        @else
                            <flux:description class="!text-white/50">Used to record this trip's earnings for the correct driver.</flux:description>
                        @endif
                    </flux:field>

                    <flux:separator class="!border-white/10" />

                    <div class="flex items-center justify-between text-sm">
                        <flux:text class="!text-white/60">Current Card Balance</flux:text>
                        <span class="font-mono text-white">₱{{ number_format($this->currentBalance, 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <flux:text class="!text-white/60">Queueing Fee</flux:text>
                        <span class="font-mono font-bold text-danger">- ₱{{ number_format($this->selectedVehicle['queueing_fee'], 2) }}</span>
                    </div>

                    <div class="flex items-center justify-between pt-1 text-sm">
                        <flux:text class="!text-white/60">Remaining Balance</flux:text>
                        <span class="font-mono font-bold text-success">₱{{ number_format($this->balanceAfterDeduction, 2) }}</span>
                    </div>

                    <flux:separator class="!border-white/10" />

                    <div class="flex items-center justify-between">
                        <flux:heading size="base" class="!text-white">Total Fee</flux:heading>
                        <flux:heading size="xl" class="font-black !text-secondary">
                            ₱{{ number_format($this->selectedVehicle['queueing_fee'], 2) }}
                        </flux:heading>
                    </div>
                </div>
            @endif

            <flux:modal.trigger name="confirm-queue-modal">
                <flux:button
                    variant="primary"
                    class="kiosk-tap-target w-full !bg-secondary !font-bold !text-primary hover:!bg-secondary-hover"
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
                <flux:text class="mt-2 text-sm text-light-txt-muted dark:text-dark-txt-muted">
                    Please verify the vehicle and driver before confirming. The queueing fee will be deducted from your card.
                </flux:text>
            </div>

            @if ($this->selectedVehicle)
                <div class="space-y-3 rounded-xl border border-light-bd-default bg-light-subtle p-4 dark:border-dark-bd-default dark:bg-dark-subtle/60">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-light-txt-muted dark:text-dark-txt-muted">Plate Number</span>
                        <span class="font-mono font-bold text-light-txt-primary dark:text-dark-txt-primary">{{ $this->selectedVehicle['plate_number'] }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-light-txt-muted dark:text-dark-txt-muted">Vehicle Type</span>
                        <span class="font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $this->selectedVehicle['vehicle_type'] }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-light-txt-muted dark:text-dark-txt-muted">Destination</span>
                        <span class="font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $this->selectedVehicle['destination'] }}</span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-light-txt-muted dark:text-dark-txt-muted">Driver on Duty</span>
                        <span class="font-medium text-light-txt-primary dark:text-dark-txt-primary">{{ $driverName ?: '—' }}</span>
                    </div>

                    <flux:separator />

                    <div class="flex items-center justify-between text-base">
                        <span class="font-bold">Queueing Fee</span>
                        <span class="font-mono font-bold text-success dark:text-dark-success">₱{{ number_format($this->selectedVehicle['queueing_fee'], 2) }}</span>
                    </div>
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="confirmQueue" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="confirmQueue">Confirm &amp; Deduct Fee</span>
                    <span wire:loading wire:target="confirmQueue">Processing Queue...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
