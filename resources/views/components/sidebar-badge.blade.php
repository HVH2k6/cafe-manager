{{-- Live count next to a sidebar link, kept up to date by the `badges` Alpine store. --}}
@props(['key', 'count' => 0, 'label', 'tone' => 'amber'])

<span
    x-data
    x-show="$store.badges.count('{{ $key }}') > 0"
    x-text="$store.badges.count('{{ $key }}')"
    title="{{ $count }} {{ $label }}"
    :title="$store.badges.count('{{ $key }}') + ' {{ $label }}'"
    :class="$store.badges.bumped['{{ $key }}'] && 'animate-bounce'"
    @if ($count === 0) style="display: none" @endif
    @class([
        'flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-xs font-semibold',
        'bg-amber-600 text-white' => $tone === 'amber',
        'bg-blue-600 text-white' => $tone === 'blue',
        'bg-amber-100 text-amber-800' => $tone === 'soft',
    ])
>{{ $count }}</span>
