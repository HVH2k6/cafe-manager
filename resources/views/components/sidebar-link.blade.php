@props(['href' => '#', 'icon', 'active' => false])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'flex h-11 items-center gap-3 rounded-lg px-3 text-[15px] transition-colors',
        'bg-cream/80 font-semibold text-ink' => $active,
        'text-stone-700 hover:bg-stone-100 hover:text-ink' => ! $active,
    ]) }}
>
    <x-icon :name="$icon" class="size-5" />
    <span class="flex-1">{{ $slot }}</span>
    {{ $badge ?? '' }}
</a>
