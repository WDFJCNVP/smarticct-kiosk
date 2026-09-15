<?php

use Illuminate\Support\Facades\Route;


// ── Public attract screen (guest entry point) ──────────────────────────
Route::livewire('/', 'pages::kiosk-home')->name('kiosk.home');

// ── Info screens (reachable from the menu after signing in) ────────────
Route::livewire('/routes', 'pages::view-routes')->name('view.routes');
Route::livewire('/queue', 'pages::queue-live')->name('guest.queue');

// ── Sign in ──────────────────────────────────────────────────────────
Route::livewire('/sign-in', 'pages::login-options')->name('login.options');
Route::livewire('/tap', 'pages::tap')->name('login.tap');
Route::livewire('/sign-in/credentials', 'pages::login')->name('login.email');

// ── Authenticated kiosk session ─────────────────────────────────────────
Route::livewire('/menu', 'pages::menu-options')->name('menu.options');
Route::livewire('/queue/vehicle', 'pages::queue-vehicle')->name('queue.vehicle');   // operator
Route::livewire('/fare/pay', 'pages::route-selection')->name('route.select');        // commuter
Route::livewire('/balance', 'pages::view-balance')->name('view.balance');            // operator + commuter

// ── Idle timeout: called by the layout's inactivity timer ───────────────
Route::get('/reset', function () {
    session()->forget(['kiosk_card', 'kiosk_user', 'kiosk_vehicle', 'kiosk_route_list', 'kiosk_verified_at']);
    return redirect()->route('kiosk.home');
})->name('kiosk.reset');