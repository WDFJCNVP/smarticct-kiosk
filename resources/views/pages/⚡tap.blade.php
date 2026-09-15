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
    class="flex min-h-full w-full flex-1 flex-col items-center justify-center p-6 text-center"
>
    <div class="w-full max-w-md rounded-3xl border border-white/15 bg-white/8 p-10 backdrop-blur-md">
        @if ($status === 'idle')
            <div class="space-y-6">
                <div class="mx-auto flex size-32 items-center justify-center rounded-full bg-secondary/15 ring-1 ring-secondary/30">
                    <flux:icon name="credit-card" class="size-16 text-secondary" />
                </div>
                <div class="space-y-1">
                    <flux:heading size="xl" class="font-primary font-extrabold text-white">
                        Tap your card to continue
                    </flux:heading>
                    <flux:text class="text-white/60">Hold your RFID card near the reader</flux:text>
                </div>
            </div>
        @elseif ($status === 'processing')
            <div class="animate-pulse space-y-6">
                <div class="mx-auto flex size-32 items-center justify-center rounded-full bg-secondary/15 ring-1 ring-secondary/30">
                    <flux:icon name="arrow-path" class="size-16 animate-spin text-secondary" />
                </div>
                <div class="space-y-1">
                    <flux:heading size="xl" class="font-primary font-extrabold text-white">
                        Verifying your card...
                    </flux:heading>
                    <flux:text class="text-white/60">Connecting to server</flux:text>
                </div>
            </div>
        @elseif ($status === 'error')
            <div class="space-y-6">
                <div class="mx-auto flex size-32 items-center justify-center rounded-full bg-danger/15 ring-1 ring-danger/30">
                    <flux:icon name="x-circle" class="size-16 text-danger" />
                </div>
                <div class="space-y-1">
                    <flux:heading size="xl" class="font-primary font-extrabold text-danger">Unable to Proceed</flux:heading>
                    <flux:text class="text-base font-medium text-white/80">{{ $errorMessage }}</flux:text>
                </div>
            </div>
        @elseif ($status === 'granted')
            <div class="space-y-6">
                <div class="mx-auto flex size-32 items-center justify-center rounded-full bg-success/15 ring-1 ring-success/30">
                    <flux:icon name="check-circle" class="size-16 text-success" />
                </div>
                <div class="space-y-1">
                    <flux:heading size="xl" class="font-primary font-extrabold text-success">Card Verified</flux:heading>
                    <flux:text class="text-white/60">Redirecting to menu...</flux:text>
                </div>
            </div>
        @endif
    </div>

    <div class="mt-8">
        <flux:button href="{{ route('login.options') }}" wire:navigate variant="ghost" icon="arrow-left" class="!text-white/70 hover:!text-white">Back</flux:button>
    </div>
</div>
