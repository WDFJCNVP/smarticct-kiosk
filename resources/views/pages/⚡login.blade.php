<?php

use App\Support\EscalatingLockout;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public string $email_address = '';
    public string $password = '';
    public bool $showPassword = false;
    public ?string $errorMessage = null;

    /** Seconds the form is locked for, as of page load. Later locks arrive as a browser event. */
    #[Locked]
    public int $lockedFor = 0;

    protected array $rules = [
        'email_address' => 'required|email',
        'password'      => 'required|string',
    ];

    public function mount(): void
    {
        $this->lockedFor = $this->deviceLockout()->remainingSeconds();
    }

    public function togglePassword(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function login(): void
    {
        $this->validate();
        $this->errorMessage = null;

        $email   = Str::lower(trim($this->email_address));
        $account = EscalatingLockout::for('login', $email);
        $device  = $this->deviceLockout();

        $remaining = max($account->remainingSeconds(), $device->remainingSeconds());

        if ($remaining > 0) {
            $this->showLock($remaining);
            return;
        }

        $baseUrl = config('services.smarticct.api_url', 'https://smarticct.app');

        try {
            $response = Http::withOptions(['verify' => (bool) config('kiosk.verify_ssl', true)])
                ->acceptJson()
                ->timeout(6)
                ->post("{$baseUrl}/api/kiosk/login", [
                    'email_address' => $email,
                    'password'      => $this->password,
                ]);

            $result = $response->json();

            if ($response->successful()) {
                $account->clear();

                $cardData      = $result['data'] ?? [];
                $cardRouteList = $result['route_list'] ?? [];

                session([
                    'kiosk_card'        => $cardData,
                    'kiosk_user'        => $cardData['user'] ?? null,
                    'kiosk_vehicle'     => $cardData['user']['vehicles'] ?? null,
                    'kiosk_route_list'  => $cardRouteList ?? null,
                    'kiosk_verified_at' => now()->toIso8601String(),
                ]);

                $this->password = '';
                $this->redirect(route('menu.options'), navigate: true);
                return;
            }

            $this->password = '';

            if ($response->status() === 429) {
                $wait = (int) $response->header('Retry-After');
                $account->lockFor($wait > 0 ? $wait : (int) config('kiosk.login_lockout_seconds'));
                $this->showLock($account->remainingSeconds());
                return;
            }

            if ($response->serverError()) {
                Log::error('Kiosk manual login: API error', ['status' => $response->status()]);
                $this->errorMessage = 'The login server is having trouble. Please try again, or contact staff.';
                return;
            }

            // A genuinely wrong email/password. The wording is deliberately the same whether the
            // email exists or not, so the screen cannot be used to find out who has an account.
            $lockedFor = max($account->hit(), $device->hit());

            if ($lockedFor > 0) {
                $this->showLock($lockedFor);
                return;
            }

            $left = min($account->attemptsLeft(), $device->attemptsLeft());
            $this->errorMessage = 'Incorrect email or password. ' . $left . ' ' . Str::plural('attempt', $left) . ' left.';

        } catch (ConnectionException $e) {
            Log::error('Kiosk manual login connection failed', ['error' => $e->getMessage()]);
            $this->errorMessage = 'Unable to connect to the login server. Please try again.';
        } catch (\Exception $e) {
            Log::error('Kiosk manual login failed', ['error' => $e->getMessage()]);
            $this->errorMessage = 'Something went wrong. Please try again or contact staff.';
        }
    }

    private function deviceLockout(): EscalatingLockout
    {
        return EscalatingLockout::for('login', 'device', (int) config('kiosk.login_device_max_attempts'));
    }

    private function showLock(int $seconds): void
    {
        $this->lockedFor = $seconds;
        $this->password = '';
        $this->dispatch('kiosk-login-locked', seconds: $seconds);
    }
};
?>

{{-- Plain inputs: the device's own keyboard is used, as before. Everything the person
     needs (both fields, Back, Sign in) sits in the top half of the screen so an OS
     keyboard opening below never covers a button. --}}
<div
    x-data="{
        remaining: 0,
        until: 0,
        timer: null,
        get locked() { return this.remaining > 0; },
        get clock() { return Math.floor(this.remaining / 60) + ':' + String(this.remaining % 60).padStart(2, '0'); },
        setLock(seconds) {
            clearInterval(this.timer);
            this.until = seconds > 0 ? Date.now() + seconds * 1000 : 0;
            this.tick();
            if (this.until) this.timer = setInterval(() => this.tick(), 250);
        },
        tick() {
            this.remaining = this.until ? Math.max(0, Math.ceil((this.until - Date.now()) / 1000)) : 0;
            if (this.until && this.remaining === 0) { clearInterval(this.timer); this.until = 0; }
        }
    }"
    x-init="setLock({{ (int) $lockedFor }})"
    @kiosk-login-locked.window="setLock($event.detail.seconds)"
    class="flex min-h-0 flex-1 flex-col items-center justify-center px-8 pb-16"
>
  <div class="w-full max-w-[820px]">
    <h1 class="font-primary text-[38px] font-extrabold leading-tight text-white">Sign in with your account</h1>
    <p class="mt-1 font-secondary text-xl text-tx-2">Use the email and password from your SmartICCT account.</p>

    <div x-show="locked" x-cloak class="mt-3 flex items-center gap-3 rounded-2xl border-2 border-stop/50 bg-stop/10 px-5 py-3 text-xl font-semibold text-stop" role="alert">
        <flux:icon name="lock-closed" class="size-7 shrink-0" />
        <span>Too many incorrect attempts. Try again in <span class="font-bold tabular-nums text-white" x-text="clock"></span></span>
    </div>

    @if ($errorMessage)
        <div x-show="!locked" class="mt-3 flex items-center gap-3 rounded-2xl border-2 border-stop/50 bg-stop/10 px-5 py-3 text-xl font-semibold text-stop">
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
            <x-kiosk.button size="xl" class="flex-1" type="submit" wire:loading.attr="disabled" wire:target="login" x-bind:disabled="locked">
                <span wire:loading.remove wire:target="login">
                    <span x-show="!locked">Sign in</span>
                    <span x-show="locked" x-cloak class="inline-flex items-center gap-3">
                        <flux:icon name="lock-closed" class="size-7" /> Try again in <span class="tabular-nums" x-text="clock"></span>
                    </span>
                </span>
                <span wire:loading.inline-flex wire:target="login" class="items-center gap-3">
                    <flux:icon name="arrow-path" class="size-7 animate-spin" /> Signing in...
                </span>
            </x-kiosk.button>
        </div>
    </form>
</div>
  </div>