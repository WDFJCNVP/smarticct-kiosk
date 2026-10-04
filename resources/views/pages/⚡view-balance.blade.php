<?php

use Livewire\Component;

new class extends Component
{
    public array $card = [];
    public array $user = [];
    public int $visibleSeconds = 20;

    public function mount()
    {
        if (! session()->has('kiosk_card')) {
            $this->redirect(route('login.options'), navigate: true);
            return;
        }

        $verifiedAt = session('kiosk_pin_verified_at');

        if (! $verifiedAt || now()->timestamp - $verifiedAt > 120) {
            session()->forget('kiosk_pin_verified_at');
            $this->redirect(route('menu.options'), navigate: true);
            return;
        }

        session()->forget('kiosk_pin_verified_at');

        $this->visibleSeconds = max(5, (int) config('kiosk.balance_visible_seconds'));
        $this->card = session('kiosk_card', []);
        $this->user = session('kiosk_user', []);
    }

    public function hideBalance(): void
    {
        $this->redirect(route('menu.options'), navigate: true);
    }
};
?>

{{-- "My Card": the card artwork (drag it, tap it, or use Flip) and the card details incl. balance. --}}
<div
    x-data="{
        total: {{ (int) $visibleSeconds }},
        left: {{ (int) $visibleSeconds }},
        timer: null,
        init() {
            this.timer = setInterval(() => {
                this.left = Math.max(0, this.left - 1);
                if (this.left === 0) { clearInterval(this.timer); $wire.hideBalance(); }
            }, 1000);
        },
        destroy() { clearInterval(this.timer); }
    }"
    class="flex min-h-0 flex-1 flex-col"
>
    <div class="grid min-h-0 flex-1 grid-cols-2 items-center gap-10 px-10 py-6">

        <div class="flex flex-col items-center gap-6">
            <x-kiosk.card-3d class="max-w-[420px]" />
            <div class="flex flex-col items-center gap-4">
                <p class="font-secondary text-xl text-tx-3">Drag or tap the card to turn it over</p>
                <x-kiosk.button variant="second" size="sm" @click="$dispatch('kiosk-flip-card')">
                    <flux:icon name="arrow-path" class="size-6" /> Flip card
                </x-kiosk.button>
            </div>
        </div>

        <div class="rounded-[1.75rem] border-2 border-white/15 bg-k-800 p-8">
            <div class="flex items-center gap-3">
                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-secondary/15 ring-1 ring-secondary/40">
                    <flux:icon name="credit-card" class="size-6 text-secondary" />
                </div>
                <span class="font-secondary text-xl text-tx-2">Card balance</span>
            </div>

            <div class="mt-3 font-primary text-[64px] font-extrabold leading-none tracking-tight text-secondary tabular-nums">
                ₱{{ number_format($card['balance'] ?? 0, 2) }}
            </div>

            <dl class="mt-6 divide-y divide-white/15 border-t border-white/15 text-[22px]">
                <div class="flex items-center justify-between gap-4 py-3.5">
                    <dt class="text-tx-2">Card number</dt>
                    <dd class="font-mono font-bold tracking-wider text-white">{{ $card['card_number'] ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-3.5">
                    <dt class="text-tx-2">Cardholder</dt>
                    <dd class="truncate font-bold text-white">{{ $user['name'] ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-3.5">
                    <dt class="text-tx-2">Account type</dt>
                    <dd class="font-bold capitalize text-white">{{ $user['role'] ?? '—' }}</dd>
                </div>
            </dl>

            <p class="mt-4 flex items-center gap-3 font-secondary text-xl text-tx-2">
                <flux:icon name="information-circle" class="size-6 shrink-0" />
                To add funds to your card, please visit the terminal cashier counter.
            </p>
        </div>
    </div>

    <x-kiosk.action-bar>
        <x-slot:back>
            <x-kiosk.button variant="second" href="{{ route('menu.options') }}" wire:navigate>
                <flux:icon name="arrow-left" class="size-7" /> Back
            </x-kiosk.button>
        </x-slot:back>

        <div class="w-[460px]">
            <div class="text-center text-xl text-tx-2">
                <flux:icon name="eye-slash" class="mr-1 inline size-6 align-[-4px]" />
                Hiding your balance in <b class="tabular-nums text-white" x-text="left"></b>s
            </div>
            <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-white/15">
                <i class="block h-full bg-secondary transition-[width] duration-1000 ease-linear" :style="'width:' + (left / total * 100) + '%'"></i>
            </div>
        </div>
    </x-kiosk.action-bar>
</div>