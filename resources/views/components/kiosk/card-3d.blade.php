{{--
    Rotatable 3D SmartICCT card (front + back artwork).

    Same idea as the card on the public landing page, tuned for a touchscreen:
      • drag to rotate in any direction (pointer events: works for touch and mouse)
      • on release it settles on the nearest face, so the back stays visible after a flip
      • a plain tap flips it, and any control can flip it with:
            @click="$dispatch('kiosk-flip-card')"

    Expects  public/images/card_front.svg  and  public/images/card_back.svg
--}}
@props(['class' => 'max-w-[340px]', 'tiltX' => 8, 'tiltY' => -14, 'tiltZ' => 3])

<div class="flex w-full justify-center [perspective:1800px]">
    <div
        x-data="{
            dragging: false, moved: false,
            angle: 0, rotX: 0, rotY: 0,
            startX: 0, startY: 0,
            baseX: {{ $tiltX }}, baseY: {{ $tiltY }}, baseZ: {{ $tiltZ }},
            down(e) {
                this.dragging = true; this.moved = false;
                this.startX = e.clientX; this.startY = e.clientY;
            },
            move(e) {
                if (!this.dragging) return;
                const dx = e.clientX - this.startX, dy = e.clientY - this.startY;
                if (Math.abs(dx) + Math.abs(dy) > 8) this.moved = true;
                this.rotY = dx * 0.9;
                this.rotX = Math.max(-25, Math.min(25, -dy * 0.4));
            },
            up() {
                if (!this.dragging) return;
                this.dragging = false;
                this.angle = this.moved
                    ? Math.round((this.angle + this.rotY) / 180) * 180
                    : this.angle + 180;
                this.rotX = 0; this.rotY = 0;
            },
            flip() { this.angle += 180; }
        }"
        @pointerdown="down($event)"
        @pointermove.window="move($event)"
        @pointerup.window="up()"
        @pointercancel.window="up()"
        @kiosk-flip-card.window="flip()"
        role="img"
        aria-label="SmartICCT card. Drag or tap to turn it over."
        class="{{ $class }} aspect-[320/208] w-full cursor-grab select-none active:cursor-grabbing"
        :class="!dragging && 'transition-transform duration-500 ease-out'"
        :style="`touch-action:none; transform-style:preserve-3d;
            transform: rotateX(${baseX + rotX}deg) rotateY(${baseY + angle + rotY}deg) rotateZ(${baseZ}deg);`"
    >
        <div class="absolute inset-0 overflow-hidden rounded-2xl shadow-2xl"
             style="backface-visibility:hidden;-webkit-backface-visibility:hidden;">
            <img src="{{ asset('images/card_front.svg') }}" alt="" draggable="false"
                 class="pointer-events-none h-full w-full object-cover">
        </div>
        <div class="absolute inset-0 overflow-hidden rounded-2xl shadow-2xl"
             style="transform:rotateY(180deg);backface-visibility:hidden;-webkit-backface-visibility:hidden;">
            <img src="{{ asset('images/card_back.svg') }}" alt="" draggable="false"
                 class="pointer-events-none h-full w-full object-cover">
        </div>
    </div>
</div>