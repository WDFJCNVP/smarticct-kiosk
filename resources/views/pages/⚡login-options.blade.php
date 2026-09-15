<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div class="flex min-h-full w-full flex-col items-center justify-center p-6 sm:p-10">
    <div class="w-full max-w-3xl space-y-8">

        <div class="space-y-2 text-center">
            <flux:heading size="xl" class="font-primary text-3xl font-extrabold text-white drop-shadow-sm sm:text-4xl">
                How would you like to sign in?
            </flux:heading>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            {{-- Tap is the expected path at a terminal, so it stays visually dominant --}}
            <flux:button
                href="{{ route('login.tap') }}"
                wire:navigate
                variant="primary"
                class="kiosk-tap-target !h-56 !flex-col !gap-4 !rounded-3xl !bg-secondary !text-2xl !font-bold !text-primary !shadow-lg !shadow-secondary/25 transition hover:!bg-secondary-hover"
            >
                <flux:icon name="credit-card" class="size-16" />
                Tap Card
            </flux:button>

            <flux:button
                href="{{ route('login.email') }}"
                wire:navigate
                variant="ghost"
                class="kiosk-tap-target !h-56 !flex-col !gap-4 !rounded-3xl !border !border-white/15 !bg-white/8 !text-2xl !font-bold !text-white !backdrop-blur-md transition hover:!border-white/25 hover:!bg-white/14"
            >
                <flux:icon name="user" class="size-16" />
                Use Account
            </flux:button>
        </div>

        {{-- Back is a real target now, not 12px of ghost text --}}
        <div class="text-center">
            <flux:button
                href="{{ route('kiosk.home') }}"
                wire:navigate
                variant="ghost"
                class="!h-14 !rounded-xl !border !border-white/10 !px-8 !text-base !font-semibold !text-white/70 transition hover:!bg-white/10 hover:!text-white"
            >
                <span class="inline-flex items-center gap-2">
                    <flux:icon name="arrow-left" class="size-5" />
                    Back
                </span>
            </flux:button>
        </div>
    </div>
</div>