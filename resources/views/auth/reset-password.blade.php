@php
    $email = old('email', $email);
    $token = old('token', $token);
    // A link without both halves cannot be completed, and showing a form that
    // is guaranteed to fail just moves the disappointment later.
    $linkIsUsable = $token !== '' && $email !== '';
@endphp

<x-layouts.auth
    :heading="$linkIsUsable ? 'Choose a new password' : 'That link is not complete'"
    :subheading="$linkIsUsable ? $email : null"
>
    @if (! $linkIsUsable)
        <div class="rounded-2xl border border-black/10 dark:border-white/10 bg-white dark:bg-field-dark p-6 flex flex-col gap-4">
            <p class="text-sm leading-relaxed">
                This page needs the full link from your email. Some mail apps shorten long
                links — try opening it again, or copying the whole address into your browser.
            </p>
            <p class="text-sm leading-relaxed text-ink/70 dark:text-ink-dark/70">
                If it still does not work, ask for a new link from the app. Reset links
                expire, so the most recent one is the one that counts.
            </p>
        </div>
    @else
        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            {{-- Sent as a field because the reset is verified against it, and
                 shown read-only so nobody resets the wrong account by habit. --}}
            <input type="hidden" name="email" value="{{ $email }}">

            @error('email')
                <p class="rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    {{ $message }}
                </p>
            @enderror

            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-medium">New password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autofocus
                    autocomplete="new-password"
                    minlength="8"
                    aria-describedby="password-hint @error('password') password-error @enderror"
                    class="w-full rounded-xl border border-black/15 dark:border-white/15 bg-white dark:bg-field-dark px-4 py-3 text-base outline-none focus:border-brand focus:ring-2 focus:ring-brand/30 dark:focus:border-brand-soft dark:focus:ring-brand-soft/30"
                >
                <p id="password-hint" class="text-xs text-ink/60 dark:text-ink-dark/60">
                    At least 8 characters, with letters and numbers.
                </p>
                @error('password')
                    <p id="password-error" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="password_confirmation" class="text-sm font-medium">Repeat new password</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    minlength="8"
                    class="w-full rounded-xl border border-black/15 dark:border-white/15 bg-white dark:bg-field-dark px-4 py-3 text-base outline-none focus:border-brand focus:ring-2 focus:ring-brand/30 dark:focus:border-brand-soft dark:focus:ring-brand-soft/30"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-full bg-brand dark:bg-brand-soft px-6 py-3.5 text-base font-semibold text-white transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-brand/40 focus:ring-offset-2 focus:ring-offset-surface dark:focus:ring-offset-surface-dark"
            >
                Set new password
            </button>

            <p class="text-center text-xs text-ink/60 dark:text-ink-dark/60">
                This signs you out everywhere else.
            </p>
        </form>
    @endif
</x-layouts.auth>
