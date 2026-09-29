<x-layouts.app title="Nhật ký hoạt động">
    @php
        $format = fn ($value) => match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'có' : 'không',
            is_array($value) => implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $value)) ?: '—',
            is_float($value) || is_int($value) => number_format($value, 0, ',', '.'),
            default => (string) $value,
        };
        $field = 'h-10 rounded-lg border border-stone-300 bg-white px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none';
    @endphp

    <h1 class="text-[32px] leading-tight font-bold tracking-tight">Cài đặt</h1>
    <p class="mt-1 text-stone-600">Ai đã sửa gì, lúc nào: hủy đơn, giảm giá, hoàn tiền, đổi giá, nhân viên, phân quyền, cài đặt.</p>

    @include('settings.partials.tabs')

    <form method="GET" action="{{ route('settings.activity') }}" class="mt-4 flex flex-wrap items-end gap-3" role="search">
        <input name="search" type="search" value="{{ $filters['search'] }}" placeholder="Tìm mã đơn, tên món, nhân viên…" aria-label="Tìm trong nhật ký" class="min-w-60 flex-1 {{ $field }}">
        <select name="group" aria-label="Loại" class="{{ $field }}">
            <option value="">Mọi loại</option>
            @foreach (\App\Models\ActivityLog::GROUPS as $key => $label)
                <option value="{{ $key }}" @selected($filters['group'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="user" aria-label="Người thực hiện" class="{{ $field }}">
            <option value="">Mọi người</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected($filters['user'] === $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <input name="date" type="date" value="{{ $filters['date'] }}" aria-label="Ngày" class="{{ $field }}">
        <button type="submit" class="h-10 rounded-lg bg-espresso px-4 text-sm font-semibold text-white hover:bg-espresso-line">Lọc</button>
        @if (array_filter($filters))
            <a href="{{ route('settings.activity') }}" class="flex h-10 items-center px-2 text-sm font-medium text-stone-600 hover:text-ink">Xóa bộ lọc</a>
        @endif
    </form>

    @if ($logs->isEmpty())
        <div class="mt-4 rounded-xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
            <p class="font-semibold">Chưa có hoạt động nào</p>
            <p class="mt-1 text-sm text-stone-600">{{ array_filter($filters) ? 'Thử đổi bộ lọc.' : 'Các thao tác quan trọng sẽ được ghi lại tại đây.' }}</p>
        </div>
    @else
        <ol class="mt-4 divide-y divide-stone-200 overflow-hidden rounded-xl border border-stone-200 bg-white">
            @foreach ($logs as $log)
                <li class="px-5 py-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <p class="min-w-0 flex-1">
                            <span class="mr-2 rounded bg-stone-100 px-1.5 py-0.5 text-xs font-semibold text-stone-700">{{ $log->actionLabel() }}</span>
                            <span class="font-medium">{{ $log->description }}</span>
                        </p>
                        <p class="text-sm whitespace-nowrap text-stone-600">
                            <b class="font-semibold text-ink">{{ $log->user?->name ?? 'Hệ thống' }}</b> ·
                            <time datetime="{{ $log->created_at->toIso8601String() }}" class="font-mono">{{ $log->created_at->format('d/m/Y H:i') }}</time>
                        </p>
                    </div>

                    @if ($changes = $log->changes())
                        <details class="mt-2 text-sm">
                            <summary class="cursor-pointer text-stone-600 hover:text-ink">Chi tiết thay đổi</summary>
                            <table class="mt-2 w-full max-w-2xl text-left">
                                <thead class="text-xs text-stone-500 uppercase"><tr><th class="py-1 pr-3">Trường</th><th class="py-1 pr-3">Trước</th><th class="py-1">Sau</th></tr></thead>
                                <tbody class="font-mono text-xs">
                                    @foreach ($changes as [$name, $old, $new])
                                        <tr class="border-t border-stone-100">
                                            <td class="py-1 pr-3 font-sans text-stone-600">{{ $name }}</td>
                                            <td class="py-1 pr-3 text-red-700">{{ $format($old) }}</td>
                                            <td class="py-1 text-emerald-700">{{ $format($new) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($log->ip_address)
                                <p class="mt-2 text-xs text-stone-500">IP: {{ $log->ip_address }}</p>
                            @endif
                        </details>
                    @endif
                </li>
            @endforeach
        </ol>

        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
</x-layouts.app>
