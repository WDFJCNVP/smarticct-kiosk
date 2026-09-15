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
    }
};
?>

<div class="mx-auto flex w-full max-w-lg min-h-full flex-1 flex-col justify-center gap-6 p-4 sm:p-8">

    <div>
        <flux:button href="{{ route('menu.options') }}" wire:navigate variant="ghost" icon="arrow-left" class="!text-white/70 hover:!text-white">Back</flux:button>
    </div>

    <flux:card class="space-y-6 !rounded-3xl !border !border-white/15 !bg-white/8 !p-6 !backdrop-blur-md sm:!p-8">
        <div class="flex items-center gap-2">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary/15 ring-1 ring-secondary/30">
                <flux:icon name="credit-card" class="size-5 text-secondary" />
            </div>
            <flux:text class="font-secondary text-sm text-white/60">Card Balance</flux:text>
        </div>

        <div class="font-primary text-5xl font-black text-white">
            ₱{{ number_format($card['balance'] ?? 0, 2) }}
        </div>

        <div class="space-y-3 border-t border-white/10 pt-5">
            <div class="flex items-center justify-between">
                <flux:text class="text-sm font-medium text-white/60">Card Number</flux:text>
                <flux:text class="font-mono text-base tracking-wider text-white/90">
                    {{ $card['card_number'] ?? '—' }}
                </flux:text>
            </div>
            <div class="flex items-center justify-between">
                <flux:text class="text-sm font-medium text-white/60">Cardholder</flux:text>
                <flux:text class="text-base font-semibold text-white/90">
                    {{ $user['name'] ?? '—' }}
                </flux:text>
            </div>
            <div class="flex items-center justify-between">
                <flux:text class="text-sm font-medium text-white/60">Account Type</flux:text>
                <flux:text class="text-base font-semibold capitalize text-white/90">
                    {{ $user['role'] ?? '—' }}
                </flux:text>
            </div>
        </div>

        <flux:text size="sm" class="block text-center text-white/50">
            To add funds to your card, please visit the terminal cashier counter.
        </flux:text>
    </flux:card>
</div>
