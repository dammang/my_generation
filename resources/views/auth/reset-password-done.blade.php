<x-layouts.auth
    title="Password changed"
    heading="Your password is changed"
>
    <div class="rounded-2xl border border-black/10 dark:border-white/10 bg-white dark:bg-field-dark p-6 flex flex-col gap-4">
        <p class="text-sm leading-relaxed">
            Open {{ config('app.name') }} on your phone and sign in with your new password.
        </p>
        <p class="text-sm leading-relaxed text-ink/70 dark:text-ink-dark/70">
            You were signed out on every device, including this one if you were signed in.
            That is deliberate: if somebody else had access to your account, they no longer do.
        </p>
    </div>
</x-layouts.auth>
