<?php

namespace App\Concerns;

use App\Support\EscalatingLockout;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * PIN check shared by every kiosk screen that asks for one (menu, fare, queue).
 *
 * Wrong entries are counted per cardholder (see App\Support\EscalatingLockout and
 * config/kiosk.php). The PIN is passed in as an argument and never stored on the component.
 */
trait VerifiesKioskPin
{
    public ?string $pinError = null;

    /** Seconds the PIN pad stays locked, as of the last server response. */
    #[Locked]
    public int $pinLockedFor = 0;

    public function refreshPinLock(): void
    {
        $userId = session('kiosk_user.id');

        $this->pinLockedFor = $userId ? EscalatingLockout::for('pin', (string) $userId)->remainingSeconds() : 0;
    }

    /**
     * Returns true only when the API accepted the PIN.
     */
    protected function attemptPin(string $pin): bool
    {
        $this->pinError = null;

        $userId = session('kiosk_user.id');

        if (! $userId) {
            $this->pinError = 'Your session has expired. Please sign in again.';

            return false;
        }

        $lockout = EscalatingLockout::for('pin', (string) $userId);

        $remaining = $lockout->remainingSeconds();

        if ($remaining > 0) {
            $this->pinLockedFor = $remaining;
            $this->pinError = 'Too many incorrect attempts.';

            return false;
        }

        if (! preg_match('/^\d{6}$/', $pin)) {
            $this->pinError = 'Enter all 6 digits of your PIN.';

            return false;
        }

        $baseUrl = config('services.smarticct.api_url', 'https://smarticct.app');

        try {
            $response = Http::withOptions(['verify' => (bool) config('kiosk.verify_ssl', true)])
                ->acceptJson()
                ->timeout(6)
                ->post("{$baseUrl}/api/card/verify", [
                    'pin' => $pin,
                    'user_id' => $userId,
                ]);

            if ($response->successful()) {
                $lockout->clear();
                $this->pinLockedFor = 0;

                return true;
            }

            if ($response->status() === 429) {
                $wait = (int) $response->header('Retry-After');
                $lockout->lockFor($wait > 0 ? $wait : (int) config('kiosk.pin_lockout_seconds'));
                $this->pinLockedFor = $lockout->remainingSeconds();
                $this->pinError = 'Too many incorrect attempts.';

                return false;
            }

            if ($response->serverError()) {
                Log::error('Kiosk PIN check: API error', ['status' => $response->status()]);
                $this->pinError = 'The server is having trouble. Please try again, or contact staff.';

                return false;
            }

            $lockedFor = $lockout->hit();

            if ($lockedFor > 0) {
                $this->pinLockedFor = $lockedFor;
                $this->pinError = 'Too many incorrect attempts.';

                return false;
            }

            $left = $lockout->attemptsLeft();
            $reason = rtrim($response->json('message') ?: 'Incorrect PIN', '. ');

            $this->pinError = "{$reason}. {$left} ".Str::plural('attempt', $left).' left.';
        } catch (ConnectionException $e) {
            Log::error('Kiosk PIN check: connection failed', ['error' => $e->getMessage()]);
            $this->pinError = 'Unable to connect to the server. Please try again.';
        } catch (\Throwable $e) {
            Log::error('Kiosk PIN check: unexpected error', ['error' => $e->getMessage()]);
            $this->pinError = 'Something went wrong. Please try again or contact staff.';
        }

        return false;
    }
}
