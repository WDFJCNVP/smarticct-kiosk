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
    class="flex min-h-0 flex-1 flex-col items-center justify-center p-6 text-center"
>
    <div class="w-full max-w-2xl rounded-[2rem] border-2 border-white/15 bg-k-800 p-12">
        <div x-show="!(verifying && $wire.status === 'idle')">
            <x-kiosk.reader-status :status="$status" :error-message="$errorMessage" />
        </div>
        <div x-show="verifying && $wire.status === 'idle'" x-cloak>
            <x-kiosk.reader-status status="processing" />
        </div>
    </div>

    <div class="mt-8">
        <x-kiosk.button variant="second" href="{{ route('kiosk.home') }}" wire:navigate>
            <flux:icon name="arrow-left" class="size-7" /> Back
        </x-kiosk.button>
    </div>
</div>