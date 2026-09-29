{{-- Licence-plate chip. <x-kiosk.plate>TXY 482</x-kiosk.plate>   (lg for big lists) --}}
@props(['lg' => false])

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center whitespace-nowrap border-2 border-primary bg-[#F4EFD0] font-primary font-extrabold tracking-[.08em] text-primary shadow-[0_0_0_1px_#F4EFD0]',
    $lg ? 'h-11 rounded-[.625rem] px-3.5 text-2xl' : 'h-8 rounded-lg px-2.5 text-base',
]) }}>{{ $slot }}</span>
