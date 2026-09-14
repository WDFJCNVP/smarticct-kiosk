<?php

use Livewire\Component;

new class extends Component
{
//
};
?>

<div class="flex min-h-screen w-full items-center justify-center bg-zinc-950 p-8">
    <div class="w-full max-w-4xl space-y-10">

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">

            <flux:button 
                href="{{ route('login.email') }}"
                wire:navigate
                variant="primary" 
                class="!h-56 !flex-col !gap-4 !rounded-2xl !text-2xl !font-semibold"
            >
                <flux:icon name="envelope" class="size-16" />
                Continue with Email
            </flux:button>

          <flux:button 
                href="{{ route('login.tap') }}"
                wire:navigate
                variant="primary" 
                class="!h-56 !flex-col !gap-4 !rounded-2xl !text-2xl !font-semibold"
            >
            <flux:icon name="credit-card" class="size-16" />
            Tap Physical Card
          </flux:button>

        </div>
    </div>
</div>