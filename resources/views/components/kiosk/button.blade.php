{{--
    Kiosk button. One place for the touch-target sizing and the variants, so pages
    don't repeat "!h-16 !rounded-2xl !bg-secondary !text-primary …" everywhere.

    variant: primary (gold) | second | line | danger | off (disabled-looking)
    size:    sm (56px) | md (72px, default) | xl (84px)
    Pass href="…" to render a link (wire:navigate etc. pass straight through).
--}}
@props([
    'variant' => 'primary',
    'size'    => 'md',
    'href'    => null,
])

@php
    $sizes = [
        'sm' => 'h-14 px-5 text-lg',
        'md' => 'kiosk-tap-target h-[72px] px-7 text-[22px]',
        'xl' => 'kiosk-tap-target h-[84px] px-8 text-[26px]',
    ];

    $variants = [
        'primary' => 'border-transparent bg-secondary text-primary shadow-[0_8px_0_rgba(0,0,0,.28)] hover:bg-secondary-hover active:translate-y-1 active:shadow-[0_4px_0_rgba(0,0,0,.28)]',
        'second'  => 'border-white/30 bg-k-700 text-white hover:bg-k-600',
        'line'    => 'border-white/30 bg-transparent text-white hover:bg-white/10',
        'danger'  => 'border-stop/50 bg-transparent text-stop hover:bg-stop/10',
        'off'     => 'cursor-default border-dashed border-white/30 bg-transparent text-tx-3',
    ];

    $classes = 'inline-flex select-none items-center justify-center gap-3 whitespace-nowrap rounded-[1.25rem] border-2 font-primary font-extrabold transition disabled:cursor-default disabled:opacity-50 '
        . ($sizes[$size] ?? $sizes['md']) . ' '
        . ($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => $classes, 'type' => 'button']) }}>{{ $slot }}</button>
@endif
