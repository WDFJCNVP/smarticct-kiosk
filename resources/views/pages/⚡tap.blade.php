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
            // Outbound call from Localhost -> Azure Cloud Server
            $response = Http::acceptJson()
                ->timeout(5) // Avoid freezing the screen if connection lags
                ->get("{$baseUrl}/api/card/" . trim($uid));

            $result = $response->json();

            // dd($result);

            // 1. Success
            if ($response->successful()) {
                $this->cardData = $result['data'] ?? [];

                // Store cloud data in local kiosk session
                session([
                    'kiosk_card'        => $this->cardData,
                    'kiosk_user'        => $this->cardData['user'] ?? null,
                    'kiosk_verified_at' => now()->toIso8601String(),
                ]);

                $this->status = 'granted';

                // dd($this->cardData['user']['role']);

                $this->redirect(route('menu.options'), navigate: true);
                return;
            }

            // if error (404, 422, etc)
            $this->status = 'error';
            $this->errorMessage = $result['message'] ?? 'Card verification rejected by server.';
            $this->dispatch('kiosk-reset-after-delay');

        } catch (ConnectionException $e) {

            // Triggered when local machine has no internet

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
        onKeydown(e) {
            if ($wire.status !== 'idle') return;

            const now = Date.now();
            if (now - this.lastKeyTime > 100) this.buffer = '';
            this.lastKeyTime = now;

            if (e.key === 'Enter') {
                if (this.buffer.length > 0) {
                    const uid = this.buffer.trim().toUpperCase();
                    this.buffer = '';
                    
                    if (/^[0-9A-Z]+$/i.test(uid) && uid.length >= 4) {
                        $wire.handleTap(uid);
                    }
                }
            } else if (e.key.length === 1) {
                this.buffer += e.key;
            }
        }
    }"
    @keydown.window="onKeydown($event)"
    @kiosk-reset-after-delay.window="setTimeout(() => $wire.resetToIdle(), 3500)"
    class="flex h-screen w-full items-center justify-center text-black select-none"
>
    @if ($status === 'idle')
        <div class="text-center space-y-2">
            <flux:heading size="xl" class="text-black">Tap your card to continue</flux:heading>
            <p class="text-sm text-zinc-500">Hold your RFID card near the reader</p>
        </div>
    @elseif ($status === 'processing')
        <div class="text-center space-y-2 animate-pulse">
            <flux:heading size="xl" class="text-black">Connecting to cloud server...</flux:heading>
            <p class="text-sm text-zinc-500">Verifying card registration...</p>
        </div>
    @elseif ($status === 'error')
        <div class="text-center space-y-2 max-w-md px-4">
            <flux:heading size="xl" class="text-red-600 font-bold">Unable to Proceed</flux:heading>
            <p class="text-base text-zinc-800 font-medium">{{ $errorMessage }}</p>
        </div>
    @elseif ($status === 'granted')
        <div class="text-center space-y-2">
            <flux:heading size="xl" class="text-green-600 font-bold">Card Verified</flux:heading>
            <p class="text-sm text-zinc-500">Redirecting to menu...</p>
        </div>
    @endif
</div>