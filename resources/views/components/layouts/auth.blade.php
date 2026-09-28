@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title }} · {{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-paper font-sans text-ink antialiased">
        <div class="flex min-h-screen">
            {{-- Brand panel --}}
            <aside class="relative hidden w-5/12 max-w-[600px] shrink-0 flex-col justify-between overflow-hidden bg-espresso p-14 lg:flex">
                <div class="relative z-10 flex flex-col gap-6">
                    <x-brand-logo class="size-16" />
                    <div>
                        <p class="text-3xl font-bold text-white">{{ config('app.name') }}</p>
                        <p class="mt-1 font-medium text-caramel">{{ config('app.cafe_name') }}</p>
                    </div>
                </div>

                <p class="relative z-10 max-w-md text-[32px] leading-snug font-bold text-cream">
                    Đơn hàng, quầy bếp, tồn kho và doanh thu — gọn trong một màn hình.
                </p>

                <svg class="pointer-events-none absolute -right-2 -bottom-10 w-[300px] text-espresso-line" viewBox="0 0 300 340" fill="none" stroke="currentColor" stroke-width="8" stroke-linecap="round" aria-hidden="true">
                    <path d="M95 72c-10-12 10-20 0-34s10-22 0-34" />
                    <path d="M140 64c-10-12 10-20 0-34s10-22 0-30" />
                    <path d="M185 72c-10-12 10-20 0-34s10-22 0-34" />
                    <ellipse cx="140" cy="130" rx="112" ry="20" />
                    <path d="M28 130v24c0 104 44 186 112 186s112-82 112-186v-24" />
                    <path d="M252 168h18a36 36 0 0 1 0 72h-26" />
                </svg>
            </aside>

            {{-- Content --}}
            <main class="flex flex-1 items-center justify-center px-6 py-12">
                <div class="w-full max-w-[400px]">
                    <div class="mb-10 flex items-center gap-3 lg:hidden">
                        <x-brand-logo class="size-12" />
                        <div>
                            <p class="text-xl font-bold">{{ config('app.name') }}</p>
                            <p class="text-sm font-medium text-latte">{{ config('app.cafe_name') }}</p>
                        </div>
                    </div>

                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
