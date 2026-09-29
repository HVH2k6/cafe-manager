<x-layouts.app title="Cài đặt">
    @php
        $input = 'h-11 w-full rounded-lg border border-stone-300 bg-white px-3 focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none disabled:bg-stone-100';
        $canEdit = auth()->user()->can('settings.update');
    @endphp

    <h1 class="text-[32px] leading-tight font-bold tracking-tight">Cài đặt</h1>
    <p class="mt-1 text-stone-600">Thông tin quán in trên hóa đơn, giờ mở cửa và gọi món bằng QR.</p>

    @include('settings.partials.tabs')

    <form method="POST" action="{{ route('settings.general.update') }}" class="mt-6 flex max-w-2xl flex-col gap-6">
        @csrf
        @method('PUT')

        <fieldset @disabled(! $canEdit) class="flex flex-col gap-6">
            <section class="rounded-xl border border-stone-200 bg-white p-6">
                <h2 class="text-lg font-bold">Thông tin quán</h2>
                <p class="mt-1 text-sm text-stone-600">Hiện trên hóa đơn, trang gọi món của khách và đầu thanh menu.</p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="cafe_name" class="text-sm font-semibold">Tên quán</label>
                        <input id="cafe_name" name="cafe_name" value="{{ old('cafe_name', $settings['cafe_name']) }}" required maxlength="100" class="mt-1.5 {{ $input }}">
                        @error('cafe_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="cafe_address" class="text-sm font-semibold">Địa chỉ</label>
                        <input id="cafe_address" name="cafe_address" value="{{ old('cafe_address', $settings['cafe_address']) }}" maxlength="255" class="mt-1.5 {{ $input }}">
                        @error('cafe_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cafe_phone" class="text-sm font-semibold">Số điện thoại</label>
                        <input id="cafe_phone" name="cafe_phone" type="tel" value="{{ old('cafe_phone', $settings['cafe_phone']) }}" maxlength="20" class="mt-1.5 {{ $input }}">
                        @error('cafe_phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-stone-200 bg-white p-6">
                <h2 class="text-lg font-bold">Hóa đơn</h2>
                <div class="mt-5 grid gap-4">
                    <div>
                        <label for="receipt_footer" class="text-sm font-semibold">Lời cảm ơn cuối hóa đơn</label>
                        <input id="receipt_footer" name="receipt_footer" value="{{ old('receipt_footer', $settings['receipt_footer']) }}" maxlength="255" class="mt-1.5 {{ $input }}">
                    </div>
                    <div>
                        <label for="receipt_note" class="text-sm font-semibold">Ghi chú thêm <span class="font-normal text-stone-500">(Wi-Fi, mã số thuế… — không bắt buộc)</span></label>
                        <textarea id="receipt_note" name="receipt_note" rows="2" maxlength="500" class="mt-1.5 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none disabled:bg-stone-100">{{ old('receipt_note', $settings['receipt_note']) }}</textarea>
                        @error('receipt_note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-stone-200 bg-white p-6">
                <h2 class="text-lg font-bold">Giờ mở cửa</h2>
                <p class="mt-1 text-sm text-stone-600">Hiện trên Tổng quan. Giờ khách được gửi đơn QR cài riêng ở tab “Giờ nhận đơn QR”.</p>
                <div class="mt-5 grid grid-cols-2 gap-4 sm:max-w-sm">
                    <div>
                        <label for="opening_time" class="text-sm font-semibold">Mở</label>
                        <input id="opening_time" name="opening_time" type="time" value="{{ old('opening_time', $settings['opening_time']) }}" required class="mt-1.5 font-mono {{ $input }}">
                        @error('opening_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="closing_time" class="text-sm font-semibold">Đóng</label>
                        <input id="closing_time" name="closing_time" type="time" value="{{ old('closing_time', $settings['closing_time']) }}" required class="mt-1.5 font-mono {{ $input }}">
                        @error('closing_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-stone-200 bg-white p-6">
                <h2 class="text-lg font-bold">Lịch sử gọi món của khách (QR)</h2>
                <p class="mt-1 text-sm text-stone-600">
                    Sau khi đơn xong hoặc bị hủy, điện thoại đã quét mã vẫn xem lại được món đã gọi và hóa đơn trong khoảng thời gian này
                    (quét lại mã trên bàn là thấy). Hết thời gian, lịch sử tự ẩn để khách ngồi sau không thấy đơn của người trước.
                </p>
                <div class="mt-5 flex items-center gap-3">
                    <label for="qr_history_minutes" class="sr-only">Số phút giữ lịch sử</label>
                    <input id="qr_history_minutes" name="qr_history_minutes" type="number" min="{{ \App\Models\Setting::QR_HISTORY_MIN }}" max="{{ \App\Models\Setting::QR_HISTORY_MAX }}" value="{{ old('qr_history_minutes', $settings['qr_history_minutes']) }}" required class="w-28 font-mono {{ $input }}">
                    <span class="text-stone-600">phút <span class="text-sm">(từ {{ \App\Models\Setting::QR_HISTORY_MIN }} đến {{ \App\Models\Setting::QR_HISTORY_MAX }})</span></span>
                </div>
                @error('qr_history_minutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </section>
        </fieldset>

        @if ($canEdit)
            <div><button type="submit" class="h-11 rounded-xl bg-latte px-5 font-semibold text-white hover:bg-latte-dark">Lưu cài đặt</button></div>
        @endif
    </form>
</x-layouts.app>
