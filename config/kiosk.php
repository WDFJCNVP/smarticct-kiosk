<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kiosk Idle Timeout
    |--------------------------------------------------------------------------
    |
    | Mirrors how Laravel's own SESSION_LIFETIME works: the values live in .env,
    | not inside the Blade files. After `idle_seconds` with no touch/keypress the
    | "Are you still there?" warning appears; if nobody answers within
    | `warning_seconds` the signed-in card is cleared and the attract screen shows.
    |
    */

    'idle_seconds' => (int) env('KIOSK_IDLE_SECONDS', 45),

    'warning_seconds' => (int) env('KIOSK_WARNING_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Success Screen Return Delay
    |--------------------------------------------------------------------------
    |
    | After a fare payment or queue entry, the receipt screen stays up this long
    | and then returns to the menu (the person stays signed in).
    |
    */

    'done_return_seconds' => (int) env('KIOSK_DONE_RETURN_SECONDS', 12),

    /*
    |--------------------------------------------------------------------------
    | PIN Attempt Limiter
    |--------------------------------------------------------------------------
    |
    | Wrong PINs are counted per cardholder (not per browser session, so tapping
    | the card again does not reset them). The first `pin_max_attempts` wrong
    | entries only warn; from then on every further wrong entry locks the PIN pad
    | for longer: base * multiplier^n seconds, capped at `pin_lockout_max_seconds`.
    | With the defaults: 30s, 60s, 2m, 4m, 8m, then 15m. A correct PIN, or
    | `pin_counter_ttl` seconds without a wrong entry, clears the counter.
    |
    */

    'pin_max_attempts' => (int) env('KIOSK_PIN_MAX_ATTEMPTS', 3),

    'pin_lockout_seconds' => (int) env('KIOSK_PIN_LOCKOUT_SECONDS', 30),

    'pin_lockout_multiplier' => (int) env('KIOSK_PIN_LOCKOUT_MULTIPLIER', 2),

    'pin_lockout_max_seconds' => (int) env('KIOSK_PIN_LOCKOUT_MAX_SECONDS', 900),

    'pin_counter_ttl' => (int) env('KIOSK_PIN_COUNTER_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Verify the SmartICCT API's TLS certificate
    |--------------------------------------------------------------------------
    |
    | PINs travel to the API, so this stays on. Only set it to false on a dev
    | machine that lacks a CA bundle.
    |
    */

    'verify_ssl' => (bool) env('KIOSK_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Email / Password Sign-in Limiter
    |--------------------------------------------------------------------------
    |
    | Same escalating lockout as the PIN, applied per email address and, with a
    | higher ceiling, to the kiosk as a whole so one terminal cannot be used to
    | try many different accounts. A correct sign-in clears that email's counter
    | but not the terminal's.
    |
    */

    'login_max_attempts' => (int) env('KIOSK_LOGIN_MAX_ATTEMPTS', 5),

    'login_device_max_attempts' => (int) env('KIOSK_LOGIN_DEVICE_MAX_ATTEMPTS', 10),

    'login_lockout_seconds' => (int) env('KIOSK_LOGIN_LOCKOUT_SECONDS', 30),

    'login_lockout_multiplier' => (int) env('KIOSK_LOGIN_LOCKOUT_MULTIPLIER', 2),

    'login_lockout_max_seconds' => (int) env('KIOSK_LOGIN_LOCKOUT_MAX_SECONDS', 900),

    'login_counter_ttl' => (int) env('KIOSK_LOGIN_COUNTER_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Balance Auto-Hide
    |--------------------------------------------------------------------------
    |
    | "My Card" shows the balance for this many seconds and then returns to the
    | menu, so it is not left on screen for the next person. Each PIN entry
    | opens it exactly once.
    |
    */

    'balance_visible_seconds' => (int) env('KIOSK_BALANCE_VISIBLE_SECONDS', 20),

];