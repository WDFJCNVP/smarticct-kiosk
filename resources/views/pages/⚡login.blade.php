<?php

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

new class extends Component
{
    public string $email_address = '';
    public string $password = '';
    public bool $showPassword = false;
    public ?string $errorMessage = null;

    protected array $rules = [
        'email_address' => 'required|email',
        'password'      => 'required|string',
    ];

    public function togglePassword(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

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
                $cardData      = $result['data'] ?? [];
                $cardRouteList = $result['route_list'] ?? [];

                session([
                    'kiosk_card'        => $cardData,
                    'kiosk_user'        => $cardData['user'] ?? null,
                    'kiosk_vehicle'     => $cardData['user']['vehicles'] ?? null,
                    'kiosk_route_list'  => $cardRouteList ?? null,
                    'kiosk_verified_at' => now()->toIso8601String(),
                ]);

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

<div class="flex min-h-full w-full flex-1 items-center justify-center p-4 select-none sm:p-6">
    <flux:card class="w-full max-w-lg space-y-7 !rounded-3xl !border !border-white/15 !bg-white/8 !p-7 !backdrop-blur-md sm:!p-9">

        <div class="space-y-1.5 text-center">
            <flux:heading size="xl" class="font-primary text-3xl font-extrabold text-white drop-shadow-sm sm:text-4xl">
                Use Account
            </flux:heading>
            <flux:text class="font-secondary text-sm text-white/60">
                Enter your registered SmartICCT credentials
            </flux:text>
        </div>

        @if ($errorMessage)
            <div class="rounded-xl border border-danger/30 bg-danger/10 p-3 text-center text-sm font-medium text-danger">
                {{ $errorMessage }}
            </div>
        @endif

        {{-- Plain inputs, built by hand — icon and text share one flex row inside
             one bordered box, so there's exactly one visible field per input
             instead of Flux's icon-wrapper and input background layering into
             two mismatched boxes. --}}
        <form wire:submit="login" class="space-y-5">
            <div class="space-y-1.5">
                <label class="block font-secondary text-sm font-semibold text-white/70">Email Address</label>
                <div class="flex items-center gap-3 rounded-2xl border border-white/15 bg-white/8 px-4 focus-within:border-secondary/60">
                    <flux:icon name="envelope" class="size-4 shrink-0 text-white/40" />
                    <input
                        type="email"
                        wire:model.defer="email_address"
                        placeholder="Enter your email"
                        autofocus
                        required
                        class="h-11 w-full bg-transparent font-secondary text-base text-white placeholder:text-white/40 focus:outline-none"
                    >
                </div>
                @error('email_address') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <div class="space-y-1.5">
                <label class="block font-secondary text-sm font-semibold text-white/70">Password</label>
                <div class="flex items-center gap-3 rounded-2xl border border-white/15 bg-white/8 px-4 focus-within:border-secondary/60">
                    <flux:icon name="key" class="size-4 shrink-0 text-white/40" />
                    <input
                        type="{{ $showPassword ? 'text' : 'password' }}"
                        wire:model.defer="password"
                        placeholder="Enter your password"
                        required
                        class="h-11 w-full bg-transparent font-secondary text-base text-white placeholder:text-white/40 focus:outline-none"
                    >
                    <button type="button" wire:click="togglePassword" class="shrink-0 text-white/40 hover:text-white/70">
                        <flux:icon name="{{ $showPassword ? 'eye-slash' : 'eye' }}" class="size-5" />
                    </button>
                </div>
                @error('password') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <div class="pt-1">
                <flux:button
                    type="submit"
                    variant="primary"
                    class="kiosk-tap-target !h-16 w-full !rounded-2xl !bg-secondary !text-xl !font-bold !text-primary !shadow-lg !shadow-secondary/25 transition hover:!bg-secondary-hover"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="login">Sign In</span>
                    <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="size-5 animate-spin" />
                        Authenticating...
                    </span>
                </flux:button>
            </div>
        </form>

        <div class="text-center">
            <flux:button
                href="{{ route('login.options') }}"
                wire:navigate
                variant="ghost"
                class="!h-12 !rounded-xl !border !border-white/10 !px-6 !text-sm !font-semibold !text-white/70 transition hover:!bg-white/10 hover:!text-white"
            >
                <span class="inline-flex items-center gap-2">
                    <flux:icon name="arrow-left" class="size-4" />
                    Back
                </span>
            </flux:button>
        </div>
    </flux:card>
</div>