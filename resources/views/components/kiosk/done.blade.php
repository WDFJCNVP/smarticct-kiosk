{{--
    Full-screen "take your ticket" confirmation. The menu screen renders this from
    the `kiosk_done` flash that route-selection / queue-vehicle now set on success
    (it replaces the small corner toast). "Done" signs out via the menu's existing
    signOut(); it also signs out by itself after 12s.
--}}
@props(['done', 'isOperator' => false])

@php
    $receipt  = $done['receipt'] ?? [];
    $isQueue  = ($done['kind'] ?? '') === 'queue';
    $amount   = (float) ($receipt['fare'] ?? $receipt['fee'] ?? 0);
    $plateNo  = $receipt['plate_number'] ?? '—';
    $vehicle  = $receipt['vehicle_type'] ?? '';
    $dest     = $receipt['destination'] ?? '—';
    $line     = 'flex justify-between gap-2';
@endphp

<div
    class="flex min-h-0 flex-1 flex-col"
    x-data="{
        timer: null,
        init() { this.timer = setTimeout(() => $wire.signOut(), 12000); },
        destroy() { clearTimeout(this.timer); }
    }"
>
    <div class="grid min-h-0 flex-1 grid-cols-[1fr_380px] gap-9 px-10 py-7">
        <div class="flex flex-col justify-center gap-5">
            <div class="kiosk-pop grid size-28 place-items-center rounded-full bg-go text-[#0A3B22]">
                <flux:icon name="check" class="size-16 stroke-[2.4]" />
            </div>

            <div>
                <h1 class="font-primary text-[56px] font-extrabold leading-[1.05] tracking-tight text-white">
                    {{ $isQueue ? 'Vehicle queued' : 'Payment successful' }}
                </h1>
                <p class="mt-2 text-[28px] text-tx-2">
                    {{ $isQueue ? 'Take your queue slip and go to staging.' : 'Take your boarding pass from the slot.' }}
                </p>
            </div>

            <dl class="rounded-3xl border-2 border-white/15 bg-k-800 px-6 py-2 text-[22px]">
                <div class="{{ $line }} items-center py-3">
                    <dt class="text-tx-2">Destination</dt>
                    <dd class="font-bold text-white">Iriga → {{ $dest }}</dd>
                </div>
                <div class="{{ $line }} items-center border-t border-white/15 py-3">
                    <dt class="text-tx-2">Vehicle</dt>
                    <dd class="flex items-center gap-2 font-bold text-white">
                        <x-kiosk.plate>{{ $plateNo }}</x-kiosk.plate>
                        @if ($vehicle) <x-kiosk.vehicle-type :type="$vehicle" /> @endif
                    </dd>
                </div>
                <div class="{{ $line }} items-center border-t border-white/15 py-3">
                    <dt class="text-tx-2">{{ $isQueue ? 'Queue fee' : 'Fare paid' }}</dt>
                    <dd class="font-bold text-white">₱{{ number_format($amount, 2) }}</dd>
                </div>
            </dl>
        </div>

        {{-- Printer slot + the ticket sliding out (mirrors the printed slip) --}}
        <div class="flex flex-col items-center pt-2">
            <div class="mb-2.5 flex items-center gap-2 text-lg text-tx-3">
                <flux:icon name="printer" class="size-6" /> Printing…
            </div>
            <div class="relative z-10 h-[26px] w-[360px] rounded-2xl border-2 border-white/30 bg-[#04061A]">
                <span class="absolute right-4 top-[7px] size-2 rounded-full bg-go shadow-[0_0_8px_var(--color-go)]"></span>
            </div>
            <div class="-mt-2 h-[420px] w-[300px] overflow-hidden">
                <div class="kiosk-ticket bg-[#FBFAF3] px-5 pb-9 pt-6 font-mono text-base leading-6 text-[#161A44]">
                    <div class="text-center">
                        <b class="text-[22px]">SMART ICCT</b><br>
                        {{ $isQueue ? 'KIOSK QUEUE SLIP' : 'PASSENGER BOARDING PASS' }}
                    </div>
                    <hr class="my-2 border-0 border-t-2 border-dashed border-[#161A44]/50">
                    <div class="{{ $line }}"><span>{{ $isQueue ? 'Ref:' : 'Ticket No:' }}</span><span>{{ \Illuminate\Support\Str::limit($receipt['reference_no'] ?? '—', 14, '') }}</span></div>
                    <div class="{{ $line }}"><span>Date:</span><span>{{ $receipt['date'] ?? '—' }}</span></div>
                    @if ($isQueue)
                        <div class="{{ $line }}"><span>Operator:</span><span>{{ \Illuminate\Support\Str::limit($receipt['operator_name'] ?? '—', 12, '') }}</span></div>
                        <div class="{{ $line }}"><span>Driver:</span><span>{{ \Illuminate\Support\Str::limit($receipt['driver_name'] ?? '—', 12, '') }}</span></div>
                    @else
                        <div class="{{ $line }}"><span>Passenger:</span><span>{{ \Illuminate\Support\Str::limit($receipt['passenger_name'] ?? '—', 12, '') }}</span></div>
                    @endif
                    <hr class="my-2 border-0 border-t-2 border-dashed border-[#161A44]/50">
                    <div class="{{ $line }}"><b>{{ $isQueue ? 'Route:' : 'Destination:' }}</b><b>{{ \Illuminate\Support\Str::limit($dest, 14, '') }}</b></div>
                    <div class="{{ $line }}"><b>Plate:</b><b>{{ $plateNo }}</b></div>
                    <hr class="my-2 border-0 border-t-2 border-dashed border-[#161A44]/50">
                    <div class="{{ $line }}"><b>{{ $isQueue ? 'Queue Fee:' : 'Fare Paid:' }}</b><b>PHP {{ number_format($amount, 2) }}</b></div>
                    <hr class="my-2 border-0 border-t-2 border-dashed border-[#161A44]/50">
                    <div class="text-center">{{ $isQueue ? 'PROCEED TO STAGING' : 'SHOW THIS UPON BOARDING' }}</div>
                </div>
            </div>
        </div>
    </div>

    <x-kiosk.action-bar>
        <x-slot:back>
            <div class="w-[290px]">
                <div class="text-lg text-tx-2">Signing out automatically</div>
                <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-white/15">
                    <i class="kiosk-shrink block h-full w-full origin-left bg-secondary"></i>
                </div>
            </div>
        </x-slot:back>

        <x-slot:primary>
            <div class="flex gap-4">
                <x-kiosk.button
                    variant="second" size="xl"
                    href="{{ $isOperator ? route('queue.vehicle') : route('route.select') }}"
                    wire:navigate
                >{{ $isOperator ? 'Queue another' : 'Pay another fare' }}</x-kiosk.button>
                <x-kiosk.button size="xl" class="w-[200px]" wire:click="signOut">Done</x-kiosk.button>
            </div>
        </x-slot:primary>
    </x-kiosk.action-bar>
</div>
