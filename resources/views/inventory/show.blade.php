<x-layouts.app :title="'Tồn kho · '.$product->name">
    @php
        $inventory = $product->inventory;
        $fieldClasses = fn (string $field): string => \Illuminate\Support\Arr::toCssClasses([
            'h-10 w-full rounded-lg border bg-white px-3 text-sm focus:ring-3 focus:outline-none',
            'border-stone-300 focus:border-latte focus:ring-latte/15' => ! $errors->has($field),
            'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errors->has($field),
        ]);
    @endphp

    <a href="{{ route('inventory.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
        <x-icon name="arrow-left" class="size-4" /> Tồn kho
    </a>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">{{ $product->name }}</h1>
            <p class="text-stone-600">{{ $product->category->name }}{{ $product->sku ? " · SKU {$product->sku}" : '' }}</p>
        </div>
        @include('inventory.partials.stock-badge', ['inventory' => $inventory])
    </div>

    @if (! $inventory)
        <div class="mt-6 rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
            <p class="font-semibold">Sản phẩm này chưa được theo dõi tồn kho</p>
            <p class="mt-1 text-sm text-stone-600">Bật theo dõi cho hàng đếm được như bánh, nước đóng chai. Sau đó nhập hàng hoặc kiểm kê để có số tồn đầu tiên.</p>
            @can('inventory.update')
                <form method="POST" action="{{ route('inventory.store', $product) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="h-10 rounded-lg bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">Bắt đầu theo dõi kho</button>
                </form>
            @endcan
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-4">
            @foreach ([
                ['label' => 'Tồn kho', 'value' => $inventory->quantity_on_hand],
                ['label' => 'Giữ cho đơn đang chờ', 'value' => $inventory->quantity_reserved],
                ['label' => 'Có thể bán', 'value' => $inventory->availableQuantity()],
            ] as $stat)
                <div class="rounded-xl border border-stone-200 bg-white p-5">
                    <p class="text-sm text-stone-600">{{ $stat['label'] }}</p>
                    <p class="mt-2 flex items-baseline gap-1.5"><span class="font-mono text-[28px] leading-none font-bold">{{ $stat['value'] }}</span><span class="text-sm text-stone-500">{{ $inventory->unitLabel() }}</span></p>
                </div>
            @endforeach
            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <p class="text-sm text-stone-600">Nhập gần nhất</p>
                <p class="mt-2 font-semibold">{{ $inventory->last_restock?->format('d/m/Y H:i') ?? 'Chưa nhập' }}</p>
            </div>
        </div>

        <div class="mt-4 grid items-start gap-4 lg:grid-cols-[1fr_340px]">
            {{-- History --}}
            <section class="overflow-hidden rounded-xl border border-stone-200 bg-white">
                <h2 class="flex items-center gap-2 px-6 py-5 text-lg font-bold"><x-icon name="history" class="size-5" /> Lịch sử kho</h2>
                @if ($transactions->isEmpty())
                    <p class="border-t border-stone-100 px-6 py-10 text-center text-sm text-stone-600">Chưa có giao dịch nào.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                                <tr>
                                    <th scope="col" class="px-6 py-3">Thời gian</th>
                                    <th scope="col" class="px-3 py-3">Loại</th>
                                    <th scope="col" class="px-3 py-3 text-right">Thay đổi</th>
                                    <th scope="col" class="px-3 py-3">Ghi chú</th>
                                    <th scope="col" class="px-6 py-3">Người ghi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-200">
                                @foreach ($transactions as $transaction)
                                    <tr>
                                        <td class="px-6 py-3 font-mono whitespace-nowrap text-stone-600">{{ $transaction->created_at->format('d/m H:i') }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap">{{ $transaction->transaction_type->label() }}</td>
                                        <td @class(['px-3 py-3 text-right font-mono font-semibold', 'text-emerald-700' => $transaction->quantity > 0, 'text-red-600' => $transaction->quantity < 0])>
                                            {{ $transaction->quantity > 0 ? '+' : '' }}{{ $transaction->quantity }}
                                        </td>
                                        <td class="px-3 py-3 text-stone-600">{{ $transaction->notes ?? '—' }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap text-stone-600">{{ $transaction->creator?->name ?? 'Hệ thống' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-stone-100 px-6 py-3">{{ $transactions->links() }}</div>
                @endif
            </section>

            @can('inventory.update')
                <aside class="flex flex-col gap-4">
                    {{-- Record a movement --}}
                    <form method="POST" action="{{ route('inventory.transactions.store', $product) }}" class="rounded-xl border border-stone-200 bg-white p-5">
                        @csrf
                        <h2 class="font-bold">Ghi nhận thay đổi</h2>

                        <fieldset class="mt-4 grid grid-cols-2 gap-2">
                            <legend class="sr-only">Loại giao dịch</legend>
                            @foreach (\App\InventoryTransactionType::manual() as $type)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-stone-200 px-3 py-2.5 text-sm has-checked:border-latte has-checked:bg-cream/40 has-checked:font-semibold">
                                    <input type="radio" name="transaction_type" value="{{ $type->value }}" @checked(old('transaction_type', 'purchase') === $type->value) class="accent-latte">
                                    {{ $type->label() }}
                                </label>
                            @endforeach
                        </fieldset>
                        @error('transaction_type') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

                        <div class="mt-4 flex flex-col gap-1.5">
                            <label for="quantity" class="text-sm font-semibold">Số lượng ({{ $inventory->unitLabel() }})</label>
                            <input id="quantity" name="quantity" type="number" min="0" value="{{ old('quantity') }}" required class="font-mono {{ $fieldClasses('quantity') }}">
                            <p class="text-xs text-stone-500">Nhập hàng: số thêm vào. Kiểm kê: số <b>thực tế đếm được</b>. Hư hỏng / hết hạn: số bỏ đi.</p>
                            @error('quantity') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="mt-4 flex flex-col gap-1.5">
                            <label for="notes" class="text-sm font-semibold">Ghi chú <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
                            <input id="notes" name="notes" type="text" maxlength="500" value="{{ old('notes') }}" placeholder="Nhà cung cấp, lý do…" class="{{ $fieldClasses('notes') }}">
                        </div>

                        <button type="submit" class="mt-4 h-10 w-full rounded-lg bg-latte text-sm font-semibold text-white hover:bg-latte-dark">Ghi nhận</button>
                    </form>

                    {{-- Thresholds --}}
                    <form method="POST" action="{{ route('inventory.update', $product) }}" class="rounded-xl border border-stone-200 bg-white p-5">
                        @csrf
                        @method('PUT')
                        <h2 class="font-bold">Ngưỡng cảnh báo</h2>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="col-span-2 flex flex-col gap-1.5">
                                <label for="unit_of_measure" class="text-sm font-semibold">Đơn vị tính</label>
                                <input id="unit_of_measure" name="unit_of_measure" type="text" list="unit-options" maxlength="20" value="{{ old('unit_of_measure', $inventory->unitLabel()) }}" required class="{{ $fieldClasses('unit_of_measure') }}">
                                <datalist id="unit-options">
                                    @foreach (array_unique(\App\Models\Inventory::UNIT_LABELS) as $unit)
                                        <option value="{{ $unit }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            @foreach ([
                                'min_stock' => ['Tối thiểu', 'Dưới mức này là “sắp hết”.'],
                                'reorder_level' => ['Cần đặt hàng', 'Nên đặt thêm khi còn ở mức này.'],
                                'max_stock' => ['Tối đa', 'Sức chứa của kho.'],
                            ] as $field => [$label, $hint])
                                <div @class(['flex flex-col gap-1.5', 'col-span-2' => $field === 'max_stock'])>
                                    <label for="{{ $field }}" class="text-sm font-semibold">{{ $label }}</label>
                                    <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" value="{{ old($field, $inventory->{$field}) }}" required class="font-mono {{ $fieldClasses($field) }}">
                                    <p class="text-xs text-stone-500">{{ $hint }}</p>
                                    @error($field) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>

                        <button type="submit" class="mt-4 h-10 w-full rounded-lg border border-stone-300 text-sm font-semibold hover:bg-stone-50">Lưu ngưỡng</button>
                    </form>
                </aside>
            @endcan
        </div>
    @endif
</x-layouts.app>
