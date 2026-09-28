@props(['title'])

{{-- Pages customers open by scanning a table's QR code: phone-sized, no login, no staff navigation. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#f9f8f6">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex">

        <title>{{ $title }} · {{ config('app.cafe_name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-stone-200/60 font-sans text-ink antialiased">
        <div {{ $attributes->class('relative mx-auto flex min-h-dvh w-full max-w-md flex-col bg-paper shadow-sm') }}>
            @if (session('status') || session('error'))
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 6000)"
                    x-show="show"
                    x-transition.opacity
                    class="fixed inset-x-0 top-3 z-[60] mx-auto w-[calc(100%-2rem)] max-w-sm"
                >
                    <div @class([
                        'flex items-start gap-3 rounded-2xl px-4 py-3 text-sm font-medium shadow-lg',
                        'bg-emerald-600 text-white' => session('status'),
                        'bg-red-600 text-white' => ! session('status'),
                    ]) role="{{ session('status') ? 'status' : 'alert' }}">
                        <x-icon :name="session('status') ? 'check-circle' : 'alert-circle'" class="mt-px size-5" />
                        <p class="flex-1">{{ session('status') ?? session('error') }}</p>
                        <button type="button" x-on:click="show = false" class="opacity-80 hover:opacity-100" aria-label="Đóng"><x-icon name="x" class="size-4" /></button>
                    </div>
                </div>
            @endif

            {{ $slot }}
        </div>
    </body>
</html>
