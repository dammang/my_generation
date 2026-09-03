@php
    $copy = match ($state) {
        'verified' => [
            'title' => 'Email confirmed',
            'heading' => 'Your email is confirmed',
            'body' => 'You can go back to '.config('app.name').' on your phone. Nothing else to do here.',
            'aside' => null,
        ],
        'already' => [
            'title' => 'Already confirmed',
            'heading' => 'This was already confirmed',
            'body' => 'That address is confirmed, so there is nothing left to do. This happens when a link is opened twice.',
            'aside' => null,
        ],
        default => [
            'title' => 'Link not usable',
            'heading' => 'That link no longer works',
            'body' => 'Verification links expire, and they stop working if the address changed after the email was sent.',
            'aside' => 'Ask for a new one from your profile in the app. The most recent link is the one that counts.',
        ],
    };
@endphp

<x-layouts.auth
    :title="$copy['title']"
    :heading="$copy['heading']"
    :subheading="$email ?? null"
>
    <div class="rounded-2xl border border-black/10 dark:border-white/10 bg-white dark:bg-field-dark p-6 flex flex-col gap-4">
        <p class="text-sm leading-relaxed">{{ $copy['body'] }}</p>
        @if ($copy['aside'])
            <p class="text-sm leading-relaxed text-ink/70 dark:text-ink-dark/70">{{ $copy['aside'] }}</p>
        @endif
    </div>
</x-layouts.auth>
