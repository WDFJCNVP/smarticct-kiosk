<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Failed-attempt counter whose lockouts grow with every further failure.
 *
 * The first `max_attempts` failures only warn. From then on each failure locks for
 * base × multiplier^n seconds (capped). State lives in the cache, keyed by whatever
 * the caller is protecting (a cardholder, an email address, the kiosk itself), so it
 * survives sign-outs and new sessions. Settings come from config/kiosk.php under
 * "{scope}_max_attempts", "{scope}_lockout_seconds", "{scope}_lockout_multiplier",
 * "{scope}_lockout_max_seconds" and "{scope}_counter_ttl".
 */
class EscalatingLockout
{
    public function __construct(
        private readonly string $key,
        private readonly int $maxAttempts,
        private readonly int $baseSeconds,
        private readonly int $multiplier,
        private readonly int $capSeconds,
        private readonly int $ttl,
    ) {}

    public static function for(string $scope, string $id, ?int $maxAttempts = null): self
    {
        return new self(
            key: "kiosk-lockout:{$scope}:".md5($id),
            maxAttempts: max(1, $maxAttempts ?? (int) config("kiosk.{$scope}_max_attempts")),
            baseSeconds: max(1, (int) config("kiosk.{$scope}_lockout_seconds")),
            multiplier: max(1, (int) config("kiosk.{$scope}_lockout_multiplier")),
            capSeconds: max(1, (int) config("kiosk.{$scope}_lockout_max_seconds")),
            ttl: max(60, (int) config("kiosk.{$scope}_counter_ttl")),
        );
    }

    public function remainingSeconds(): int
    {
        return max(0, $this->state()['locked_until'] - now()->timestamp);
    }

    public function attemptsLeft(): int
    {
        return max(0, $this->maxAttempts - $this->state()['failures']);
    }

    /**
     * Record one failure. Returns the seconds the caller is now locked for (0 = still has free attempts).
     * The mutex stops parallel requests from slipping extra guesses past the counter.
     */
    public function hit(): int
    {
        $record = function (): int {
            $state = $this->state();
            $state['failures']++;

            if ($state['failures'] >= $this->maxAttempts) {
                $state['locked_until'] = now()->timestamp + $this->secondsForFailure($state['failures'] - $this->maxAttempts);
            }

            Cache::put($this->key, $state, $this->ttl);

            return max(0, $state['locked_until'] - now()->timestamp);
        };

        try {
            return Cache::lock($this->key.':mutex', 5)->block(3, $record);
        } catch (LockTimeoutException) {
            return $record();
        }
    }

    /**
     * Lock for a fixed time, e.g. because the API itself told us to back off.
     */
    public function lockFor(int $seconds): void
    {
        $state = $this->state();
        $state['locked_until'] = now()->timestamp + $seconds;

        Cache::put($this->key, $state, max($this->ttl, $seconds));
    }

    public function clear(): void
    {
        Cache::forget($this->key);
    }

    /**
     * 0 → base, 1 → base × multiplier, 2 → base × multiplier², … up to the cap.
     */
    private function secondsForFailure(int $excess): int
    {
        return (int) min($this->baseSeconds * ($this->multiplier ** $excess), $this->capSeconds);
    }

    /**
     * @return array{failures: int, locked_until: int}
     */
    private function state(): array
    {
        return Cache::get($this->key, ['failures' => 0, 'locked_until' => 0]);
    }
}
