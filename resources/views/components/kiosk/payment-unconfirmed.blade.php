{{--
    Shown when a charge request got no usable answer (timeout, dropped connection, server error).
    The money may or may not have moved, so this stays up until the person dismisses it and
    tells them NOT to simply try again. Opened with Flux::modal('payment-unconfirmed')->show().
--}}
@props(['what' => 'payment'])

<flux:modal name="payment-unconfirmed" :closable="false" :dismissible="false" class="w-[680px] max-w-[90vw] !bg-k-800 !border-2 !border-wait/60 !rounded-[2rem] !p-8 shadow-2xl">
    <div class="space-y-5">
        <div class="flex items-center gap-4">
            <span class="grid size-16 shrink-0 place-items-center rounded-full bg-wait/15 ring-2 ring-wait/50">
                <flux:icon name="exclamation-triangle" class="size-9 text-wait" />
            </span>
            <h2 class="font-primary text-[34px] font-extrabold leading-tight text-white">We couldn't confirm your {{ $what }}</h2>
        </div>

        <p class="font-secondary text-2xl leading-snug text-tx-2">
            The connection dropped before we got an answer, so your card may or may not have been charged.
        </p>

        <div class="rounded-2xl border-2 border-wait/40 bg-wait/10 px-5 py-4 font-secondary text-2xl font-semibold leading-snug text-wait">
            Please don't try again yet. Check your balance under <span class="text-white">My card</span>, or ask terminal staff to check for you.
        </div>

        <div class="flex gap-4 pt-1">
            <flux:modal.close class="w-[210px]">
                <x-kiosk.button variant="second" size="xl" class="w-full">Stay here</x-kiosk.button>
            </flux:modal.close>
            <x-kiosk.button size="xl" class="flex-1" href="{{ route('menu.options') }}" wire:navigate>Go to menu</x-kiosk.button>
        </div>
    </div>
</flux:modal>
