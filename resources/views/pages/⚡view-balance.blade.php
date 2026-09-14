<?php

use Livewire\Component;

new class extends Component
{
    public array $card = [];
    public array $user = [];

    public function mount() {

        if (! session()->has('kiosk_card')) {
            $this->redirect(route('login.options'), navigate: true);
            return;
        }

        $this->card = session('kiosk_card', []);
        $this->user = session('kiosk_user', []);

        // dd($this->card);
    }
};
?>

<div>

    <flux:button href="{{ route('menu.options') }}" variant="primary">Back</flux:button>

    <flux:card class="p-4 sm:p-5 flex flex-col">
        <div class="flex items-center gap-1.5 sm:gap-2 mb-1.5">
            <div class="flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-primary/10 dark:bg-primary/20 shrink-0">
                <flux:icon.credit-card class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary dark:text-dark-txt-primary" />
            </div>
            <x-text class="font-secondary text-xs sm:text-stat-label text-light-txt-muted dark:text-dark-txt-muted">
                Total balance
            </x-text>
<!--             <button
                type="button"
                @click="showBalance = !showBalance"
                class="ml-auto text-light-txt-muted dark:text-dark-txt-muted hover:text-light-txt-body dark:hover:text-dark-txt-primary transition-colors focus:outline-none"
                title="Toggle Balance Visibility"
            >
                <flux:icon name="eye" class="w-4 h-4" x-show="!showBalance" />
                <flux:icon name="eye-slash" class="w-4 h-4" x-show="showBalance" x-cloak />
            </button> -->
        </div>

        <x-text class="font-primary text-2xl sm:text-3xl font-bold text-light-txt-primary dark:text-dark-txt-primary block h-[40px] flex items-center">
            <span>
                ₱{{ number_format($this->card['balance'], 2) }}
            </span>
        <!-- <span x-show="!showBalance" x-cloak class="tracking-wider">
                ₱••••••
            </span> -->
        </x-text>

        {{-- Enhanced Card details block – larger, more spacing, fills whitespace --}}
        <div class="mt-4 pt-4 border-t border-light-bd-default dark:border-dark-bd-default space-y-3">
            <div class="flex justify-between items-center">
                <x-text class="text-sm font-medium text-light-txt-muted dark:text-dark-txt-muted">Card Number</x-text>
                <x-text class="text-base font-mono text-light-txt-body dark:text-dark-txt-primary tracking-wider">
                    {{ $this->card['card_number'] }}
                </x-text>
            </div>
            <div class="flex justify-between items-center">
                <x-text class="text-sm font-medium text-light-txt-muted dark:text-dark-txt-muted">Cardholder</x-text>
                <x-text class="text-base font-semibold text-light-txt-body dark:text-dark-txt-primary">
                    {{ $this->user['name'] }}
                </x-text>
            </div>
            <div class="flex justify-between items-center">
                <x-text class="text-sm font-medium text-light-txt-muted dark:text-dark-txt-muted">Type</x-text>
                <x-text class="text-base font-semibold capitalize text-light-txt-body dark:text-dark-txt-primary">
                    {{ $this->user['role'] }}
                </x-text>
            </div>
        </div>
    </flux:card>
</div>