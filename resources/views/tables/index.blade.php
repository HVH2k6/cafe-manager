<x-layouts.app title="Sơ đồ bàn">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">Sơ đồ bàn</h1>
            <p class="mt-1 text-stone-600">{{ $tableCount }} bàn · {{ $totalSeats }} chỗ ngồi</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('tables.update')
                <a href="{{ route('tables.qr.index') }}" class="flex h-11 items-center gap-2 rounded-xl border border-stone-300 bg-white px-4 text-sm font-semibold hover:bg-stone-50">
                    <x-icon name="qr-code" class="size-4.5" />
                    Mã QR gọi món
                </a>
            @endcan
            @can('tables.create')
                <a href="{{ route('tables.create') }}" class="flex h-11 items-center gap-2 rounded-xl bg-latte px-4 text-sm font-semibold text-white hover:bg-latte-dark">
                    <x-icon name="plus" class="size-4.5" />
                    Thêm bàn
                </a>
            @endcan
        </div>
    </div>

    @if ($tableCount === 0)
        <div class="mt-6 flex flex-col items-center rounded-xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-cream/70 text-latte-dark"><x-icon name="armchair" class="size-6" /></span>
            <p class="mt-4 font-semibold">Chưa có bàn nào</p>
            <p class="mt-1 text-sm text-stone-600">Thêm bàn và chia theo khu vực (Tầng 1, Sân vườn…) để phục vụ theo dõi.</p>
        </div>
    @else
        {{-- Legend with counts --}}
        <div class="mt-6 flex flex-wrap gap-2" aria-label="Số bàn theo trạng thái">
            @foreach (\App\TableStatus::cases() as $status)
                @php [, $dot] = $status->classes(); @endphp
                <span class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-white px-3 py-1.5 text-sm">
                    <span class="size-2 rounded-full {{ $dot }}"></span>
                    {{ $status->label() }}
                    <b class="font-mono">{{ $statusCounts[$status->value] ?? 0 }}</b>
                </span>
            @endforeach
        </div>

        @foreach ($tablesBySection as $section => $tables)
            <section class="mt-8">
                <h2 class="flex items-baseline gap-2 text-lg font-bold">
                    {{ $section }}
                    <span class="text-sm font-normal text-stone-500">{{ $tables->count() }} bàn</span>
                </h2>

                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($tables as $table)
                        @php [$card, $dot] = $table->status->classes(); @endphp
                        <article class="flex flex-col rounded-xl border-2 p-4 {{ $card }}">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-2xl leading-none font-bold">
                                    <span class="sr-only">Bàn</span>{{ $table->table_no }}
                                </p>
                                @can('tables.update')
                                    <div class="-mt-1 -mr-1 flex">
                                        <a href="{{ route('tables.qr.show', $table) }}" @class([
                                            'flex size-7 items-center justify-center rounded-md hover:bg-white/70 hover:text-ink',
                                            'text-stone-500' => $table->qrCode?->is_active,
                                            'text-amber-600' => ! $table->qrCode?->is_active,
                                        ]) title="{{ $table->qrCode ? ($table->qrCode->is_active ? 'Mã QR' : 'Mã QR đang tạm ngưng') : 'Chưa có mã QR' }}" aria-label="Mã QR bàn {{ $table->table_no }}">
                                            <x-icon name="qr-code" class="size-3.5" />
                                        </a>
                                        <a href="{{ route('tables.edit', $table) }}" class="flex size-7 items-center justify-center rounded-md text-stone-500 hover:bg-white/70 hover:text-ink" title="Sửa bàn" aria-label="Sửa bàn {{ $table->table_no }}">
                                            <x-icon name="pencil" class="size-3.5" />
                                        </a>
                                    </div>
                                @endcan
                            </div>
                            <p class="mt-1 flex items-center gap-1 text-sm text-stone-600">
                                <x-icon name="users" class="size-3.5" /> {{ $table->capacity }} chỗ
                                @if ($table->location)
                                    · {{ $table->location }}
                                @endif
                            </p>
                            <p class="mt-3 flex items-center gap-1.5 text-sm font-semibold">
                                <span class="size-2 rounded-full {{ $dot }}"></span>{{ $table->status->label() }}
                            </p>
                            @if ($table->notes)
                                <p class="mt-1 line-clamp-2 text-xs text-stone-600">{{ $table->notes }}</p>
                            @endif

                            @can('tables.status')
                                <div class="mt-auto pt-3">
                                    @if ($table->status === \App\TableStatus::Dirty)
                                        <form method="POST" action="{{ route('tables.status.update', $table) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" name="status" value="available" class="h-9 w-full rounded-lg bg-espresso text-sm font-semibold text-white hover:bg-espresso-line">Đã dọn xong</button>
                                        </form>
                                    @endif

                                    <details class="group relative mt-2">
                                        <summary class="flex h-8 cursor-pointer list-none items-center justify-center gap-1 rounded-lg text-xs font-semibold text-stone-600 hover:bg-white/70 hover:text-ink [&::-webkit-details-marker]:hidden">
                                            Đổi trạng thái <x-icon name="chevron-down" class="size-3.5 transition group-open:rotate-180" />
                                        </summary>
                                        <form method="POST" action="{{ route('tables.status.update', $table) }}" class="absolute inset-x-0 top-full z-10 mt-1 flex flex-col rounded-lg border border-stone-200 bg-white p-1 shadow-lg">
                                            @csrf
                                            @method('PUT')
                                            @foreach (\App\TableStatus::manuallySettable() as $status)
                                                @continue($status === $table->status)
                                                <button type="submit" name="status" value="{{ $status->value }}" class="flex items-center gap-2 rounded-md px-2.5 py-2 text-left text-sm hover:bg-stone-100">
                                                    <span class="size-2 rounded-full {{ $status->classes()[1] }}"></span>{{ $status->label() }}
                                                </button>
                                            @endforeach
                                        </form>
                                    </details>
                                </div>
                            @endcan
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</x-layouts.app>
