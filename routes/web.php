<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::livewire('/', 'pages::login-options')->name('login.options');
Route::livewire('/tap', 'pages::tap')->name('login.tap');
Route::livewire('/login', 'pages::login')->name('login.email');
Route::livewire('/route/selection','pages::route-selection')->name('route.select');
Route::livewire('/menu','pages::menu-options')->name('menu.options');
Route::livewire('/queue/vehicle', 'pages::queue-vehicle')->name('queue.vehicle');
Route::livewire('/view/balance', 'pages::view-balance')->name('view.balance');
Route::livewire('/view/routes/', 'pages::view-routes')->name('view.routes');
