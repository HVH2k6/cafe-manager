{{-- "Hôm nay / 7 ngày / 30 ngày / Tùy chọn" picker. Keeps the page's other query parameters ($keep). --}}
@props(['period', 'route', 'keep' => []])

<details class="group relative" @if ($period->key === 'custom' && $errors->has('period')) open @endif>
    <summary class="flex h-11 cursor-pointer list-none items-center gap-2 rounded-xl border border-stone-200 bg-white px-4 text-sm font-medium hover:bg-stone-50 [&::-webkit-details-marker]:hidden">
        <x-icon name="calendar" class="size-4.5" />
        {{ $period->label() }}
        <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
    </summary>

    <div class="absolute right-0 z-20 mt-2 w-72 rounded-xl border border-stone-200 bg-white p-2 shadow-lg">
        <nav class="flex flex-col" aria-label="Khoảng thời gian">
            @foreach (['today', '7d', '30d'] as $key)
                <a
                    href="{{ route($route, ['period' => $key] + $keep) }}"
                    @if ($period->key === $key) aria-current="true" @endif
                    @class([
                        'flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-stone-100',
                        'font-semibold text-ink' => $period->key === $key,
                        'text-stone-700' => $period->key !== $key,
                    ])
                >
                    {{ \App\Reports\ReportPeriod::PRESETS[$key] }}
                    @if ($period->key === $key)
                        <x-icon name="check" class="size-4 text-latte-dark" />
                    @endif
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route($route) }}" class="mt-2 border-t border-stone-200 px-1 pt-3 pb-1">
            <input type="hidden" name="period" value="custom">
            @foreach ($keep as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <p class="px-2 text-xs font-semibold tracking-wide text-stone-500 uppercase">Tùy chọn</p>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <label class="text-xs text-stone-600">
                    Từ ngày
                    <input type="date" name="from" value="{{ $period->from->toDateString() }}" max="{{ today()->toDateString() }}" required class="mt-1 h-9 w-full rounded-lg border border-stone-300 px-2 text-sm text-ink focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                </label>
                <label class="text-xs text-stone-600">
                    Đến ngày
                    <input type="date" name="to" value="{{ $period->to->toDateString() }}" max="{{ today()->toDateString() }}" required class="mt-1 h-9 w-full rounded-lg border border-stone-300 px-2 text-sm text-ink focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                </label>
            </div>
            <button type="submit" class="mt-3 h-9 w-full rounded-lg bg-espresso text-sm font-semibold text-white hover:bg-espresso-line">Xem</button>
        </form>
    </div>
</details>
