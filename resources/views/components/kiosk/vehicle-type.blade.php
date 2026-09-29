{{--
    Vehicle-type pill. ONE colour rule for the whole kiosk (queue-vehicle used to
    colour "Aircon Bus" yellow while view-routes coloured it indigo).
--}}
@props(['type'])

@php
    $normalized = strtolower((string) $type);

    $tone = match (true) {
        str_contains($normalized, 'jeep')     => 'bg-amber-400/15 text-amber-300',
        str_contains($normalized, 'multicab') => 'bg-pink-400/15 text-pink-300',
        str_contains($normalized, 'aircon')   => 'bg-violet-400/20 text-violet-300',
        str_contains($normalized, 'uv')       => 'bg-emerald-400/15 text-emerald-300',
        str_contains($normalized, 'bus')      => 'bg-blue-400/15 text-blue-300',
        default                               => 'bg-white/10 text-white/80',
    };
@endphp

<span {{ $attributes->class(['inline-flex h-8 items-center gap-2 whitespace-nowrap rounded-[.625rem] px-3 text-base font-bold', $tone]) }}>
    <span class="size-2.5 rounded-[3px] bg-current"></span>{{ $type }}
</span>
