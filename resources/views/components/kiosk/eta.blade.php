{{--
    Departure-time plate. Replaces the countdown block that was copy-pasted into
    queue-live and (twice) route-selection. Same countdown logic as before:
    counts down to `timestamp` (ms epoch), turns red under 30s, says "Now" once
    it passes, and "when full" when there is no timestamp.
--}}
@props(['timestamp' => null, 'full' => false, 'size' => 'md'])

@php
    $endTime = $timestamp ? (int) $timestamp : null;
    $plate = 'flex flex-col items-center justify-center rounded-[14px] border-2 border-[#2B3272] bg-[#070A26] px-3.5 py-1.5 shadow-[inset_0_2px_10px_rgba(0,0,0,.5)] '
        . ($size === 'sm' ? 'h-16 min-w-[132px]' : 'h-[76px] min-w-[146px]');
    $digits = $size === 'sm' ? 'text-[34px]' : 'text-[40px]';
@endphp

@if ($full)
    <div {{ $attributes->class([$plate]) }}>
        <small class="text-base leading-none text-tx-3">Vehicle is</small>
        <b class="font-primary text-[32px] font-extrabold leading-tight text-stop">Full</b>
    </div>
@else
    <div
        {{ $attributes->class([$plate]) }}
        x-data="{
            endTime: {{ $endTime ?? 'null' }},
            display: '{{ $endTime ? '--:--' : 'when full' }}',
            state: '{{ $endTime ? 'go' : 'wait' }}',
            urgent: false,
            intervalId: null,
            init() {
                if (!this.endTime) return;
                this.update();
                this.intervalId = setInterval(() => this.update(), 1000);
            },
            destroy() { if (this.intervalId) clearInterval(this.intervalId); },
            update() {
                const remaining = this.endTime - Date.now();
                if (remaining <= 0) {
                    this.display = 'Now'; this.state = 'now'; this.urgent = false;
                    if (this.intervalId) clearInterval(this.intervalId);
                    return;
                }
                this.urgent = remaining < 30000;
                const m = String(Math.floor(remaining / 60000)).padStart(2, '0');
                const s = String(Math.floor((remaining % 60000) / 1000)).padStart(2, '0');
                this.display = `${m}:${s}`;
            }
        }"
    >
        <small class="text-base leading-none text-tx-3" x-text="state === 'now' ? 'Departing' : (state === 'wait' ? 'Leaves' : 'Leaves in')">Leaves in</small>
        <b
            class="font-primary font-extrabold leading-tight tabular-nums"
            :class="[
                state === 'wait' ? 'text-[26px] text-tx-2' : '{{ $digits }}',
                state === 'now' ? 'text-go' : (urgent ? 'text-stop' : (state === 'wait' ? '' : 'text-secondary')),
            ]"
            x-text="display"
        >{{ $endTime ? '--:--' : 'when full' }}</b>
    </div>
@endif
