{{-- The photograph, big enough to recognise somebody by. --}}
<div class="flex justify-center">
    @if ($url)
        <img src="{{ $url }}" alt="" class="max-h-[70vh] rounded-lg" />
    @else
        <p class="text-sm text-gray-500">
            No photograph was sent, or the link to it has expired. Reload the
            page to sign a fresh one.
        </p>
    @endif
</div>
