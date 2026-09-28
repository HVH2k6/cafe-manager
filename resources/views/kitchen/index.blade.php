<x-layouts.app title="Màn hình bếp">
    <div x-data="liveRegion({ channel: 'kitchen', events: ['order.created', 'order.status.updated', 'order.ready', 'order.completed'], poll: 5, pollWhenConnected: 60, alerts: true })">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-[32px] leading-tight font-bold tracking-tight">Màn hình bếp</h1>
                <p class="mt-1 text-stone-600" data-live-refresh="counts">
                    <b>{{ $counts['pending'] }}</b> món chờ làm · <b>{{ $counts['preparing'] }}</b> món đang làm
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <span class="flex items-center gap-2 rounded-full border border-stone-200 bg-white px-3 py-1.5 text-sm" role="status" aria-live="polite">
                    <span class="size-2 rounded-full" :class="$store.realtime.connected ? 'bg-emerald-500' : ($store.realtime.state === 'off' ? 'bg-stone-400' : 'bg-amber-500 animate-pulse')"></span>
                    <span x-text="$store.realtime.connected ? $store.realtime.label : $store.realtime.label + ' · tự tải lại mỗi 5 giây'">Tự tải lại mỗi 5 giây</span>
                </span>
                <button type="button" x-on:click="$store.sound.toggle()" :aria-pressed="$store.sound.enabled.toString()" class="h-9 rounded-lg border border-stone-300 bg-white px-3 text-sm font-semibold hover:bg-stone-50 aria-pressed:border-espresso aria-pressed:bg-espresso aria-pressed:text-white">
                    <span x-text="$store.sound.enabled ? 'Âm báo: bật' : 'Bật âm báo'">Bật âm báo</span>
                </button>
            </div>
        </div>

        <div data-live-refresh="tickets">
            @if ($orders->isEmpty())
                <div class="mt-6 flex flex-col items-center rounded-xl border border-dashed border-stone-300 bg-white px-6 py-20 text-center">
                    <span class="flex size-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><x-icon name="check-circle" class="size-6" /></span>
                    <p class="mt-4 font-semibold">Không còn món nào cần làm</p>
                    <p class="mt-1 text-sm text-stone-600">Đơn mới sẽ tự hiện ở đây.</p>
                </div>
            @else
                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($orders as $order)
                        @php $waitingMinutes = (int) $order->created_at->diffInMinutes(now()); @endphp
                        <article data-order-id="{{ $order->id }}" @class([
                            'flex flex-col overflow-hidden rounded-xl border-2 bg-white',
                            'border-red-300' => $waitingMinutes >= 15,
                            'border-stone-200' => $waitingMinutes < 15,
                        ])>
                            <header @class([
                                'flex items-center justify-between gap-3 px-4 py-3',
                                'bg-red-50' => $waitingMinutes >= 15,
                                'bg-stone-50' => $waitingMinutes < 15,
                            ])>
                                <div>
                                    <p class="text-lg font-bold">{{ $order->placeLabel() }}</p>
                                    <p class="font-mono text-xs text-stone-500">{{ $order->order_number }} · {{ $order->created_at->format('H:i') }}</p>
                                </div>
                                <span @class(['font-mono text-sm font-bold', 'text-red-600' => $waitingMinutes >= 15, 'text-stone-600' => $waitingMinutes < 15])>{{ $waitingMinutes }} phút</span>
                            </header>

                            @if ($order->notes)
                                <p class="border-b border-stone-100 bg-amber-50 px-4 py-2 text-sm text-amber-800">{{ $order->notes }}</p>
                            @endif

                            <ul class="flex-1 divide-y divide-stone-100">
                                @foreach ($order->items as $item)
                                    <li @class(['flex items-start gap-3 px-4 py-3', 'opacity-50' => $item->status === \App\OrderItemStatus::Ready])>
                                        <span class="font-mono text-xl leading-none font-bold">{{ $item->quantity }}×</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold leading-snug">{{ $item->name() }}</p>
                                            @if ($item->bundle)
                                                <p class="text-sm text-stone-600">{{ $item->bundle->items->map(fn ($component) => ($component->quantity > 1 ? $component->quantity.'× ' : '').$component->product->name)->implode(' + ') }}</p>
                                            @endif
                                            @if ($item->variantSummary())
                                                <p class="text-sm text-stone-600">{{ $item->variantSummary() }}</p>
                                            @endif
                                            @if ($item->special_instructions)
                                                <p class="text-sm font-semibold text-amber-700">“{{ $item->special_instructions }}”</p>
                                            @endif
                                        </div>

                                        @if ($item->status === \App\OrderItemStatus::Ready)
                                            <span class="flex items-center gap-1 text-sm font-semibold text-emerald-700"><x-icon name="check-circle" class="size-4" /> Xong</span>
                                        @else
                                            @can('kitchen.update')
                                                <form method="POST" action="{{ route('kitchen.items.update', $item) }}" class="flex shrink-0 gap-1.5">
                                                    @csrf
                                                    @method('PUT')
                                                    @if ($item->status === \App\OrderItemStatus::Pending)
                                                        <button type="submit" name="status" value="preparing" class="h-9 rounded-lg border border-stone-300 px-3 text-sm font-semibold hover:bg-stone-50">Làm</button>
                                                    @else
                                                        <span class="flex h-9 items-center px-1 text-sm font-semibold text-amber-600">Đang làm</span>
                                                    @endif
                                                    <button type="submit" name="status" value="ready" class="h-9 rounded-lg bg-emerald-600 px-3 text-sm font-semibold text-white hover:bg-emerald-700">Xong</button>
                                                </form>
                                            @endcan
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
