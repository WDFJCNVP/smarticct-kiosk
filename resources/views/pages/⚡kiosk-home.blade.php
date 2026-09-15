<?php

use Livewire\Component;

new class extends Component
{
    // A logged-in user can land back here (e.g. via browser back navigation)
    // without this check, they'd see the generic "Sign In" welcome screen and
    // it would look like they'd been logged out, even though their session is
    // still intact — so send them straight back to their menu instead.
    public function mount()
    {
        if (session()->has('kiosk_card')) {
            $this->redirect(route('menu.options'), navigate: true);
        }
    }
};
?>

{{-- Attract screen. On a real kiosk this is what people see from across the
     room, so it carries one message and one action. The entire panel is the
     tap target — nobody should have to aim at a button. --}}
<a
    href="{{ route('login.options') }}"
    wire:navigate
    class="group flex min-h-full w-full flex-col items-center justify-center gap-10 p-6 text-center sm:p-10"
>
    <div class="space-y-4">
        <span class="inline-flex items-center gap-2 rounded-full border border-secondary/30 bg-secondary/10 px-4 py-1 font-secondary text-xs font-semibold uppercase tracking-wide text-secondary">
            <span class="size-1.5 rounded-full bg-secondary"></span>
            Iriga City Central Terminal
        </span>

        <flux:heading size="xl" class="font-primary text-4xl font-extrabold text-white drop-shadow-sm sm:text-6xl">
            Welcome to SmartICCT
        </flux:heading>
    </div>

    {{-- The pulse is the "this screen is alive, touch it" signal --}}
    <div class="flex flex-col items-center gap-5">
        <span class="relative flex size-24 items-center justify-center">
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-secondary/25"></span>
            <span class="relative inline-flex size-24 items-center justify-center rounded-full bg-secondary shadow-lg shadow-secondary/30">
                <flux:icon name="hand-raised" class="size-11 text-primary" />
            </span>
        </span>

        <span class="font-primary text-2xl font-bold text-white sm:text-3xl">
            Touch to Start
        </span>
    </div>
</a>