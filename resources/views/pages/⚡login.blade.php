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

{{-- Plain inputs: the device's own keyboard is used, as before. Everything the person
     needs (both fields, Back, Sign in) sits in the top half of the screen so an OS
     keyboard opening below never covers a button. --}}
<div class="flex min-h-0 flex-1 flex-col items-center justify-center px-8 pb-16">
  <div class="w-full max-w-[820px]">
    <h1 class="font-primary text-[38px] font-extrabold leading-tight text-white">Sign in with your account</h1>
    <p class="mt-1 font-secondary text-xl text-tx-2">Use the email and password from your SmartICCT account.</p>

    @if ($errorMessage)
        <div class="mt-3 flex items-center gap-3 rounded-2xl border-2 border-stop/50 bg-stop/10 px-5 py-3 text-xl font-semibold text-stop">
            <flux:icon name="exclamation-circle" class="size-7 shrink-0" />
            {{ $errorMessage }}
        </div>
    @endif

    <form wire:submit="login" class="mt-4 space-y-4">
        <div class="grid grid-cols-[1.25fr_1fr] gap-5">
            <div>
                <label for="login-email" class="mb-2 block font-secondary text-lg font-semibold text-tx-2">Email address</label>
                <div class="flex h-[68px] items-center gap-3 rounded-[1.125rem] border-2 border-white/30 bg-k-800 px-5 focus-within:border-secondary focus-within:ring-[3px] focus-within:ring-secondary/30">
                    <flux:icon name="envelope" class="size-6 shrink-0 text-tx-3" />
                    <input
                        id="login-email"
                        type="email"
                        wire:model.defer="email_address"
                        placeholder="name@example.com"
                        autofocus
                        required
                        class="h-full w-full bg-transparent font-secondary text-[26px] text-white placeholder:text-tx-3 focus:outline-none [&:-webkit-autofill]:shadow-[inset_0_0_0_1000px_var(--color-k-800)] [&:-webkit-autofill]:[-webkit-text-fill-color:#fff]"
                    >
                </div>
                @error('email_address') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <div>
                <label for="login-password" class="mb-2 block font-secondary text-lg font-semibold text-tx-2">Password</label>
                <div class="flex h-[68px] items-center gap-3 rounded-[1.125rem] border-2 border-white/30 bg-k-800 px-5 focus-within:border-secondary focus-within:ring-[3px] focus-within:ring-secondary/30">
                    <flux:icon name="key" class="size-6 shrink-0 text-tx-3" />
                    <input
                        id="login-password"
                        type="{{ $showPassword ? 'text' : 'password' }}"
                        wire:model.defer="password"
                        placeholder="Password"
                        required
                        class="h-full w-full min-w-0 bg-transparent font-secondary text-[26px] text-white placeholder:text-tx-3 focus:outline-none [&:-webkit-autofill]:shadow-[inset_0_0_0_1000px_var(--color-k-800)] [&:-webkit-autofill]:[-webkit-text-fill-color:#fff]"
                    >
                    <button type="button" wire:click="togglePassword" class="shrink-0 px-1 text-lg font-bold text-tx-2">
                        {{ $showPassword ? 'Hide' : 'Show' }}
                    </button>
                </div>
                @error('password') <flux:error>{{ $message }}</flux:error> @enderror
            </div>
        </div>

        <div class="flex gap-4">
            <x-kiosk.button variant="second" size="xl" class="w-[210px]" href="{{ route('kiosk.home') }}" wire:navigate>
                <flux:icon name="arrow-left" class="size-7" /> Back
            </x-kiosk.button>
            <x-kiosk.button size="xl" class="flex-1" type="submit" wire:loading.attr="disabled" wire:target="login">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading.inline-flex wire:target="login" class="items-center gap-3">
                    <flux:icon name="arrow-path" class="size-7 animate-spin" /> Signing in...
                </span>
            </x-kiosk.button>
        </div>
    </form>
</div>
  </div>