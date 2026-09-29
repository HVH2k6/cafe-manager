<x-layouts.app title="Giờ nhận đơn QR">
    <h1 class="text-[32px] leading-tight font-bold tracking-tight">Cài đặt</h1>
    @include('settings.partials.tabs')

    <div class="mt-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold">Giờ nhận đơn QR</h2>
            <p class="mt-1 text-stone-600">Ngoài giờ này khách vẫn xem menu và gọi nhân viên được, nhưng không gửi đơn được.</p>
        </div>
        <span @class([
            'flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold',
            'bg-emerald-50 text-emerald-700' => $isOpenNow,
            'bg-stone-100 text-stone-600' => ! $isOpenNow,
        ])>
            <span @class(['size-2 rounded-full', 'bg-emerald-500' => $isOpenNow, 'bg-stone-400' => ! $isOpenNow])></span>
            {{ $isOpenNow ? 'Đang nhận đơn' : 'Đang ngoài giờ' }}
        </span>
    </div>

    @unless ($isConfigured)
        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Chưa cài đặt giờ: hiện đang nhận đơn QR <b>cả ngày</b>. Lưu bảng dưới để áp dụng.</p>
    @endunless

    <form method="POST" action="{{ route('settings.qr-hours.update') }}" class="mt-6 max-w-2xl">
        @csrf
        @method('PUT')

        <section class="overflow-hidden rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                    <tr>
                        <th scope="col" class="px-5 py-3">Ngày</th>
                        <th scope="col" class="px-3 py-3">Nhận đơn</th>
                        <th scope="col" class="px-3 py-3">Mở</th>
                        <th scope="col" class="px-5 py-3">Đóng</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    @foreach ($days as $day)
                        @php $key = $day['day']; @endphp
                        <tr>
                            <th scope="row" class="px-5 py-3 font-semibold">{{ $day['label'] }}</th>
                            <td class="px-3 py-3">
                                <input type="hidden" name="days[{{ $key }}][is_open]" value="0">
                                <input type="checkbox" name="days[{{ $key }}][is_open]" value="1" @checked(old("days.{$key}.is_open", $day['is_open'])) class="size-5 accent-latte" aria-label="Nhận đơn {{ $day['label'] }}">
                            </td>
                            <td class="px-3 py-3">
                                <input type="time" name="days[{{ $key }}][opening_time]" value="{{ old("days.{$key}.opening_time", $day['opening_time']) }}" required class="h-10 rounded-lg border border-stone-300 px-2 font-mono focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none" aria-label="Giờ mở {{ $day['label'] }}">
                            </td>
                            <td class="px-5 py-3">
                                <input type="time" name="days[{{ $key }}][closing_time]" value="{{ old("days.{$key}.closing_time", $day['closing_time']) }}" required class="h-10 rounded-lg border border-stone-300 px-2 font-mono focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none" aria-label="Giờ đóng {{ $day['label'] }}">
                                @error("days.{$key}.opening_time") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @error("days.{$key}.closing_time") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <p class="mt-3 text-sm text-stone-600">
            Giờ đóng nhỏ hơn giờ mở = mở qua đêm (VD 18:00 → 02:00 kéo sang sáng hôm sau). Giờ đóng bằng giờ mở = cả ngày.
        </p>

        @can('settings.update')
            <button type="submit" class="mt-5 h-11 rounded-xl bg-latte px-5 font-semibold text-white hover:bg-latte-dark">Lưu giờ nhận đơn</button>
        @endcan
    </form>
</x-layouts.app>
