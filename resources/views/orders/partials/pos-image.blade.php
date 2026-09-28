{{-- Square thumbnail of a POS card. Expects $image (?string) and $icon. --}}
@if ($image)
    <img src="{{ $image }}" alt="" loading="lazy" class="aspect-[4/3] w-full object-cover">
@else
    <span class="flex aspect-[4/3] w-full items-center justify-center bg-cream/50 text-latte/70">
        <x-icon :name="$icon" class="size-9" />
    </span>
@endif
