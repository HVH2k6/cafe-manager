{{-- VND amount, e.g. <x-money :amount="72000" /> => "72.000 đ". --}}
@props(['amount', 'signed' => false])

@php $amount = (float) $amount; @endphp

<span {{ $attributes->class('font-mono whitespace-nowrap') }}>{{ $signed && $amount > 0 ? '+' : '' }}{{ number_format($amount, 0, ',', '.') }} đ</span>
