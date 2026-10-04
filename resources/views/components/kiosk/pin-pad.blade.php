{{--
    PIN entry: a prompt and six circles. No keypad, no Enter key — the PIN is checked the moment
    the sixth digit is typed. Digits only: anything else is rejected as it is typed or pasted.

    An invisible, focused <input inputmode="numeric"> sits over the circles so the kiosk's
    physical keypad / the device's own numeric keyboard types into it. The digits are never
    displayed and leave this component only as the argument to the Livewire verifyPin() call.

    Needs the host component to expose (see App\Concerns\VerifiesKioskPin):
      verifyPin(string $pin): bool   $pinError   $pinLockedFor (seconds)
--}}
@props(['title' => 'Enter your PIN', 'subtitle' => null, 'length' => 6])

<div
    x-data="{
        length: {{ (int) $length }},
        pin: '',
        busy: false,
        success: false,
        shake: false,
        error: null,
        remaining: 0,
        lockedUntil: 0,
        ticker: null,

        get locked() { return this.remaining > 0; },
        get clock() {
            return Math.floor(this.remaining / 60) + ':' + String(this.remaining % 60).padStart(2, '0');
        },

        focusPin() {
            this.$nextTick(() => this.$refs.pin && this.$refs.pin.focus({ preventScroll: true }));
        },
        setLock(seconds) {
            clearInterval(this.ticker);
            this.lockedUntil = seconds > 0 ? Date.now() + seconds * 1000 : 0;
            this.tick();
            if (this.lockedUntil) this.ticker = setInterval(() => this.tick(), 250);
        },
        tick() {
            this.remaining = this.lockedUntil ? Math.max(0, Math.ceil((this.lockedUntil - Date.now()) / 1000)) : 0;
            if (this.lockedUntil && this.remaining === 0) {
                clearInterval(this.ticker);
                this.lockedUntil = 0;
                this.error = null;
                this.focusPin();
            }
        },
        reset() {
            this.pin = '';
            this.error = null;
            if (this.$refs.pin) this.$refs.pin.value = '';
        },

        /* Only digits get in: block letters/symbols as they are typed, strip anything pasted. */
        onBeforeInput(e) {
            if (e.data && /\D/.test(e.data)) e.preventDefault();
        },
        onInput(e) {
            const digits = e.target.value.replace(/\D/g, '').slice(0, this.length);
            e.target.value = digits;
            this.pin = digits;
            this.error = null;
            if (digits.length === this.length) setTimeout(() => this.submit(), 150);
        },
        refocusOnKey(e) {
            if (!this.$el.getClientRects().length || e.ctrlKey || e.metaKey || e.altKey) return;
            if (document.activeElement !== this.$refs.pin) this.focusPin();
        },

        async submit() {
            if (this.busy || this.pin.length !== this.length) return;
            this.busy = true;
            let ok = false;
            try {
                ok = (await $wire.verifyPin(this.pin)) === true;
                this.error = ok ? null : $wire.pinError;
            } catch (e) {
                this.error = 'Something went wrong. Please try again.';
            }
            this.setLock($wire.pinLockedFor);

            if (ok) {
                this.success = true;
                setTimeout(() => { this.reset(); this.success = false; this.busy = false; }, 500);
                return;
            }
            this.shake = true;
            setTimeout(() => { this.shake = false; this.reset(); this.busy = false; this.focusPin(); }, 450);
        }
    }"
    x-init="setLock($wire.pinLockedFor)"
    x-intersect:enter="reset(); $wire.refreshPinLock().then(() => { setLock($wire.pinLockedFor); focusPin(); })"
    x-intersect:leave="reset(); clearInterval(ticker)"
    @keydown.window="refocusOnKey($event)"
    @click="focusPin()"
    class="mx-auto w-full text-center"
>
    <h2 class="font-primary text-[52px] font-extrabold leading-tight text-white">{{ $title }}</h2>
    @if ($subtitle)
        <p class="mt-2 font-secondary text-2xl text-tx-2">{{ $subtitle }}</p>
    @endif

    {{-- The six circles, with the invisible input laid over them so tapping them brings up the keyboard --}}
    <div class="relative mx-auto mt-12 flex w-max justify-center gap-7" :class="shake && 'kiosk-shake'">
        <template x-for="n in length" :key="n">
            <span
                aria-hidden="true"
                class="size-12 rounded-full border-[3px] transition-colors duration-150"
                :class="{
                    'border-go bg-go': success,
                    'border-stop bg-stop': !success && shake,
                    'border-secondary bg-secondary': !success && !shake && n <= pin.length,
                    'border-stop/50': !success && !shake && n > pin.length && locked,
                    'border-white/50': !success && !shake && n > pin.length && !locked
                }"
            ></span>
        </template>

        <input
            x-ref="pin"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="{{ (int) $length }}"
            autocomplete="off"
            autocapitalize="off"
            autocorrect="off"
            spellcheck="false"
            enterkeyhint="done"
            aria-label="PIN"
            :readonly="busy || locked"
            @beforeinput="onBeforeInput($event)"
            @input="onInput($event)"
            @keydown.enter.prevent
            class="absolute inset-0 h-full w-full cursor-default bg-transparent text-transparent opacity-0 caret-transparent outline-none"
        >
    </div>

    {{-- One status line; reserved height so nothing jumps --}}
    <div class="mt-9 flex min-h-[88px] flex-col items-center justify-start" role="status" aria-live="polite">
        <template x-if="locked">
            <div>
                <p class="flex items-center justify-center gap-3 font-primary text-3xl font-extrabold text-stop">
                    <flux:icon name="lock-closed" class="size-8" /> Too many incorrect attempts
                </p>
                <p class="mt-1 font-secondary text-3xl text-tx-2">
                    Try again in <span class="font-bold tabular-nums text-white" x-text="clock"></span>
                </p>
            </div>
        </template>
        <template x-if="!locked && busy && !success">
            <p class="flex items-center gap-3 font-secondary text-3xl text-tx-2">
                <flux:icon name="arrow-path" class="size-8 animate-spin text-secondary" /> Checking…
            </p>
        </template>
        <template x-if="!locked && success">
            <p class="font-primary text-3xl font-extrabold text-go">PIN accepted</p>
        </template>
        <template x-if="!locked && !busy && !success && error">
            <p class="font-secondary text-3xl font-semibold text-stop" x-text="error"></p>
        </template>
    </div>

    <flux:modal.close class="mt-2 inline-block">
        <button type="button" class="rounded-xl px-6 py-3 font-secondary text-2xl font-semibold text-tx-2 underline underline-offset-4 hover:text-white">Cancel</button>
    </flux:modal.close>
</div>