@php
    // Attract screen (home) gets the terminal photo; every task screen is solid navy.
    $isAttract   = request()->routeIs('kiosk.home');
    $isSignedIn  = session()->has('kiosk_card');
    $kioskName   = (string) data_get(session('kiosk_user'), 'name', 'Cardholder');
    $nameParts   = preg_split('/\s+/', trim($kioskName)) ?: [];
    $initials    = strtoupper(mb_substr($nameParts[0] ?? 'C', 0, 1) . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : ''));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

        <title>{{ $title ?? 'SmartICCT Kiosk' }}</title>

        <link rel="icon" href="{{ asset('images/logo.png') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles

        {{-- This is a fixed kiosk terminal, not a general browser — it must always render in
             the dark theme regardless of the device's OS/browser light-dark preference. --}}
        <style>
            :root.dark { color-scheme: dark; }
        </style>

    </head>
    <body class="h-full overflow-hidden antialiased">
        <div class="kiosk-stage">
        @if ($isAttract)
            <div class="fixed inset-0 overflow-hidden bg-k-950">
                {{-- origin-bottom + scale crops the top edge of the photo --}}
                <div
                    class="absolute inset-0 origin-bottom scale-110 bg-cover bg-[position:50%_30%]"
                    style="background-image: url('{{ asset('images/terminal-bg.jpeg') }}')"
                ></div>
                <div class="absolute inset-0 bg-gradient-to-r from-k-950/95 via-k-950/80 to-k-950/10"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-k-950/95 via-transparent to-transparent"></div>
            </div>
        @else
            <div class="fixed inset-0 bg-k-900 [background-image:radial-gradient(900px_420px_at_88%_-8%,rgba(255,215,0,.07),transparent_62%)]"></div>
        @endif

        <div class="relative flex h-full flex-col">
            {{-- App bar: who is signed in + sign out, and the date/time. Nothing else. --}}
            <header class="relative z-20 flex shrink-0 items-center justify-between {{ $isAttract ? 'h-[104px] px-10 pt-3' : 'h-[72px] border-b border-white/15 bg-k-950 px-8' }}">
                <a href="{{ route('kiosk.home') }}" wire:navigate class="flex items-center gap-3.5">
                    <img src="{{ asset('images/logo.png') }}" alt="SmartICCT" class="h-[46px] w-auto">
                    <div class="flex flex-col leading-tight">
                        <span class="font-primary text-[22px] font-extrabold text-white">SmartICCT</span>
                        <span class="font-secondary text-base text-tx-3">Iriga City Central Terminal</span>
                    </div>
                </a>

                <div class="flex items-center gap-3.5">
                    @if ($isSignedIn)
                        {{-- Tapping the name opens "My Card" (card + balance). The balance itself is never shown in the bar. --}}
                        <a
                            href="{{ route('view.balance') }}"
                            wire:navigate
                            class="flex h-14 max-w-[340px] items-center gap-3.5 rounded-full border-2 border-white/30 bg-k-800 py-0 pl-[7px] pr-6 text-white"
                            aria-label="My card — {{ $kioskName }}"
                        >
                            <span class="grid size-[42px] shrink-0 place-items-center rounded-full bg-secondary font-primary text-[17px] font-extrabold tracking-wide text-primary">{{ $initials }}</span>
                            <span class="truncate text-[19px] font-bold">{{ $kioskName }}</span>
                        </a>

                        <a
                            href="{{ route('kiosk.reset') }}"
                            class="flex h-14 items-center gap-2 rounded-2xl border-2 border-white/30 px-5 text-lg font-bold text-white transition hover:bg-white/10"
                        >
                            <flux:icon name="arrow-right-start-on-rectangle" class="size-6" />
                            Sign out
                        </a>
                    @endif

                    <div
                        x-data="{ now: new Date() }"
                        x-init="setInterval(() => now = new Date(), 1000)"
                        class="min-w-[150px] text-right leading-[1.15] {{ $isAttract ? 'rounded-2xl border border-white/15 bg-k-950/70 px-4 py-2' : '' }}"
                    >
                        <div
                            class="whitespace-nowrap font-secondary text-base font-medium text-tx-3"
                            x-text="now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })"
                        ></div>
                        <div
                            class="font-primary text-2xl font-extrabold text-white tabular-nums"
                            x-text="now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })"
                        ></div>
                    </div>
                </div>
            </header>

            <main class="relative flex min-h-0 flex-1 flex-col overflow-y-auto">
                {{ $slot }}
            </main>
        </div>

        {{-- Idle watchdog.
             Previously this reset the terminal silently at 60s, which could yank someone
             out mid-transaction (e.g. while slowly typing a driver name on a touch
             keyboard). Now it warns at 45s and gives a visible 15s countdown with a
             "I'm still here" button — standard kiosk behaviour. Only active once
             somebody is actually signed in; the attract screen has nothing to reset. --}}
        @if (session()->has('kiosk_card'))
            <div
                x-data="{
                    idleMs: 45000,
                    graceSec: 60,
                    warning: false,
                    remaining: 15,
                    idleTimer: null,
                    tickTimer: null,

                    start() {
                        clearTimeout(this.idleTimer);
                        this.idleTimer = setTimeout(() => this.warn(), this.idleMs);
                    },
                    warn() {
                        this.warning = true;
                        this.remaining = this.graceSec;
                        this.tickTimer = setInterval(() => {
                            this.remaining--;
                            if (this.remaining <= 0) {
                                clearInterval(this.tickTimer);
                                window.location.href = '{{ route('kiosk.reset') }}';
                            }
                        }, 1000);
                    },
                    stayActive() {
                        clearInterval(this.tickTimer);
                        this.warning = false;
                        this.start();
                    },
                    bump() {
                        if (this.warning) return;   // only the button dismisses the warning
                        this.start();
                    }
                }"
                x-init="start()"
                @click.window="bump()"
                @touchstart.window="bump()"
                @keydown.window="bump()"
            >
                <div
                    x-show="warning"
                    x-cloak
                    x-transition.opacity
                    class="fixed inset-0 z-50 flex items-center justify-center bg-k-950/90 p-6"
                >
                    <div class="w-full max-w-xl rounded-[2rem] border-2 border-white/30 bg-k-800 p-9 text-center shadow-2xl">
                        <div class="relative mx-auto size-[150px]">
                            <svg class="-rotate-90" width="150" height="150" viewBox="0 0 150 150" aria-hidden="true">
                                <circle cx="75" cy="75" r="62" fill="none" stroke="rgb(255 255 255 / .14)" stroke-width="12" />
                                <circle
                                    cx="75" cy="75" r="62" fill="none" stroke-width="12" stroke-linecap="round"
                                    class="stroke-secondary"
                                    stroke-dasharray="389.6"
                                    :stroke-dashoffset="389.6 * (1 - remaining / graceSec)"
                                />
                            </svg>
                            <div class="absolute inset-0 grid place-items-center font-primary text-5xl font-extrabold text-white tabular-nums" x-text="remaining"></div>
                        </div>

                        <h2 class="mt-5 font-primary text-[34px] font-extrabold text-white">Are you still there?</h2>

                        <p class="mb-7 mt-2 font-secondary text-2xl text-tx-2">
                            Signing out in
                            <span class="font-bold text-secondary tabular-nums" x-text="remaining"></span>
                            seconds
                        </p>

                        <div class="flex gap-4">
                            <x-kiosk.button variant="second" size="xl" class="flex-1" href="{{ route('kiosk.reset') }}">Sign out now</x-kiosk.button>
                            <x-kiosk.button size="xl" class="flex-1" @click="stayActive()">I'm still here</x-kiosk.button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        </div>{{-- /kiosk-stage --}}

        @livewireScripts
        @fluxScripts

        @persist('toast')
            <flux:toast position="top end" />
        @endpersist
    </body>
</html>