{{-- Tabs shared by the permission matrix and the role list. --}}
<nav class="mt-6 flex gap-1 border-b border-stone-200" aria-label="Phân quyền">
    @foreach ([
        ['route' => 'permissions.index', 'active' => 'permissions.*', 'label' => 'Ma trận quyền'],
        ['route' => 'roles.index', 'active' => 'roles.*', 'label' => 'Vai trò'],
    ] as $tab)
        <a
            href="{{ route($tab['route']) }}"
            @if (request()->routeIs($tab['active'])) aria-current="page" @endif
            @class([
                '-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold',
                'border-latte text-ink' => request()->routeIs($tab['active']),
                'border-transparent text-stone-600 hover:text-ink' => ! request()->routeIs($tab['active']),
            ])
        >{{ $tab['label'] }}</a>
    @endforeach
</nav>
