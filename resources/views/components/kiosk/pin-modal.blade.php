{{--
    The PIN dialog used by every screen that asks for one. Opened with
    Flux::modal('verify-pin-modal')->show() (or a flux:modal.trigger). The host Livewire
    component must use App\Concerns\VerifiesKioskPin and define verifyPin(string $pin): bool.

    "bare" = no panel: just the dimmed screen with the prompt and circles on top of it.
    Tapping the dimmed area (or the small Cancel link) closes it.
--}}
@props(['subtitle' => 'Enter the 6-digit PIN for your card.'])

<flux:modal name="verify-pin-modal" variant="bare" :closable="false" class="w-[620px] max-w-[92vw] !bg-transparent !p-0 !shadow-none !ring-0 !border-0">
    <x-kiosk.pin-pad :subtitle="$subtitle" />
</flux:modal>
