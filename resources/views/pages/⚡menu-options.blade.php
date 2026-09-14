<?php

use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component
{

    public array $card = [];
    public array $user = [];


    // #[On('queue_success')]
    // public function queue_success() {
    //     flux::toast(
    //         duration : 0,
    //         variant : "success",
    //         heading : "Queued Successfully",
    //         text    : "Your vehicle has been successfully queued",
    //     );
    // }

    public function mount() {

        if (! session()->has('kiosk_card')) {
            $this->redirect(route('login.options'), navigate: true);
            return;
        }

        $this->card = session('kiosk_card', []);
        $this->user = session('kiosk_user', []);

        // dd(session('kiosk_vehicle'));

        if (session('queue_success')) {
            Flux::toast(
                variant: 'success',
                heading: 'Queued Successfully',
                text: 'Your vehicle has been successfully queued',
            );
        }

        // if (session('fare_success')) {
        //     Flux::toast(
        //         variant: 'success',
        //         heading: 'Fare Payment Successfully',
        //         text: 'Fare Payment Successfully. Please get your ticket!',
        //     );
        // }
    }

    public function signOut(): void
    {
        session()->forget(['kiosk_card', 'kiosk_user', 'kiosk_verified_at']);
        $this->redirect(route('login.options'), navigate: true);
    }      
};
?>

<div class="flex min-h-screen w-full items-center justify-center bg-zinc-950 p-8">
    <div class="w-full max-w-4xl space-y-10">

        <div class="text-center">
            <flux:heading size="xl" class="text-white">Welcome back, {{ $this->user['name'] }}</flux:heading>
            <flux:text class="mt-2 text-zinc-400">Please select an option below to continue</flux:text>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

            <flux:button
                href="{{ route('view.balance') }}"
                variant="primary"
                class="!h-56 !flex-col !gap-4 !rounded-2xl !text-2xl !font-semibold"
            >
                <flux:icon name="credit-card" class="size-16" />
                Check Balance
            </flux:button>
            @if($user['role'] !== "commuter") 
                <flux:button
                    href="{{ route('queue.vehicle') }}"
                    wire:navigate
                    variant="primary"
                    class="!h-56 !flex-col !gap-4 !rounded-2xl !text-2xl !font-semibold"
                >
                    <flux:icon name="truck" class="size-16" />
                    Queue Vehicle
                </flux:button>
            @else
                <flux:button
                    href="{{ route('route.select') }}"
                    wire:navigate
                    variant="primary"
                    class="!h-56 !flex-col !gap-4 !rounded-2xl !text-2xl !font-semibold"
                >
                    <flux:icon name="truck" class="size-16" />
                    Ride Vehicle
                </flux:button>
            @endif
            <flux:button
                href="{{ route('view.routes') }}"
                wire:navigate
                variant="primary"
                class="!h-56 !flex-col !gap-4 !rounded-2xl !text-2xl !font-semibold"
            >
                View Routes
            </flux:button>
            <flux:button
                wire:click="signOut"
                variant="primary"
                class="!h-56 !flex-col !gap-4 !rounded-2xl !text-2xl !font-semibold"
            >
                Sign out
            </flux:button>

        </div>

        <div class="text-center">
            <flux:text size="sm" class="text-zinc-500">Session will automatically reset after 60 seconds of inactivity</flux:text>
        </div>

    </div>
</div>