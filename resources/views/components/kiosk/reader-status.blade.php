{{-- Card-reader states (idle / processing / error / granted), shared by Home and the Tap screen. --}}
@props(['status' => 'idle', 'errorMessage' => null])

<div class="space-y-6 text-center">
    @if ($status === 'processing')
        <div class="mx-auto flex size-32 items-center justify-center rounded-full bg-secondary/15 ring-2 ring-secondary/40">
            <flux:icon name="arrow-path" class="size-16 animate-spin text-secondary" />
        </div>
        <div class="space-y-2">
            <h2 class="font-primary text-4xl font-extrabold text-white">Verifying your card...</h2>
            <p class="text-2xl text-tx-2">Connecting to server</p>
        </div>
    @elseif ($status === 'error')
        <div class="mx-auto flex size-32 items-center justify-center rounded-full bg-stop/15 ring-2 ring-stop/50">
            <flux:icon name="x-circle" class="size-16 text-stop" />
        </div>
        <div class="space-y-2">
            <h2 class="font-primary text-4xl font-extrabold text-white">Unable to Proceed</h2>
            <p class="text-2xl font-medium leading-snug text-tx-2">{{ $errorMessage }}</p>
        </div>
    @elseif ($status === 'granted')
        <div class="mx-auto flex size-32 items-center justify-center rounded-full bg-go/15 ring-2 ring-go/50">
            <flux:icon name="check-circle" class="size-16 text-go" />
        </div>
        <div class="space-y-2">
            <h2 class="font-primary text-4xl font-extrabold text-go">Card Verified</h2>
            <p class="text-2xl text-tx-2">Redirecting to menu...</p>
        </div>
    @else
        <div class="mx-auto flex size-32 items-center justify-center rounded-full bg-secondary/15 ring-2 ring-secondary/40">
            <flux:icon name="credit-card" class="size-16 text-secondary" />
        </div>
        <div class="space-y-2">
            <h2 class="font-primary text-4xl font-extrabold text-white">Tap your card to continue</h2>
            <p class="text-2xl text-tx-2">Hold your RFID card near the reader</p>
        </div>
    @endif
</div>
