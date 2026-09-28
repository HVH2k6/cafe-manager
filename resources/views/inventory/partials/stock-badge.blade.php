{{-- Stock pill for a product. Expects $inventory (Inventory|null; null = stock not tracked). --}}
@if (! $inventory)
    <span class="text-xs text-stone-500">Không theo dõi</span>
@else
    @switch($inventory->stockLevel())
        @case('out')
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700"><span class="size-1.5 rounded-full bg-red-500"></span>Hết hàng</span>
            @break
        @case('low')
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700"><span class="size-1.5 rounded-full bg-amber-500"></span>Sắp hết · {{ $inventory->quantity_on_hand }} {{ $inventory->unitLabel() }}</span>
            @break
        @default
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"><span class="size-1.5 rounded-full bg-emerald-500"></span>{{ $inventory->quantity_on_hand }} {{ $inventory->unitLabel() }}</span>
    @endswitch
@endif
