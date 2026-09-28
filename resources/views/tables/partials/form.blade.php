{{-- Shared fields of the create / edit table forms. Expects $table and $sections. --}}
@php
    $fieldClasses = fn (string $field): string => \Illuminate\Support\Arr::toCssClasses([
        'w-full rounded-lg border bg-white px-4 text-ink placeholder:text-stone-400 focus:ring-3 focus:outline-none',
        'border-stone-300 focus:border-latte focus:ring-latte/15' => ! $errors->has($field),
        'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errors->has($field),
    ]);
@endphp

<div class="flex flex-col gap-5">
    <div class="grid gap-5 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
            <label for="table_no" class="text-sm font-semibold">Số bàn</label>
            <input id="table_no" name="table_no" type="number" min="1" max="9999" value="{{ old('table_no', $table->table_no) }}" required class="h-11 font-mono {{ $fieldClasses('table_no') }}">
            @error('table_no') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex flex-col gap-2">
            <label for="capacity" class="text-sm font-semibold">Số chỗ ngồi</label>
            <input id="capacity" name="capacity" type="number" min="1" max="50" value="{{ old('capacity', $table->capacity) }}" required class="h-11 font-mono {{ $fieldClasses('capacity') }}">
            @error('capacity') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex flex-col gap-2">
            <label for="section" class="text-sm font-semibold">Khu vực <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
            <input id="section" name="section" type="text" list="section-options" value="{{ old('section', $table->section) }}" placeholder="Tầng 1" maxlength="50" class="h-11 {{ $fieldClasses('section') }}">
            <datalist id="section-options">
                @foreach ($sections as $section)
                    <option value="{{ $section }}"></option>
                @endforeach
            </datalist>
            @error('section') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex flex-col gap-2">
            <label for="location" class="text-sm font-semibold">Vị trí <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
            <input id="location" name="location" type="text" value="{{ old('location', $table->location) }}" placeholder="Cạnh cửa sổ" maxlength="100" class="h-11 {{ $fieldClasses('location') }}">
        </div>
    </div>

    <div class="flex flex-col gap-2">
        <label for="notes" class="text-sm font-semibold">Ghi chú <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
        <textarea id="notes" name="notes" rows="2" maxlength="1000" class="py-3 {{ $fieldClasses('notes') }}">{{ old('notes', $table->notes) }}</textarea>
    </div>
</div>
