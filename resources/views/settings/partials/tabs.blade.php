<nav class="mt-6 flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Cài đặt">
    @foreach (array_filter([
        ['settings.general.edit', 'Thông tin quán', 'settings.view'],
        ['settings.qr-hours.edit', 'Giờ nhận đơn QR', 'settings.view'],
        ['settings.activity', 'Nhật ký hoạt động', 'settings.activity'],
    ], fn ($tab) => auth()->user()->can($tab[2])) as [$route, $label])
        <a
            href="{{ route($route) }}"
            @if (request()->routeIs($route)) aria-current="page" @endif
            @class([
                '-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold whitespace-nowrap',
                'border-latte text-ink' => request()->routeIs($route),
                'border-transparent text-stone-600 hover:text-ink' => ! request()->routeIs($route),
            ])
        >{{ $label }}</a>
    @endforeach
</nav>
