<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

new class extends Component
{
    public array $card = [];
    public array $user = [];
    public ?array $done = null;

    #[Validate('required|digits:6')]
    public ?string $pin_number = null;

    public ?string $pinError = null;

    public function verifyPin(): void
    {
        $this->pinError = null;
        $this->validateOnly('pin_number');

        $baseUrl = config('services.smarticct.api_url', 'https://smarticct.app');

        try {
            $response = Http::withoutVerifying()
                ->acceptJson()
                ->timeout(6)
                ->post("{$baseUrl}/api/card/verify", [
                    'pin'     => $this->pin_number,
                    'user_id' => $this->user['id'] ?? null,
                ]);

            $this->pin_number = null; 

            if ($response->successful()) {
                session(['kiosk_pin_verified_at' => now()->timestamp]);

                Flux::modal('verify-pin-modal')->close();
                $this->redirect(route('view.balance'), navigate: true);
                return;
            }

            $this->pinError = $response->json('message') ?? 'Invalid PIN number.';

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Kiosk balance PIN check: connection failed', ['error' => $e->getMessage()]);
            $this->pinError = 'Unable to connect to the server. Please try again.';
        } catch (\Throwable $e) {
            Log::error('Kiosk balance PIN check: unexpected error', ['error' => $e->getMessage()]);
            $this->pinError = 'Something went wrong. Please try again or contact staff.';
        }
    }

    public function mount()
    {
        if (! session()->has('kiosk_card')) {
            $this->redirect(route('login.options'), navigate: true);
            return;
        }

        $this->card = session('kiosk_card', []);
        $this->user = session('kiosk_user', []);
        $this->done = session('kiosk_done');

        if (session('queue_success')) {
            Flux::toast(
                variant: 'success',
                heading: 'Queued Successfully',
                text: 'Your vehicle has been successfully queued',
            );
        }
    }

    public function signOut(): void
    {
        session()->forget(['kiosk_card', 'kiosk_user', 'kiosk_vehicle', 'kiosk_route_list', 'kiosk_verified_at', 'kiosk_pin_verified_at']);
        $this->redirect(route('kiosk.home'), navigate: true);
    }
};
?>

@php
    $isOperator = ($user['role'] ?? '') === 'operator';
    $firstName  = \Illuminate\Support\Str::of($user['name'] ?? 'Cardholder')->before(' ');
@endphp

<div class="flex min-h-0 flex-1 flex-col">
    @if ($done)
        <x-kiosk.done :done="$done" :is-operator="$isOperator" />
    @else
        {{-- The balance is deliberately NOT on this screen — anyone standing behind you can
             read it. It lives on "My Card", one tap away. --}}
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col justify-center gap-6 px-10 py-8">

            <div class="flex items-center justify-between gap-4">
                <h1 class="font-primary text-[38px] font-extrabold text-white">Hello, {{ $firstName }}</h1>
                <span class="rounded-full border border-white/25 bg-k-800 px-4 py-1.5 font-secondary text-lg font-semibold capitalize text-tx-2">
                    {{ $user['role'] ?? 'Cardholder' }}
                </span>
            </div>

            {{-- Hero action --}}
            <flux:button
                href="{{ $isOperator ? route('queue.vehicle') : route('route.select') }}"
                wire:navigate
                variant="primary"
                class="kiosk-tap-target !h-40 w-full !flex-col !gap-3 !rounded-3xl !bg-secondary !text-2xl !font-bold !text-primary !shadow-lg !shadow-secondary/25 transition hover:!bg-secondary-hover sm:!h-44 sm:!text-3xl"
            >
                <flux:icon name="{{ $isOperator ? 'truck' : 'ticket' }}" class="size-14" />
                {{ $isOperator ? 'Queue Vehicle' : 'Pay Fare' }}
            </flux:button>

            {{-- Secondary actions --}}
            <div class="grid grid-cols-3 gap-3 sm:gap-4">
                <flux:button
                    href="{{ route('view.routes') }}"
                    wire:navigate
                    variant="ghost"
                    class="kiosk-tap-target !h-28 !flex-col !gap-2 !rounded-2xl !border !border-white/15 !bg-white/8 !text-sm !font-semibold !text-white !backdrop-blur-md transition hover:!border-white/25 hover:!bg-white/14 sm:!text-base"
                >
                    <flux:icon name="map" class="size-7 text-secondary sm:size-8" />
                    Routes &amp; Fares
                </flux:button>

                <flux:button
                    href="{{ route('guest.queue') }}"
                    wire:navigate
                    variant="ghost"
                    class="kiosk-tap-target !h-28 !flex-col !gap-2 !rounded-2xl !border !border-white/15 !bg-white/8 !text-sm !font-semibold !text-white !backdrop-blur-md transition hover:!border-white/25 hover:!bg-white/14 sm:!text-base"
                >
                    <flux:icon name="queue-list" class="size-7 text-secondary sm:size-8" />
                    Live Queue
                </flux:button>

                <flux:modal.trigger name="verify-pin-modal">
                    <flux:button
                        variant="ghost"
                        class="kiosk-tap-target !h-28 !flex-col !gap-2 !rounded-2xl !border !border-white/15 !bg-white/8 !text-sm !font-semibold !text-white !backdrop-blur-md transition hover:!border-white/25 hover:!bg-white/14 sm:!text-base"
                    >
                        <flux:icon name="credit-card" class="size-7 text-secondary sm:size-8" />
                        Check Card Balance
                    </flux:button>
                </flux:modal.trigger>
            </div>

            <div class="flex justify-center">
                <flux:button
                    wire:click="signOut"
                    variant="ghost"
                    class="!h-14 !w-full !max-w-xs !rounded-2xl !border !border-white/10 !bg-white/5 !text-base !font-semibold !text-white/70 transition hover:!border-danger/30 hover:!bg-danger/10 hover:!text-danger"
                >
                    <span class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-right-start-on-rectangle" class="size-5" />
                        Sign Out
                    </span>
                </flux:button>
            </div>

        </div>

        {{-- PIN Verification Modal --}}
        <flux:modal name="verify-pin-modal" class="min-w-[24rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Enter your PIN to continue</flux:heading>
                    <flux:text class="mt-2 text-sm text-light-txt-muted dark:text-dark-txt-muted">
                        Enter the 6-digit PIN for your card to view your balance.
                    </flux:text>
                </div>

                @if ($pinError)
                    <p class="font-secondary text-sm text-danger dark:text-dark-danger">{{ $pinError }}</p>
                @endif

                <flux:field>
                    <flux:input
                        wire:model="pin_number"
                        wire:keydown.enter="verifyPin"
                        type="password"
                        viewable
                        maxlength="6"
                        pattern="[0-9]*"
                        inputmode="numeric"
                        label="PIN"
                    />
                    <flux:error name="pin_number" />
                </flux:field>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="verifyPin" wire:loading.attr="disabled" wire:target="verifyPin">
                        <span wire:loading.remove wire:target="verifyPin">Verify PIN</span>
                        <span wire:loading wire:target="verifyPin">Verifying...</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>