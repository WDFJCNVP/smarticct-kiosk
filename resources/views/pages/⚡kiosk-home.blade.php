<?php

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

new class extends Component
{
    public string $status = 'idle'; // idle | processing | granted | error
    public ?array $cardData = null;
    public ?string $errorMessage = null;

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

    // ── Card tap ──────────────────────────────────────────────────────────
    // Identical to the handler on the Tap screen (pages::tap). The attract screen
    // now listens for the reader too, so tapping a card *is* the way to start.
    public function handleTap(string $uid): void
    {
        if ($this->status === 'processing') {
            return;
        }

        $this->status = 'processing';
        $this->errorMessage = null;

        $baseUrl = config('services.smarticct.api_url', 'https://smarticct.app');

        try {
            $response = Http::acceptJson()
                ->timeout(5)
                ->get("{$baseUrl}/api/card/" . trim($uid));

            $result = $response->json();

            if ($response->successful()) {
                $this->cardData = $result['data'] ?? [];

                session([
                    'kiosk_card'        => $this->cardData,
                    'kiosk_user'        => $this->cardData['user'] ?? null,
                    'kiosk_route_list'  => $result['route_list'] ?? null,
                    'kiosk_verified_at' => now()->toIso8601String(),
                ]);

                $this->status = 'granted';

                $this->redirect(route('menu.options'), navigate: true);
                return;
            }

            $this->status = 'error';
            $this->errorMessage = $result['message'] ?? 'Card verification rejected by server.';
            $this->dispatch('kiosk-reset-after-delay');

        } catch (ConnectionException $e) {
            Log::warning('Kiosk offline or cloud connection failed', ['error' => $e->getMessage()]);

            $this->status = 'error';
            $this->errorMessage = 'Terminal Offline: Unable to reach server. Please proceed to the cashier.';
            $this->dispatch('kiosk-reset-after-delay');

        } catch (\Exception $e) {
            Log::error('Kiosk tap unexpected exception', ['error' => $e->getMessage()]);

            $this->status = 'error';
            $this->errorMessage = 'Card read failed. Please try tapping again.';
            $this->dispatch('kiosk-reset-after-delay');
        }
    }

    public function resetToIdle(): void
    {
        $this->status = 'idle';
        $this->cardData = null;
        $this->errorMessage = null;
    }
};
?>

{{-- Attract screen. Nothing to aim at: hold a card on the reader and you're in.
     The card is the same 3D artwork as the public site — drag it around. --}}
<div
    x-data="{
        buffer: '',
        lastKeyTime: Date.now(),
        verifying: false,
        minVerifyMs: 900,   // keep the 'Verifying…' modal up at least this long, even on a fast connection
        onKeydown(e) {
            if ($wire.status !== 'idle' || this.verifying) return;
            const now = Date.now();
            if (now - this.lastKeyTime > 100) this.buffer = '';
            this.lastKeyTime = now;

            if (e.key === 'Enter') {
                if (this.buffer.length > 0) {
                    const uid = this.buffer.trim().toUpperCase();
                    this.buffer = '';
                    if (/^[0-9A-Z]+$/i.test(uid) && uid.length >= 4) {
                        // Show the modal immediately; the (unchanged) API call follows right after.
                        this.verifying = true;
                        setTimeout(() => $wire.handleTap(uid), this.minVerifyMs);
                    }
                }
            } else if (e.key.length === 1) {
                this.buffer += e.key;
            }
        }
    }"
    x-init="$watch('$wire.status', s => { if (s === 'error' || s === 'idle') verifying = false; })"
    @keydown.window="onKeydown($event)"
    @kiosk-reset-after-delay.window="setTimeout(() => $wire.resetToIdle(), 8000)"
    class="relative flex min-h-0 flex-1 flex-col px-12 pb-9"
>
  {{-- Everything sits in one centered column, so on a wide screen the headline and the card
       stay next to each other instead of drifting to opposite edges. --}}
  <div class="mx-auto flex min-h-0 w-full max-w-[900px] flex-1 flex-col">
    <div class="flex flex-1 items-center justify-between gap-12">
        <div class="min-w-0 max-w-[440px] space-y-5">
            <h1 class="font-primary text-[68px] font-extrabold leading-[1.02] tracking-tight text-white">
                Tap your card<br>to begin
            </h1>
            <p class="max-w-[420px] text-balance font-secondary text-[26px] leading-[1.3] text-tx-2">
                Hold it on the card reader. No card? Use your account, or check the live queue.
            </p>
        </div>

        {{-- Gentle tilt so it reads as a card, not a skewed shape --}}
        <div class="relative w-[420px] shrink-0">
            <div class="relative">
                <x-kiosk.card-3d class="max-w-[420px]" :tilt-x="5" :tilt-y="-9" :tilt-z="2" />
            </div>
            <p class="relative mt-7 flex justify-center">
                <span class="rounded-full bg-k-950/70 px-5 py-2 font-secondary text-lg text-tx-2">Drag or tap the card to turn it over</span>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <x-kiosk.button variant="second" size="xl" class="!bg-k-950/90" href="{{ route('login.email') }}" wire:navigate>
            <flux:icon name="user" class="size-7" /> Use my account
        </x-kiosk.button>
        <x-kiosk.button variant="second" size="xl" class="!bg-k-950/90" href="{{ route('guest.queue') }}" wire:navigate>
            <flux:icon name="queue-list" class="size-7" /> Live queue
        </x-kiosk.button>
    </div>
  </div>

    {{-- Instant "Verifying…" modal: shows the moment a card is read, before the server has answered --}}
    <div x-show="verifying && $wire.status !== 'error'" x-cloak class="absolute inset-0 z-30 flex items-center justify-center bg-k-950/85 p-6">
        <div class="w-full max-w-2xl rounded-[2rem] border-2 border-white/30 bg-k-800 p-10 shadow-2xl">
            <x-kiosk.reader-status status="processing" />
        </div>
    </div>

    @if ($status !== 'idle')
        <div class="absolute inset-0 z-30 flex items-center justify-center bg-k-950/85 p-6">
            <div class="w-full max-w-2xl rounded-[2rem] border-2 border-white/30 bg-k-800 p-10 shadow-2xl">
                <x-kiosk.reader-status :status="$status" :error-message="$errorMessage" />

                @if ($status === 'error')
                    <div class="mt-8 flex gap-4">
                        <x-kiosk.button size="xl" class="flex-1" wire:click="resetToIdle">Try again</x-kiosk.button>
                        <x-kiosk.button variant="second" size="xl" class="flex-1" href="{{ route('login.email') }}" wire:navigate>Use my account</x-kiosk.button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>