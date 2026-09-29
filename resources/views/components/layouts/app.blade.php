@props(['title'])

@php
    // Sidebar counts the user may see; kept live by the `badges` store (realtime.js).
    $badges = \App\Support\SidebarBadges::for(auth()->user());
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Echo sends it when asking /broadcasting/auth for private channels. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} · {{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="min-h-screen bg-paper font-sans text-ink antialiased"
        data-badges="{{ json_encode((object) $badges) }}"
        data-badge-settings="{{ json_encode(['url' => route('sidebar-badges'), 'channels' => \App\Support\SidebarBadges::channelsFor(auth()->user())]) }}"
    >
        <div data-sidebar-backdrop class="fixed inset-0 z-30 hidden bg-ink/40 lg:hidden"></div>

        @can('orders.view')
            {{-- Realtime pop-ups: customer calls (until answered), QR orders to confirm, orders ready to take out. --}}
            <div x-data="staffAlerts(@js(\App\Http\Controllers\StaffAlertController::waitingCalls()), '{{ route('staff-alerts') }}')" class="fixed right-4 bottom-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2" aria-live="assertive">
                <template x-for="call in calls" :key="'call-' + call.id">
                    <div class="flex items-start gap-3 rounded-xl border-2 border-amber-300 bg-amber-50 p-4 shadow-lg" role="alert">
                        <x-icon name="concierge-bell" class="size-6 text-amber-600" />
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-bold" x-text="call.place + ' gọi nhân viên'"></p>
                            <button type="button" x-on:click="answer(call)" class="mt-2 h-8 rounded-lg bg-amber-600 px-3 font-semibold text-white hover:bg-amber-700">Tôi ra ngay</button>
                        </div>
                    </div>
                </template>
                <template x-for="toast in toasts" :key="toast.id">
                    <div class="flex items-start gap-3 rounded-xl border bg-white p-4 shadow-lg" :class="toast.kind === 'pending' ? 'border-blue-200' : 'border-emerald-200'" role="alert">
                        <span :class="toast.kind === 'pending' ? 'text-blue-600' : 'text-emerald-600'">
                            <x-icon name="check-circle" class="size-6" />
                        </span>
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-semibold" x-text="toast.message"></p>
                            <a :href="toast.url" class="mt-1 inline-block font-semibold text-latte-dark hover:underline" x-text="toast.kind === 'pending' ? 'Xem & xác nhận →' : 'Mở đơn →'"></a>
                        </div>
                        <button type="button" x-on:click="dismiss(toast.id)" class="text-stone-400 hover:text-ink" aria-label="Đóng">×</button>
                    </div>
                </template>
            </div>
        @endcan

        <aside
            id="sidebar"
            data-sidebar
            class="fixed inset-y-0 left-0 z-40 flex w-62 -translate-x-full flex-col border-r border-stone-200 bg-[#fcfbf9] px-4 py-5 transition-transform lg:translate-x-0"
        >
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-1">
                <x-brand-logo class="size-10 rounded-xl" />
                <div class="leading-tight">
                    <p class="font-bold">{{ config('app.name') }}</p>
                    <p class="text-xs text-stone-600">{{ config('app.cafe_name') }}</p>
                </div>
            </a>

            <nav class="mt-7 flex flex-1 flex-col gap-1 overflow-y-auto" aria-label="Điều hướng chính">
                <x-sidebar-link :href="route('dashboard')" icon="layout-grid" :active="request()->routeIs('dashboard')">Tổng quan</x-sidebar-link>
                @can('orders.view')
                    <x-sidebar-link :href="route('orders.index')" icon="receipt" :active="request()->routeIs('orders.*')">
                        Đơn hàng
                        <x-slot:badge>
                            <x-sidebar-badge key="pending" :count="$badges['pending'] ?? 0" label="đơn chờ xác nhận" />
                        </x-slot:badge>
                    </x-sidebar-link>
                @endcan
                @can('kitchen.view')
                    <x-sidebar-link :href="route('kitchen.index')" icon="monitor" :active="request()->routeIs('kitchen.*')">
                        Màn hình bếp
                        <x-slot:badge>
                            <x-sidebar-badge key="kitchen" :count="$badges['kitchen'] ?? 0" label="món đang chờ bếp làm" tone="blue" />
                        </x-slot:badge>
                    </x-sidebar-link>
                @endcan
                @can('tables.view')
                    <x-sidebar-link :href="route('tables.index')" icon="armchair" :active="request()->routeIs('tables.*')">
                        Sơ đồ bàn
                        <x-slot:badge>
                            <x-sidebar-badge key="dirty" :count="$badges['dirty'] ?? 0" label="bàn cần dọn" tone="soft" />
                        </x-slot:badge>
                    </x-sidebar-link>
                @endcan

                @canany(['products.view', 'inventory.view', 'reports.view', 'employees.view', 'manage-permissions', 'settings.view', 'settings.activity'])
                    <p class="mt-4 mb-1 px-3 text-xs font-semibold tracking-wider text-stone-500 uppercase">Quản lý</p>
                @endcanany

                @can('products.view')
                    <x-sidebar-link :href="route('products.index')" icon="coffee" :active="request()->routeIs('products.*', 'categories.*', 'bundles.*')">Sản phẩm</x-sidebar-link>
                @endcan
                @can('inventory.view')
                    <x-sidebar-link :href="route('inventory.index')" icon="package" :active="request()->routeIs('inventory.*')">
                        Tồn kho
                        <x-slot:badge>
                            <x-sidebar-badge key="low_stock" :count="$badges['low_stock'] ?? 0" label="sản phẩm sắp hết" tone="soft" />
                        </x-slot:badge>
                    </x-sidebar-link>
                @endcan
                @can('reports.view')
                    <x-sidebar-link :href="route('reports.index')" icon="chart-bar" :active="request()->routeIs('reports.*')">Báo cáo</x-sidebar-link>
                @endcan
                @can('employees.view')
                    <x-sidebar-link :href="route('employees.index')" icon="users" :active="request()->routeIs('employees.*')">Nhân viên</x-sidebar-link>
                @endcan
                @can('manage-permissions')
                    <x-sidebar-link :href="route('permissions.index')" icon="shield-check" :active="request()->routeIs('permissions.*', 'roles.*')">Phân quyền</x-sidebar-link>
                @endcan
                @can('settings.view')
                    <x-sidebar-link :href="route('settings.general.edit')" icon="sliders" :active="request()->routeIs('settings.*')">Cài đặt</x-sidebar-link>
                @elsecan('settings.activity')
                    <x-sidebar-link :href="route('settings.activity')" icon="history" :active="request()->routeIs('settings.*')">Nhật ký</x-sidebar-link>
                @endcan
            </nav>

            <div class="mt-4 flex items-center gap-3 rounded-xl bg-stone-100 p-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-latte text-sm font-bold text-white">
                    {{ auth()->user()->initials() }}
                </span>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-stone-600">{{ auth()->user()->role?->name }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-stone-200 hover:text-ink" title="Đăng xuất" aria-label="Đăng xuất">
                        <x-icon name="log-out" class="size-4.5" />
                    </button>
                </form>
            </div>
        </aside>

        <div class="lg:pl-62">
            <div class="sticky top-0 z-20 flex items-center gap-3 border-b border-stone-200 bg-paper/95 px-4 py-3 backdrop-blur lg:hidden">
                <button type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Mở menu" class="flex size-10 items-center justify-center rounded-lg border border-stone-200 bg-white">
                    <x-icon name="menu" class="size-5" />
                </button>
                <p class="font-bold">{{ config('app.name') }}</p>
            </div>

            <main class="mx-auto max-w-[1200px] px-4 py-6 sm:px-8 lg:py-8">
                @session('status')
                    <div role="status" class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                        <x-icon name="check-circle" class="size-5 text-emerald-600" />
                        {{ $value }}
                    </div>
                @endsession

                @session('error')
                    <div role="alert" class="mb-6 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                        <x-icon name="alert-circle" class="size-5 text-red-600" />
                        {{ $value }}
                    </div>
                @endsession

                {{ $slot }}
            </main>
        </div>
    </body>
</html>
