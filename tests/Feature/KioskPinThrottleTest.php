<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();

    config([
        'kiosk.pin_max_attempts' => 3,
        'kiosk.pin_lockout_seconds' => 30,
        'kiosk.pin_lockout_multiplier' => 2,
        'kiosk.pin_lockout_max_seconds' => 900,
    ]);

    session([
        'kiosk_card' => ['uid' => 'ABC123', 'balance' => 100],
        'kiosk_user' => ['id' => 7, 'name' => 'Ana Cruz', 'role' => 'commuter'],
    ]);
});

it('warns first, then locks the PIN pad for longer after every further wrong PIN', function () {
    Http::fake(['*/api/card/verify' => Http::response(['message' => 'Invalid PIN number.'], 422)]);

    $component = Livewire::test('pages::menu-options');

    $component->call('verifyPin', '111111')
        ->assertSet('pinLockedFor', 0)
        ->assertSet('pinError', 'Invalid PIN number. 2 attempts left.');

    $component->call('verifyPin', '111111')
        ->assertSet('pinError', 'Invalid PIN number. 1 attempt left.');

    $component->call('verifyPin', '111111')->assertSet('pinLockedFor', 30);

    $this->travel(31)->seconds();
    $component->call('verifyPin', '111111')->assertSet('pinLockedFor', 60);

    $this->travel(61)->seconds();
    $component->call('verifyPin', '111111')->assertSet('pinLockedFor', 120);
});

it('does not call the API while locked, even with the right PIN', function () {
    Http::fake(['*/api/card/verify' => Http::response(['message' => 'Invalid PIN number.'], 422)]);

    $component = Livewire::test('pages::menu-options');

    foreach (range(1, 3) as $_) {
        $component->call('verifyPin', '111111');
    }

    Http::assertSentCount(3);

    $component->call('verifyPin', '123456')->assertSet('pinError', 'Too many incorrect attempts.');

    Http::assertSentCount(3);
});

it('keeps the lock when the card is tapped again in a new session', function () {
    Http::fake(['*/api/card/verify' => Http::response(['message' => 'Invalid PIN number.'], 422)]);

    $component = Livewire::test('pages::menu-options');

    foreach (range(1, 3) as $_) {
        $component->call('verifyPin', '111111');
    }

    Livewire::test('pages::menu-options')->assertSet('pinLockedFor', 30);
});

it('clears the counter after a correct PIN', function () {
    Http::fake([
        '*/api/card/verify' => Http::sequence()
            ->push(['message' => 'Invalid PIN number.'], 422)
            ->push(['success' => true], 200)
            ->push(['message' => 'Invalid PIN number.'], 422),
    ]);

    $component = Livewire::test('pages::menu-options');

    $component->call('verifyPin', '111111');
    $component->call('verifyPin', '123456')->assertReturned(true);
    $component->call('verifyPin', '111111')->assertSet('pinError', 'Invalid PIN number. 2 attempts left.');
});

it('rejects a PIN that is not six digits without counting it', function () {
    Http::fake();

    Livewire::test('pages::menu-options')
        ->call('verifyPin', '12ab')
        ->assertSet('pinError', 'Enter all 6 digits of your PIN.');

    Http::assertNothingSent();
});

it('locks the email sign-in with growing delays and never says whether the account exists', function () {
    config(['kiosk.login_max_attempts' => 2, 'kiosk.login_lockout_seconds' => 30]);
    Http::fake(['*/api/kiosk/login' => Http::response(['message' => 'No account found for that email.'], 401)]);

    $component = Livewire::test('pages::login')
        ->set('email_address', 'ana@example.com')
        ->set('password', 'wrong');

    $component->call('login')
        ->assertSet('errorMessage', 'Incorrect email or password. 1 attempt left.')
        ->assertSet('password', '');

    $component->set('password', 'wrong')->call('login')
        ->assertSet('lockedFor', 30)
        ->assertDispatched('kiosk-login-locked', seconds: 30);

    Http::assertSentCount(2);

    $component->set('password', 'right')->call('login');
    Http::assertSentCount(2);

    $this->travel(31)->seconds();
    $component->set('password', 'wrong')->call('login')->assertSet('lockedFor', 60);
});

it('opens the balance only once per PIN entry', function () {
    session(['kiosk_pin_verified_at' => now()->timestamp]);

    Livewire::test('pages::view-balance')->assertNoRedirect();

    expect(session()->has('kiosk_pin_verified_at'))->toBeFalse();

    Livewire::test('pages::view-balance')->assertRedirect(route('menu.options'));
});

it('hides the balance and returns to the menu when the timer runs out', function () {
    session(['kiosk_pin_verified_at' => now()->timestamp]);

    Livewire::test('pages::view-balance')
        ->call('hideBalance')
        ->assertRedirect(route('menu.options'));
});

it('does not call a timed-out fare payment "denied" and tells the person not to pay again', function () {
    Http::fake([
        '*/api/queued/routes' => Http::response(['data' => ['Naga' => [[
            'type' => 'Jeep', 'fare' => 50, 'plate_number' => 'ABC 123',
            'is_full' => false, 'capacity_current' => 1, 'capacity_max' => 16,
        ]]]]),
        '*/api/card/verify' => Http::response(['success' => true]),
        '*/api/cards/tap' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timed out'),
    ]);

    Livewire::test('pages::route-selection')
        ->set('selectedRide', 'Naga|Jeep')
        ->call('verifyPin', '123456')
        ->call('confirmPayment')
        ->assertDispatched('modal-show')
        ->assertNoRedirect();
});
