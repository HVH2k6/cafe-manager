<x-layouts.app title="Sửa bàn">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('tables.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
            <x-icon name="arrow-left" class="size-4" /> Sơ đồ bàn
        </a>
        <h1 class="mt-3 text-[32px] leading-tight font-bold tracking-tight">Bàn {{ $table->table_no }}</h1>
        <p class="text-stone-600">{{ $table->status->label() }}</p>

        <form method="POST" action="{{ route('tables.update', $table) }}" class="mt-6 rounded-xl border border-stone-200 bg-white p-6">
            @csrf
            @method('PUT')

            @include('tables.partials.form')

            <div class="mt-6 flex justify-end gap-3 border-t border-stone-200 pt-5">
                <a href="{{ route('tables.index') }}" class="flex h-10 items-center rounded-lg border border-stone-300 px-4 text-sm font-semibold hover:bg-stone-50">Hủy</a>
                <button type="submit" class="h-10 rounded-lg bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">Lưu thay đổi</button>
            </div>
        </form>

        @can('tables.delete')
            <form method="POST" action="{{ route('tables.destroy', $table) }}" data-confirm="Xóa bàn {{ $table->table_no }}? Mã QR của bàn (nếu có) cũng bị xóa." class="mt-4 flex items-center justify-between gap-4 rounded-xl border border-red-200 bg-white p-5">
                @csrf
                @method('DELETE')
                <div>
                    <h2 class="font-bold">Xóa bàn</h2>
                    <p class="text-sm text-stone-600">Muốn tạm ngưng dùng bàn thì chuyển sang “Bảo trì” thay vì xóa.</p>
                </div>
                <button type="submit" class="h-10 shrink-0 rounded-lg border border-red-200 px-4 text-sm font-semibold text-red-700 hover:bg-red-50">Xóa bàn</button>
            </form>
        @endcan
    </div>
</x-layouts.app>
