{{-- How the money went back to the customer, shared by the refund forms. --}}
<fieldset class="grid grid-cols-2 gap-2">
    <legend class="sr-only">Cách hoàn tiền</legend>
    @foreach (\App\Actions\Payments\RefundPayment::methods() as $method)
        <label class="flex h-9 cursor-pointer items-center justify-center rounded-lg border border-stone-300 bg-white text-sm has-checked:border-espresso has-checked:bg-espresso has-checked:font-semibold has-checked:text-white">
            <input type="radio" name="refund_method" value="{{ $method->value }}" @checked(old('refund_method', 'cash') === $method->value) class="sr-only">
            {{ $method->label() }}
        </label>
    @endforeach
</fieldset>
<input name="refund_reference" value="{{ old('refund_reference') }}" maxlength="100" placeholder="Mã giao dịch chuyển khoản (nếu có)" aria-label="Mã giao dịch hoàn tiền" class="h-10 w-full rounded-lg border border-stone-300 bg-white px-3 font-mono text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
