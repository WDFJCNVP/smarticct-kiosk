<?php

use Livewire\Component;

new class extends Component
{
    public array $card = [];
    public array $user = [];

    public function mount()
    {
        if (! session()->has('kiosk_card')) {
            $this->redirect(route('login.options'), navigate: true);
            return;
        }

        $this->card = session('kiosk_card', []);
        $this->user = session('kiosk_user', []);

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
@endphp

<div class="flex min-h-full w-full flex-1 flex-col items-center justify-center p-6 sm:p-10">
    <div class="w-full max-w-3xl space-y-8">

        {{-- Identity strip: who the kiosk thinks you are. Balance used to live
             here too, but that made it show up in two places at once (here
             AND on the "Check Card Balance" screen) — one source of truth,
             so it only lives behind the tile now. --}}
        <div class="flex items-center gap-3 rounded-2xl border border-white/15 bg-white/8 px-5 py-4 backdrop-blur-md">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-secondary/15 ring-1 ring-secondary/30">
                <flux:icon name="{{ $isOperator ? 'truck' : 'user' }}" class="size-5 text-secondary" />
            </div>
            <div class="min-w-0">
                <p class="truncate font-primary text-lg font-bold text-white sm:text-xl">
                    {{ $user['name'] ?? 'Cardholder' }}
                </p>
                <p class="font-secondary text-xs uppercase tracking-wide text-white/50">
                    {{ $user['role'] ?? 'Cardholder' }}
                </p>
            </div>
        </div>

        {{-- Hero action. Whatever this person came to the kiosk to do, it's
             this — so it gets the whole width and none of the competition. --}}
        <flux:button
            href="{{ $isOperator ? route('queue.vehicle') : route('route.select') }}"
            wire:navigate
            variant="primary"
            class="kiosk-tap-target !h-40 w-full !flex-col !gap-3 !rounded-3xl !bg-secondary !text-2xl !font-bold !text-primary !shadow-lg !shadow-secondary/25 transition hover:!bg-secondary-hover sm:!h-44 sm:!text-3xl"
        >
            <flux:icon name="{{ $isOperator ? 'truck' : 'ticket' }}" class="size-14" />
            {{ $isOperator ? 'Queue Vehicle' : 'Pay Fare' }}
        </flux:button>

        {{-- Secondary actions: deliberately smaller and quieter than the hero.
             These are "while I'm here" tasks, not why anyone walked up. --}}
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

            <flux:button
                href="{{ route('view.balance') }}"
                wire:navigate
                variant="ghost"
                class="kiosk-tap-target !h-28 !flex-col !gap-2 !rounded-2xl !border !border-white/15 !bg-white/8 !text-sm !font-semibold !text-white !backdrop-blur-md transition hover:!border-white/25 hover:!bg-white/14 sm:!text-base"
            >
                <flux:icon name="credit-card" class="size-7 text-secondary sm:size-8" />
                Check Card Balance
            </flux:button>
        </div>

        {{-- Sign out — still the quietest action on the screen, but a proper
             bordered pill with breathing room instead of a squashed inline
             text link. --}}
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
</div>