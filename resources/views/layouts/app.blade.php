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
        <div class="fixed inset-0 overflow-hidden bg-dark-primary">
            <div
                class="absolute inset-0 scale-110 bg-cover bg-center opacity-90"
                style="background-image: url('{{ asset('images/terminal-bg.jpeg') }}')"
            ></div>
            <div class="absolute inset-0 bg-gradient-to-b from-[#0B0F2A]/85 via-[#10143A]/80 to-[#0B0F2A]/92"></div>
        </div>

        <div class="flex h-screen flex-col">
            <header class="flex shrink-0 items-center justify-between gap-3 border-b border-white/10 bg-white/5 px-4 py-3 backdrop-blur-md sm:px-8">
                <a href="{{ route('kiosk.home') }}" wire:navigate class="flex items-center gap-3">
                    <img src="{{ asset('images/logo.png') }}" alt="SmartICCT" class="h-9 w-auto sm:h-10">
                    <div class="flex flex-col leading-tight">
                        <span class="font-primary text-base font-bold text-white sm:text-lg">SmartICCT Kiosk</span>
                        <span class="font-secondary text-xs text-white/60">Iriga City Central Terminal</span>
                    </div>
                </a>

                <div
                    x-data="{ now: new Date() }"
                    x-init="setInterval(() => now = new Date(), 1000)"
                    class="font-secondary text-sm font-medium text-white/70 tabular-nums"
                    x-text="now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })"
                ></div>
            </header>

            <main class="flex flex-1 flex-col overflow-y-auto">
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
                    graceSec: 15,
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
                    class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B0F2A]/90 p-6 backdrop-blur-sm"
                >
                    <div class="w-full max-w-md rounded-3xl border border-white/15 bg-white/10 p-8 text-center backdrop-blur-md">
                        <flux:heading size="xl" class="font-primary font-extrabold text-white">
                            Are you still there?
                        </flux:heading>

                        <p class="mt-3 font-secondary text-white/70">
                            Signing out in
                            <span class="font-mono text-2xl font-bold text-secondary tabular-nums" x-text="remaining"></span>
                            seconds
                        </p>

                        <button
                            type="button"
                            @click="stayActive()"
                            class="kiosk-tap-target mt-7 w-full rounded-2xl bg-secondary px-6 text-xl font-bold text-primary shadow-lg shadow-secondary/20 transition hover:bg-secondary-hover"
                        >
                            I'm still here
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @livewireScripts
        @fluxScripts

        @persist('toast')
            <flux:toast position="top end" />
        @endpersist
    </body>
</html>