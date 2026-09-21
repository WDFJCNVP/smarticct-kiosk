{{--
    Fixed bottom bar: Back on the left, context in the middle, the one primary
    action on the right. Same place on every screen, so people learn it once.
--}}
<footer {{ $attributes->class('flex h-[104px] shrink-0 items-center gap-5 border-t border-white/15 bg-k-950 px-8') }}>
    {{ $back ?? '' }}

    <div class="flex min-w-0 flex-1 items-center justify-center gap-6">
        {{ $slot }}
    </div>

    {{ $primary ?? '' }}
</footer>
