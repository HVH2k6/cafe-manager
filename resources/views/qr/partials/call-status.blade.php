{{-- The customer's latest "Gọi nhân viên" call ($lastCall: ?QrNotification): waiting, or who is coming. --}}
@if ($lastCall)
    @if ($lastCall->is_read)
        <p class="flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">
            <x-icon name="check-circle" class="size-5 text-emerald-600" /> {{ $lastCall->title }}
        </p>
    @else
        <p class="flex items-center gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800" role="status">
            <span class="size-2 animate-pulse rounded-full bg-amber-500"></span>
            Đã gọi nhân viên lúc {{ $lastCall->created_at->format('H:i') }}, vui lòng chờ trong giây lát.
        </p>
    @endif
@endif
