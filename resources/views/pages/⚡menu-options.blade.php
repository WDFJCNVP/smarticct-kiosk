<?php

use Livewire\Component;

new class extends Component
{
    public array $card = [];
    public array $user = [];
    public ?array $done = null; // set by a just-completed payment / queue entry (see x-kiosk.done)

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
        session()->forget(['kiosk_card', 'kiosk_user', 'kiosk_vehicle', 'kiosk_route_list', 'kiosk_verified_at']);
        $this->redirect(route('kiosk.home'), navigate: true);
    }
};
?>

@php
    $isOperator = ($user['role'] ?? '') === 'operator';
    $firstName  = \Illuminate\Support\Str::of($user['name'] ?? 'Cardholder')->before(' ');
    $tile = 'kiosk-tap-target flex h-[150px] flex-col items-start justify-between rounded-3xl border-2 border-white/30 bg-k-800 p-6 text-left transition hover:bg-k-700';
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

            {{-- Hero action: whatever this person came to do --}}
            <a
                href="{{ $isOperator ? route('queue.vehicle') : route('route.select') }}"
                wire:navigate
                class="kiosk-tap-target flex h-[250px] flex-col items-start justify-between rounded-[1.75rem] bg-secondary p-8 text-left text-primary shadow-[0_10px_0_rgba(0,0,0,.3)] transition hover:bg-secondary-hover"
            >
                <flux:icon name="{{ $isOperator ? 'truck' : 'ticket' }}" class="size-16" />
                <div>
                    <div class="font-primary text-5xl font-extrabold leading-tight tracking-tight">{{ $isOperator ? 'Queue a vehicle' : 'Pay fare' }}</div>
                    <div class="mt-1 font-secondary text-2xl font-medium text-primary/85">
                        {{ $isOperator ? 'Pick a vehicle and pay the queue fee' : 'Pick a destination and pay from your card' }}
                    </div>
                </div>
            </a>

            <div class="grid grid-cols-3 gap-5">
                <a href="{{ route('view.routes') }}" wire:navigate class="{{ $tile }}">
                    <flux:icon name="map" class="size-10 text-secondary" />
                    <div>
                        <div class="font-primary text-2xl font-extrabold text-white">Routes &amp; fares</div>
                        <div class="font-secondary text-lg text-tx-3">All destinations</div>
                    </div>
                </a>

                <a href="{{ route('guest.queue') }}" wire:navigate class="{{ $tile }}">
                    <flux:icon name="queue-list" class="size-10 text-secondary" />
                    <div>
                        <div class="font-primary text-2xl font-extrabold text-white">Live queue</div>
                        <div class="font-secondary text-lg text-tx-3">What's boarding now</div>
                    </div>
                </a>

                <a href="{{ route('view.balance') }}" wire:navigate class="{{ $tile }}">
                    <flux:icon name="credit-card" class="size-10 text-secondary" />
                    <div>
                        <div class="font-primary text-2xl font-extrabold text-white">My card</div>
                        <div class="font-secondary text-lg text-tx-3">Balance &amp; details</div>
                    </div>
                </a>
            </div>
        </div>
    @endif
</div>
