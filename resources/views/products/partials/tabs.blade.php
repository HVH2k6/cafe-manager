{{-- Tabs shared by products, categories and combos. --}}
<nav class="mt-6 flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Thực đơn">
    @foreach ([
        ['route' => 'products.index', 'active' => 'products.*', 'label' => 'Sản phẩm'],
        ['route' => 'categories.index', 'active' => 'categories.*', 'label' => 'Danh mục'],
        ['route' => 'bundles.index', 'active' => 'bundles.*', 'label' => 'Combo'],
    ] as $tab)
        <a
            href="{{ route($tab['route']) }}"
            @if (request()->routeIs($tab['active'])) aria-current="page" @endif
            @class([
                '-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold whitespace-nowrap',
                'border-latte text-ink' => request()->routeIs($tab['active']),
                'border-transparent text-stone-600 hover:text-ink' => ! request()->routeIs($tab['active']),
            ])
        >{{ $tab['label'] }}</a>
    @endforeach
</nav>
