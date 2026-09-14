<?php

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

new class extends Component
{
    public string $email_address = '';
    public string $password = '';
    public ?string $errorMessage = null;

    protected array $rules = [
        'email_address' => 'required|email',
        'password'      => 'required|string',
    ];

    public function login(): void
    {
        $this->validate();
        $this->errorMessage = null;

        $baseUrl = config('services.smarticct.api_url', 'https://smarticct.app');

        try {
            $response = Http::withoutVerifying()
                ->acceptJson()
                ->timeout(6)
                ->post("{$baseUrl}/api/kiosk/login", [
                    'email_address' => trim($this->email_address),
                    'password'      => $this->password,
                ]);

            $result = $response->json();

            if ($response->successful()) {
                $cardData       = $result['data'] ?? [];
                $cardRouteList  = $result['route_list'] ?? [];

                session([
                    'kiosk_card'        => $cardData,
                    'kiosk_user'        => $cardData['user'] ?? null,
                    'kiosk_vehicle'     => $cardData['user']['vehicles'] ?? null,
                    'kiosk_route_list'  => $cardRouteList ?? null,
                    'kiosk_verified_at' => now()->toIso8601String(),
                ]);

                // dd(session('kiosk_route_list'));

                $this->redirect(route('menu.options'), navigate: true);
                return;
            }

            $this->errorMessage = $result['message'] ?? 'Invalid email address or password.';

        } catch (\Exception $e) {
            Log::error('Kiosk manual login connection failed', ['error' => $e->getMessage()]);
            $this->errorMessage = 'Unable to connect to the login server. Please try again.';
        }
    }
};
?>

<div class="flex min-h-screen w-full items-center justify-center p-4 select-none">
    <flux:card class="w-full max-w-md space-y-6 p-6 sm:p-8">
        {{-- Header --}}
        <div class="space-y-1 text-center">
            <flux:heading size="xl" class="font-bold">Kiosk Sign In</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                Sign in using your registered account credentials
            </flux:text>
        </div>

        {{-- Error Banner --}}
        @if ($errorMessage)
            <div class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-center text-sm font-medium text-red-600 dark:text-red-400">
                {{ $errorMessage }}
            </div>
        @endif

        {{-- Form --}}
        <form wire:submit="login" class="space-y-4">
            {{-- Email Input --}}
            <flux:field>
                <flux:label>Email Address</flux:label>
                <flux:input
                    type="email"
                    wire:model.defer="email_address"
                    placeholder="Enter your email"
                    icon="envelope"
                    required
                    autofocus
                />
                <flux:error name="email_address" />
            </flux:field>

            {{-- Password Input --}}
            <flux:field>
                <flux:label>Password</flux:label>
                <flux:input
                    type="password"
                    wire:model.defer="password"
                    placeholder="Enter your password"
                    icon="key"
                    viewable
                    required
                />
                <flux:error name="password" />
            </flux:field>

            {{-- Submit Action --}}
            <div class="pt-2">
                <flux:button
                    type="submit"
                    variant="primary"
                    class="w-full"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="login">Sign In</span>
                    <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="size-4 animate-spin" />
                        Authenticating...
                    </span>
                </flux:button>
            </div>
        </form>

    </flux:card>
</div>